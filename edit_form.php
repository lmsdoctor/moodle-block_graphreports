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
 * Block instance configuration form for block_graphreports.
 *
 * Uses a hybrid approach: moodleform as a shell (CSRF, save, permissions)
 * with an AMD module driving all visual interactions (accordion, DnD, toggles).
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_graphreports_edit_form extends block_edit_form {

    /** @var array<string, array<string, string>> Report catalogue [role => [id => langkey]] */
    private const REPORTS = [
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

    protected function specific_definition($mform): void {

        // ── Section header ──────────────────────────────────────────────────
        $mform->addElement(
            'header',
            'config_roles_header',
            get_string('config_roles_header', 'block_graphreports')
        );
        $mform->setExpanded('config_roles_header', true);

        // ── AMD mount point — the AMD module renders the full UI here ───────
        $mform->addElement('html', '<div id="graphreports-config-root" class="graphreports-config">');

        foreach (self::REPORTS as $role => $reports) {

            // Hidden: role enabled flag.
            $mform->addElement('hidden', 'config_role_' . $role, '1');
            $mform->setType('config_role_' . $role, PARAM_INT);
            $mform->setDefault('config_role_' . $role, 1);

            // Hidden: CSV order for this role.
            $mform->addElement('hidden', 'config_order_' . $role, '');
            $mform->setType('config_order_' . $role, PARAM_TEXT);
            $mform->setDefault('config_order_' . $role, implode(',', array_keys($reports)));

            foreach ($reports as $rid => $langkey) {
                // Hidden: report enabled flag.
                $mform->addElement('hidden', 'config_report_' . $role . '_' . $rid, '1');
                $mform->setType('config_report_' . $role . '_' . $rid, PARAM_INT);
                $mform->setDefault('config_report_' . $role . '_' . $rid, 1);

                // Hidden: column size (6 = half, 12 = full).
                $mform->addElement('hidden', 'config_size_' . $role . '_' . $rid, '6');
                $mform->setType('config_size_' . $role . '_' . $rid, PARAM_INT);
                $mform->setDefault('config_size_' . $role . '_' . $rid, 6);
            }
        }

        // ── Data attributes passed to the AMD module ────────────────────────
        // Encode the full catalogue as a data-* attribute so the AMD can
        // build the UI without making additional requests.
        $catalogue = [];
        foreach (self::REPORTS as $role => $reports) {
            $rolereports = [];
            foreach ($reports as $rid => $langkey) {
                $rolereports[] = [
                    'id'    => $rid,
                    'label' => get_string($langkey, 'block_graphreports'),
                ];
            }
            $catalogue[] = [
                'role'       => $role,
                'role_label' => get_string('config_role_' . $role, 'block_graphreports'),
                'reports'    => $rolereports,
            ];
        }

        $mform->addElement(
            'html',
            '<div id="graphreports-catalogue" data-catalogue="' .
            htmlspecialchars(json_encode($catalogue), ENT_QUOTES, 'UTF-8') .
            '"></div>'
        );

        $mform->addElement('html', '</div>'); // close #graphreports-config-root

        // Inline require() — works in modal/fragment contexts where $PAGE->requires
        // may not be flushed to the browser.
        $mform->addElement('html',
            '<script>require(["block_graphreports/edit_form"],function(m){m.init();});</script>'
        );
    }

    /**
     * Server-side validation (second line of defence after AMD client validation).
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $roles = array_keys(self::REPORTS);
        $anyRole = false;

        foreach ($roles as $role) {
            if (!empty($data['config_role_' . $role])) {
                $anyRole = true;

                // At least one report must be enabled per active role.
                $anyReport = false;
                foreach (array_keys(self::REPORTS[$role]) as $rid) {
                    if (!empty($data['config_report_' . $role . '_' . $rid])) {
                        $anyReport = true;
                        break;
                    }
                }
                if (!$anyReport) {
                    $errors['config_role_' . $role] =
                        get_string('error_min_one_report', 'block_graphreports');
                }
            }

            // Order CSV must only contain valid report IDs (injection prevention).
            $validids = array_keys(self::REPORTS[$role]);
            $orderraw = trim($data['config_order_' . $role] ?? '');
            if ($orderraw !== '') {
                foreach (array_filter(explode(',', $orderraw)) as $id) {
                    if (!in_array($id, $validids, true)) {
                        $errors['config_order_' . $role] =
                            get_string('error_invalid_order', 'block_graphreports');
                        break;
                    }
                }
            }

            // Column size must be 6 or 12.
            foreach (array_keys(self::REPORTS[$role]) as $rid) {
                $size = (int) ($data['config_size_' . $role . '_' . $rid] ?? 6);
                if (!in_array($size, [6, 12], true)) {
                    $errors['config_size_' . $role . '_' . $rid] =
                        get_string('error_invalid_size', 'block_graphreports');
                }
            }
        }

        if (!$anyRole) {
            $errors['config_roles_header'] =
                get_string('error_min_one_role', 'block_graphreports');
        }

        return $errors;
    }
}
