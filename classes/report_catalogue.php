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
 * Single source of truth for the report catalogue.
 *
 * All other files (block_graphreports.php, edit_form.php) MUST consume
 * this class instead of maintaining their own lists.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_graphreports;

defined('MOODLE_INTERNAL') || die();

/**
 * Report catalogue — maps role → [report_id => lang_key].
 */
class report_catalogue {

    /**
     * Full catalogue: role => [ report_id => lang_string_key ]
     *
     * The lang key must exist in lang/en/block_graphreports.php.
     */
    const REPORTS = [
        'admin' => [
            'logins_120'           => 'report_logins_120',
            'enrollments_course'   => 'report_enrollments_course',
            'active_vs_inactive'   => 'report_active_inactive',
            'completions_course'   => 'report_completions_course',
            'never_logged'         => 'report_never_logged',
            'new_registrations'    => 'report_new_registrations',
            'enrollments_category' => 'report_enrollments_category',
        ],
        'teacher' => [
            'teacher_enrollments'  => 'report_teacher_enrollments',
            'teacher_completion'   => 'report_teacher_completion',
            'teacher_inactive'     => 'report_teacher_inactive',
            'teacher_grades'       => 'report_teacher_grades',
            'teacher_forum'        => 'report_teacher_forum',
        ],
        'parent' => [
            'parent_courses'       => 'report_parent_courses',
            'parent_completion'    => 'report_parent_completion',
            'parent_lastlogin'     => 'report_parent_lastlogin',
            'parent_grades'        => 'report_parent_grades',
            'parent_pending'       => 'report_parent_pending',
        ],
        'student' => [
            'student_courses'      => 'report_student_courses',
            'student_completion'   => 'report_student_completion',
            'student_grades'       => 'report_student_grades',
        ],
    ];

    /**
     * Returns the ordered list of report IDs for a given role.
     *
     * @param  string $role  One of admin|teacher|parent|student.
     * @return string[]
     */
    public static function ids(string $role): array {
        return array_keys(self::REPORTS[$role] ?? []);
    }

    /**
     * Returns all defined role keys.
     *
     * @return string[]
     */
    public static function roles(): array {
        return array_keys(self::REPORTS);
    }
}
