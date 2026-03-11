<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Block class for graph reports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_graphreports extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_graphreports');
    }

    public function applicable_formats(): array {
        return ['my' => true];
    }

    public function has_config(): bool {
        return true;
    }

    public function get_content(): stdClass {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        require_login();

        $this->content = new stdClass();
        $this->content->footer = '';

        $roledetector = new \block_graphreports\role_detector($USER);
        $reportmanager = new \block_graphreports\report_manager($USER);
        $chartrenderer = new \block_graphreports\chart_renderer();

        $role = $roledetector->get_role();
        if (!$this->can_view_dashboard($role, $USER)) {
            $this->content->text = \html_writer::div(
                get_string('no_permission_dashboard', 'block_graphreports'),
                'graphreports-empty'
            );
            return $this->content;
        }

        $reports = $reportmanager->get_reports_for_role($role);
        $context = $chartrenderer->prepare($reports, $role);

        $this->page->requires->js_call_amd('block_graphreports/charts', 'init');

        $this->content->text = $OUTPUT->render_from_template(
            'block_graphreports/dashboard_' . $role,
            $context
        );

        return $this->content;
    }

    private function can_view_dashboard(string $role, \stdClass $user): bool {
        $syscontext = \context_system::instance();
        if (has_capability('moodle/site:config', $syscontext, $user)) {
            return true;
        }

        return match ($role) {
            'admin' => has_capability('block/graphreports:viewadmindashboard', $syscontext, $user),
            'teacher' => $this->has_capability_in_any_role_context(
                'block/graphreports:viewteacherdashboard',
                $user->id,
                CONTEXT_COURSE,
                ['editingteacher', 'teacher'],
                $user
            ),
            'parent' => $this->has_capability_in_any_role_context(
                'block/graphreports:viewparentdashboard',
                $user->id,
                CONTEXT_USER,
                ['parent'],
                $user
            ),
            default => $this->has_capability_in_any_role_context(
                'block/graphreports:viewstudentdashboard',
                $user->id,
                CONTEXT_COURSE,
                ['student'],
                $user
            ),
        };
    }

    private function has_capability_in_any_role_context(
        string $capability,
        int $userid,
        int $contextlevel,
        array $roleshortnames,
        \stdClass $user
    ): bool {
        global $DB;

        if (empty($roleshortnames)) {
            return false;
        }

        [$rolesql, $params] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['contextlevel'] = $contextlevel;

        $sql = "SELECT DISTINCT ra.contextid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ctx.contextlevel = :contextlevel
                   AND r.shortname $rolesql";

        $contextids = $DB->get_fieldset_sql($sql, $params);
        foreach ($contextids as $contextid) {
            $context = \context::instance_by_id((int) $contextid, IGNORE_MISSING);
            if ($context && has_capability($capability, $context, $user)) {
                return true;
            }
        }

        return false;
    }
}
