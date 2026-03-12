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

    public function instance_allow_multiple(): bool {
        return false;
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

        // Check if this role is enabled in instance config.
        $cfg = $this->config ?? new stdClass();
        $roleenabled = (bool) ($cfg->{'role_' . $role} ?? true);

        if (!$roleenabled || !$this->can_view_dashboard($role, $USER)) {
            $this->content->text = \html_writer::div(
                get_string('no_permission_dashboard', 'block_graphreports'),
                'graphreports-empty'
            );
            return $this->content;
        }

        $allowed = $this->get_allowed_reports($cfg, $role);
        $order   = $this->get_report_order($cfg, $role);
        $sizes   = $this->get_report_sizes($cfg, $role);

        $reports = $reportmanager->get_reports_for_role($role, $allowed, $order);
        $context = $chartrenderer->prepare($reports, $role, $sizes);

        $this->page->requires->js_call_amd('block_graphreports/charts', 'init');

        $this->content->text = $OUTPUT->render_from_template(
            'block_graphreports/dashboard_' . $role,
            $context
        );

        return $this->content;
    }

    /**
     * Returns whitelisted report IDs for $role from instance config.
     * Returns null when no config set (= allow all).
     */
    private function get_allowed_reports(stdClass $cfg, string $role): ?array {
        static $reportids = [
            'admin'   => ['logins_120','enrollments_course','active_vs_inactive',
                          'completions_course','never_logged','new_registrations','enrollments_category'],
            'teacher' => ['teacher_enrollments','teacher_completion','teacher_inactive',
                          'teacher_grades','teacher_forum'],
            'parent'  => ['parent_courses','parent_completion','parent_lastlogin',
                          'parent_grades','parent_pending'],
            'student' => ['student_courses','student_completion','student_grades'],
        ];

        $all = $reportids[$role] ?? [];
        $allowed = [];
        foreach ($all as $rid) {
            $key = 'report_' . $role . '_' . $rid;
            // Default true when key not set (fresh install).
            if ((bool) ($cfg->{$key} ?? true)) {
                $allowed[] = $rid;
            }
        }
        // Return null (= allow all via report_manager) when config matches defaults.
        return count($allowed) === count($all) ? null : ($allowed ?: null);
    }

    /**
     * Returns ordered array of report IDs for $role from instance config CSV.
     */
    private function get_report_order(stdClass $cfg, string $role): array {
        $raw = trim($cfg->{'order_' . $role} ?? '');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(explode(',', $raw)));
    }

    /**
     * Returns [reportid => colsize] map for $role from instance config.
     */
    private function get_report_sizes(stdClass $cfg, string $role): array {
        static $reportids = [
            'admin'   => ['logins_120','enrollments_course','active_vs_inactive',
                          'completions_course','never_logged','new_registrations','enrollments_category'],
            'teacher' => ['teacher_enrollments','teacher_completion','teacher_inactive',
                          'teacher_grades','teacher_forum'],
            'parent'  => ['parent_courses','parent_completion','parent_lastlogin',
                          'parent_grades','parent_pending'],
            'student' => ['student_courses','student_completion','student_grades'],
        ];

        $sizes = [];
        foreach ($reportids[$role] ?? [] as $rid) {
            $raw = (int) ($cfg->{'size_' . $role . '_' . $rid} ?? 6);
            $sizes[$rid] = in_array($raw, [6, 12], true) ? $raw : 6;
        }
        return $sizes;
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
