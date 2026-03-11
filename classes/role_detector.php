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
 * Role detector class for block_graphreports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_graphreports;

defined('MOODLE_INTERNAL') || die();

class role_detector {
    /** @var \stdClass */
    private $user;

    public function __construct(\stdClass $user) {
        $this->user = $user;
    }

    public function get_role(): string {
        $syscontext = \context_system::instance();

        if (has_capability('moodle/site:config', $syscontext, $this->user) ||
            has_capability('block/graphreports:viewadmindashboard', $syscontext, $this->user)) {
            return 'admin';
        }

        if ($this->is_parent()) {
            return 'parent';
        }

        if ($this->is_teacher()) {
            return 'teacher';
        }

        return 'student';
    }

    private function is_parent(): bool {
        global $DB;

        $parentroleid = $DB->get_field('role', 'id', ['shortname' => 'parent']);
        if (!$parentroleid) {
            return false;
        }

        $sql = "SELECT COUNT(ra.id)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ra.roleid = :roleid
                   AND ctx.contextlevel = :ctxlevel";

        return $DB->count_records_sql($sql, [
            'userid' => $this->user->id,
            'roleid' => $parentroleid,
            'ctxlevel' => CONTEXT_USER,
        ]) > 0;
    }

    private function is_teacher(): bool {
        global $DB;

        $sql = "SELECT COUNT(ra.id)
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND r.shortname IN ('editingteacher', 'teacher')
                   AND ctx.contextlevel = :ctxlevel";

        return $DB->count_records_sql($sql, [
            'userid' => $this->user->id,
            'ctxlevel' => CONTEXT_COURSE,
        ]) > 0;
    }
}
