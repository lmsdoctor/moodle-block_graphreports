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
 * Report manager class for block_graphreports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_graphreports;

defined('MOODLE_INTERNAL') || die();

class report_manager {
    // ── Brand palette ─────────────────────────────────────────────────────────
    //    Primary blue : #018ae6  |  Yellow : #f6d402  |  Orange : #f1990f
    //    Brown        : #57330f  |  Gray   : #9ca3af (darkened from #f5f5f5)
    //    Derived tints: blue #60b8f4 · yellow #fde84d · orange #f7ba6e
    //    Derived shades: orange dark #c4720a · brown mid #8a5525

    // ── Semantic roles — status / completion ──────────────────────────────────
    private const COLOR_COMPLETED     = '#018ae6';          // Brand blue   – completed / positive
    private const COLOR_INPROGRESS    = '#f6d402';          // Brand yellow – in progress
    private const COLOR_NOTSTARTED    = '#9ca3af';          // Neutral gray – not yet started
    private const COLOR_PENDING       = '#f7ba6e';          // Orange tint  – pending activities

    // ── Semantic roles — user states ──────────────────────────────────────────
    private const COLOR_ACTIVE        = '#018ae6';          // Brand blue   – active users
    private const COLOR_INACTIVE      = '#f1990f';          // Brand orange – inactive / alert
    private const COLOR_NEVER_LOGGED  = '#57330f';          // Brand brown  – never accessed
    private const COLOR_WITH_ACCESS   = '#60b8f4';          // Blue tint    – has logged in

    // ── Line / series colours ─────────────────────────────────────────────────
    private const COLOR_LOGINS        = '#018ae6';          // Brand blue   – login line
    private const COLOR_REGISTRATIONS = '#f6d402';          // Brand yellow – registration line

    // ── Single-colour accent bars ─────────────────────────────────────────────
    private const COLOR_PRIMARY       = '#018ae6';          // Brand blue   – primary bars
    private const COLOR_ORANGE_ACCENT  = '#f1990f';          // Brand orange – teacher enrolments
    private const COLOR_BROWN         = '#57330f';          // Brand brown  – grades (dark)
    private const COLOR_SKY           = '#60b8f4';          // Blue tint    – forum activity
    private const COLOR_YELLOW_LIGHT  = '#fde84d';          // Yellow tint  – student completion
    private const COLOR_ORANGE_LIGHT  = '#f7ba6e';          // Orange tint  – parent completion

    // ── Transparent fills (radar / area charts) ───────────────────────────────
    private const COLOR_PRIMARY_FILL  = 'rgba(1,138,230,0.2)';    // Brand blue @ 20 %
    private const COLOR_ORANGE_FILL   = 'rgba(241,153,15,0.2)';   // Brand orange @ 20 %

    // ── Category palette (pie / multi-slice doughnuts) ────────────────────────
    private const COLORS_CATEGORY = [
        '#018ae6', '#f6d402', '#f1990f', '#57330f',
        '#60b8f4', '#fde84d', '#c4720a', '#8a5525',
    ];

    /** @var \stdClass */
    private $user;

    public function __construct(\stdClass $user) {
        $this->user = $user;
    }

    public function get_reports_for_role(string $role): array {
        return match ($role) {
            'admin' => $this->get_admin_reports(),
            'teacher' => $this->get_teacher_reports(),
            'parent' => $this->get_parent_reports(),
            default => $this->get_student_reports(),
        };
    }

    private function get_admin_reports(): array {
        return [
            $this->report_logins_last_120_days(),
            $this->report_enrollments_per_course(),
            $this->report_active_vs_inactive_users(),
            $this->report_completions_per_course(),
            $this->report_users_never_logged_in(),
            $this->report_new_registrations_per_month(),
            $this->report_enrollments_per_category(),
        ];
    }

    private function get_teacher_reports(): array {
        return [
            $this->report_teacher_course_enrollments(),
            $this->report_teacher_completion_progress(),
            $this->report_teacher_inactive_students(),
            $this->report_teacher_grades_by_activity(),
            $this->report_teacher_forum_activity(),
        ];
    }

    private function get_parent_reports(): array {
        return [
            $this->report_parent_child_courses(),
            $this->report_parent_child_completion(),
            $this->report_parent_child_last_login(),
            $this->report_parent_child_grades(),
            $this->report_parent_child_pending_activities(),
        ];
    }

    private function get_student_reports(): array {
        return [
            $this->report_student_my_courses(),
            $this->report_student_my_completion(),
            $this->report_student_my_grades(),
        ];
    }

    private function report_logins_last_120_days(): array {
        global $DB;

        $since = time() - (120 * DAYSECS);
        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(timecreated), '%Y-%u') AS week_label,
                       COUNT(DISTINCT userid) AS total
                  FROM {logstore_standard_log}
                 WHERE action = 'loggedin'
                   AND timecreated >= :since
              GROUP BY week_label
              ORDER BY week_label ASC
                 LIMIT 20";

        $rows = $this->cached_records_sql('admin_logins_120', $sql, ['since' => $since]);

        return [
            'id' => 'logins_120',
            'title' => get_string('report_logins_120', 'block_graphreports'),
            'type' => 'line',
            'labels' => array_column($rows, 'week_label'),
            'datasets' => [[
                'label' => get_string('report_logins_120', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'borderColor' => self::COLOR_LOGINS,
                'tension' => 0.4,
                'fill' => false,
            ]],
        ];
    }

    private function report_enrollments_per_course(): array {
        global $DB;

        $sql = "SELECT c.fullname AS course_name, COUNT(ue.id) AS total
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE c.visible = 1
              GROUP BY c.id, c.fullname
              ORDER BY total DESC
                 LIMIT 10";

        $rows = $this->cached_records_sql('admin_enrollments_course', $sql);

        return [
            'id' => 'enrollments_course',
            'title' => get_string('report_enrollments_course', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('report_enrollments_course', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'backgroundColor' => self::COLOR_PRIMARY,
            ]],
        ];
    }

    private function report_active_vs_inactive_users(): array {
        global $DB;

        $since = time() - (30 * DAYSECS);
        $active = $DB->count_records_select(
            'user',
            'lastaccess >= :since AND deleted = 0 AND suspended = 0',
            ['since' => $since]
        );
        $total = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
        $inactive = max(0, $total - $active);

        return [
            'id' => 'active_vs_inactive',
            'title' => get_string('report_active_inactive', 'block_graphreports'),
            'type' => 'doughnut',
            'labels' => [
                get_string('active_users', 'block_graphreports'),
                get_string('inactive_users', 'block_graphreports'),
            ],
            'datasets' => [[
                'data' => [$active, $inactive],
                'backgroundColor' => [self::COLOR_ACTIVE, self::COLOR_INACTIVE],
            ]],
        ];
    }

    private function report_completions_per_course(): array {
        global $DB;

        $sql = "SELECT c.fullname AS course_name, COUNT(cc.id) AS total
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE cc.timecompleted IS NOT NULL
              GROUP BY c.id, c.fullname
              ORDER BY total DESC
                 LIMIT 10";

        $rows = $this->cached_records_sql('admin_completions_course', $sql);

        return [
            'id' => 'completions_course',
            'title' => get_string('report_completions_course', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('report_completions_course', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'backgroundColor' => self::COLOR_COMPLETED,
            ]],
        ];
    }

    private function report_users_never_logged_in(): array {
        global $DB;

        $never = $DB->count_records_select(
            'user',
            'lastaccess = 0 AND deleted = 0 AND confirmed = 1 AND suspended = 0'
        );
        $logged = $DB->count_records_select(
            'user',
            'lastaccess > 0 AND deleted = 0 AND suspended = 0'
        );

        return [
            'id' => 'never_logged',
            'title' => get_string('report_never_logged', 'block_graphreports'),
            'type' => 'doughnut',
            'labels' => [
                get_string('users_with_access', 'block_graphreports'),
                get_string('users_never_logged', 'block_graphreports'),
            ],
            'datasets' => [[
                'data' => [$logged, $never],
                'backgroundColor' => [self::COLOR_WITH_ACCESS, self::COLOR_NEVER_LOGGED],
            ]],
        ];
    }

    private function report_new_registrations_per_month(): array {
        global $DB;

        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(timecreated), '%Y-%m') AS month_label,
                       COUNT(*) AS total
                  FROM {user}
                 WHERE deleted = 0
              GROUP BY month_label
              ORDER BY month_label DESC
                 LIMIT 12";

        $rows = array_reverse($this->cached_records_sql('admin_new_registrations', $sql));

        return [
            'id' => 'new_registrations',
            'title' => get_string('report_new_registrations', 'block_graphreports'),
            'type' => 'line',
            'labels' => array_column($rows, 'month_label'),
            'datasets' => [[
                'label' => get_string('report_new_registrations', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'borderColor' => self::COLOR_REGISTRATIONS,
                'tension' => 0.3,
                'fill' => false,
            ]],
        ];
    }

    private function report_enrollments_per_category(): array {
        global $DB;

        $sql = "SELECT cc.name AS category_name, COUNT(ue.id) AS total
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
              GROUP BY cc.id, cc.name
              ORDER BY total DESC
                 LIMIT 8";

        $rows = $this->cached_records_sql('admin_enrollments_category', $sql);

        return [
            'id' => 'enrollments_category',
            'title' => get_string('report_enrollments_category', 'block_graphreports'),
            'type' => 'pie',
            'labels' => array_column($rows, 'category_name'),
            'datasets' => [[
                'data' => array_values(array_column($rows, 'total')),
                'backgroundColor' => self::COLORS_CATEGORY,
            ]],
        ];
    }

    private function get_teacher_course_ids(): array {
        global $DB;

        $sql = "SELECT DISTINCT ctx.instanceid AS courseid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ctx.contextlevel = :ctxlevel
                   AND r.shortname IN ('editingteacher', 'teacher')";

        $rows = $this->cached_records_sql('teacher_course_ids', $sql, [
            'userid' => $this->user->id,
            'ctxlevel' => CONTEXT_COURSE,
        ]);

        return array_values(array_map(static fn($row) => (int) $row->courseid, $rows));
    }

    private function report_teacher_course_enrollments(): array {
        global $DB;

        $courseids = $this->get_teacher_course_ids();
        if (empty($courseids)) {
            return $this->empty_report('teacher_enrollments');
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT c.id, c.fullname AS course_name, COUNT(ue.id) AS total
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.courseid $insql
              GROUP BY c.id, c.fullname
              ORDER BY total DESC";

        $rows = $this->cached_records_sql('teacher_course_enrollments', $sql, $params);

        return [
            'id' => 'teacher_enrollments',
            'title' => get_string('report_teacher_enrollments', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('students', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'backgroundColor' => self::COLOR_ORANGE_ACCENT,
            ]],
        ];
    }

    private function report_teacher_completion_progress(): array {
        global $DB;

        $courseids = $this->get_teacher_course_ids();
        if (empty($courseids)) {
            return $this->empty_report('teacher_completion');
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT c.id, c.fullname AS course_name,
                       SUM(CASE WHEN cc.timecompleted IS NOT NULL THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN cc.timecompleted IS NULL THEN 1 ELSE 0 END) AS inprogress
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE cc.course $insql
              GROUP BY c.id, c.fullname";

        $rows = $this->cached_records_sql('teacher_completion_progress', $sql, $params);

        return [
            'id' => 'teacher_completion',
            'title' => get_string('report_teacher_completion', 'block_graphreports'),
            'type' => 'bar',
            'options' => [
                'scales' => [
                    'x' => ['stacked' => true],
                    'y' => ['stacked' => true],
                ],
            ],
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [
                [
                    'label' => get_string('completed', 'completion'),
                    'data' => array_values(array_column($rows, 'completed')),
                    'backgroundColor' => self::COLOR_COMPLETED,
                ],
                [
                    'label' => get_string('inprogress', 'completion'),
                    'data' => array_values(array_column($rows, 'inprogress')),
                    'backgroundColor' => self::COLOR_INPROGRESS,
                ],
            ],
        ];
    }

    private function report_teacher_inactive_students(): array {
        global $DB;

        $courseids = $this->get_teacher_course_ids();
        if (empty($courseids)) {
            return $this->empty_report('teacher_inactive');
        }

        $since = time() - (28 * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['since'] = $since;

        $sql = "SELECT c.id, c.fullname AS course_name, COUNT(DISTINCT u.id) AS total
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid $insql
                   AND u.lastaccess < :since
                   AND u.lastaccess > 0
                   AND u.deleted = 0
                   AND u.suspended = 0
              GROUP BY c.id, c.fullname";

        $rows = $this->cached_records_sql('teacher_inactive_students', $sql, $params);

        return [
            'id' => 'teacher_inactive',
            'title' => get_string('report_teacher_inactive', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('report_teacher_inactive', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'total')),
                'backgroundColor' => self::COLOR_INACTIVE,
            ]],
        ];
    }

    private function report_teacher_grades_by_activity(): array {
        global $DB;

        $courseids = $this->get_teacher_course_ids();
        if (empty($courseids)) {
            return $this->empty_report('teacher_grades');
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT gi.id, gi.itemname AS activity_name, ROUND(AVG(gg.finalgrade), 2) AS avg_grade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid $insql
                   AND gi.itemtype = 'mod'
                   AND gg.finalgrade IS NOT NULL
              GROUP BY gi.id, gi.itemname
              ORDER BY avg_grade ASC
                 LIMIT 10";

        $rows = $this->cached_records_sql('teacher_grades_activity', $sql, $params);

        return [
            'id' => 'teacher_grades',
            'title' => get_string('report_teacher_grades', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'activity_name'),
            'datasets' => [[
                'label' => get_string('report_teacher_grades', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'avg_grade')),
                'backgroundColor' => self::COLOR_BROWN,
            ]],
        ];
    }

    private function report_teacher_forum_activity(): array {
        global $DB;

        $courseids = $this->get_teacher_course_ids();
        if (empty($courseids)) {
            return $this->empty_report('teacher_forum');
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT c.id, c.fullname AS course_name, COUNT(fp.id) AS posts
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                  JOIN {forum} f ON f.id = fd.forum
                  JOIN {course} c ON c.id = f.course
                 WHERE f.course $insql
              GROUP BY c.id, c.fullname
              ORDER BY posts DESC";

        $rows = $this->cached_records_sql('teacher_forum_activity', $sql, $params);

        return [
            'id' => 'teacher_forum',
            'title' => get_string('report_teacher_forum', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('report_teacher_forum', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'posts')),
                'backgroundColor' => self::COLOR_SKY,
            ]],
        ];
    }

    private function get_mentee_id(): int {
        global $DB;

        $parentroleid = $DB->get_field('role', 'id', ['shortname' => 'parent']);
        if (!$parentroleid) {
            return 0;
        }

        $sql = "SELECT ctx.instanceid AS menteeid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ra.roleid = :roleid
                   AND ctx.contextlevel = :ctxlevel
                 LIMIT 1";

        $rows = $this->cached_records_sql('parent_mentee_id', $sql, [
            'userid' => $this->user->id,
            'roleid' => $parentroleid,
            'ctxlevel' => CONTEXT_USER,
        ]);
        $record = reset($rows);
        return !empty($record->menteeid) ? (int) $record->menteeid : 0;
    }

    private function report_parent_child_courses(): array {
        global $DB;

        $menteeid = $this->get_mentee_id();
        if (!$menteeid) {
            return $this->empty_report('parent_courses');
        }

        $sql = "SELECT c.fullname AS course_name,
                       CASE
                           WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                           WHEN cc.timestarted IS NOT NULL THEN 'inprogress'
                           ELSE 'notstarted'
                       END AS status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                 WHERE ue.userid = :userid
                   AND c.visible = 1";

        $rows = $this->cached_records_sql('parent_child_courses', $sql, ['userid' => $menteeid]);
        $statuses = array_column($rows, 'status');

        return [
            'id' => 'parent_courses',
            'title' => get_string('report_parent_courses', 'block_graphreports'),
            'type' => 'doughnut',
            'labels' => [
                get_string('completed', 'completion'),
                get_string('inprogress', 'completion'),
                get_string('notyetstarted', 'completion'),
            ],
            'datasets' => [[
                'data' => [
                    count(array_filter($statuses, fn($s) => $s === 'completed')),
                    count(array_filter($statuses, fn($s) => $s === 'inprogress')),
                    count(array_filter($statuses, fn($s) => $s === 'notstarted')),
                ],
                'backgroundColor' => [self::COLOR_COMPLETED, self::COLOR_INPROGRESS, self::COLOR_NOTSTARTED],
            ]],
        ];
    }

    private function report_parent_child_completion(): array {
        global $DB;

        $menteeid = $this->get_mentee_id();
        if (!$menteeid) {
            return $this->empty_report('parent_completion');
        }

        $sql = "SELECT c.fullname AS course_name,
                       ROUND(
                           (
                               SELECT COUNT(*)
                                 FROM {course_modules_completion} cmc
                                 JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                                WHERE cm.course = c.id
                                  AND cmc.userid = ue.userid
                                  AND cmc.completionstate > 0
                           )
                           / NULLIF(
                               (
                                   SELECT COUNT(*)
                                     FROM {course_modules} cm2
                                    WHERE cm2.course = c.id
                                      AND cm2.completion > 0
                               ),
                               0
                           ) * 100,
                           1
                       ) AS pct
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.userid = :userid
                   AND c.visible = 1";

        $rows = $this->cached_records_sql('parent_child_completion', $sql, ['userid' => $menteeid]);

        return [
            'id' => 'parent_completion',
            'title' => get_string('report_parent_completion', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('pct_progress', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'pct')),
                'backgroundColor' => self::COLOR_ORANGE_LIGHT,
            ]],
        ];
    }

    private function report_parent_child_last_login(): array {
        global $DB;

        $menteeid = $this->get_mentee_id();
        if (!$menteeid) {
            return $this->empty_report('parent_lastlogin');
        }

        $user = $DB->get_record('user', ['id' => $menteeid], 'id, firstname, lastname, lastaccess', MUST_EXIST);
        $daysago = $user->lastaccess ? (int) floor((time() - $user->lastaccess) / DAYSECS) : 0;

        return [
            'id' => 'parent_lastlogin',
            'title' => get_string('report_parent_lastlogin', 'block_graphreports'),
            'type' => 'bar',
            'labels' => [fullname($user)],
            'datasets' => [[
                'label' => get_string('daysago', 'block_graphreports'),
                'data' => [$daysago],
                'backgroundColor' => self::COLOR_WITH_ACCESS,
            ]],
        ];
    }

    private function report_parent_child_grades(): array {
        global $DB;

        $menteeid = $this->get_mentee_id();
        if (!$menteeid) {
            return $this->empty_report('parent_grades');
        }

        $sql = "SELECT gi.itemname AS activity_name, ROUND(gg.finalgrade, 1) AS grade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid
                   AND gi.itemtype = 'mod'
                   AND gg.finalgrade IS NOT NULL
              ORDER BY gg.timemodified DESC
                 LIMIT 10";

        $rows = $this->cached_records_sql('parent_child_grades', $sql, ['userid' => $menteeid]);

        return [
            'id' => 'parent_grades',
            'title' => get_string('report_parent_grades', 'block_graphreports'),
            'type' => 'radar',
            'labels' => array_column($rows, 'activity_name'),
            'datasets' => [[
                'label' => get_string('grades', 'grades'),
                'data' => array_values(array_column($rows, 'grade')),
                'backgroundColor' => self::COLOR_PRIMARY_FILL,
                'borderColor' => self::COLOR_PRIMARY,
            ]],
        ];
    }

    private function report_parent_child_pending_activities(): array {
        global $DB;

        $menteeid = $this->get_mentee_id();
        if (!$menteeid) {
            return $this->empty_report('parent_pending');
        }

        $sql = "SELECT COUNT(*) AS total
                  FROM {course_modules} cm
                  JOIN {enrol} e ON e.courseid = cm.course
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
             LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = ue.userid
                 WHERE ue.userid = :userid
                   AND cm.completion > 0
                   AND (cmc.completionstate IS NULL OR cmc.completionstate = 0)";

        $pending = $this->cached_count_sql('parent_pending_activities', $sql, ['userid' => $menteeid]);

        $sqltotal = "SELECT COUNT(*) AS total
                       FROM {course_modules} cm
                       JOIN {enrol} e ON e.courseid = cm.course
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      WHERE ue.userid = :userid
                        AND cm.completion > 0";
        $total = $this->cached_count_sql('parent_total_activities', $sqltotal, ['userid' => $menteeid]);
        $done = max(0, $total - $pending);

        return [
            'id' => 'parent_pending',
            'title' => get_string('report_parent_pending', 'block_graphreports'),
            'type' => 'doughnut',
            'labels' => [
                get_string('completed', 'completion'),
                get_string('pending', 'block_graphreports'),
            ],
            'datasets' => [[
                'data' => [$done, $pending],
                'backgroundColor' => [self::COLOR_COMPLETED, self::COLOR_PENDING],
            ]],
        ];
    }

    private function report_student_my_courses(): array {
        global $DB;

        $sql = "SELECT c.fullname AS course_name,
                       CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed' ELSE 'inprogress' END AS status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                 WHERE ue.userid = :userid
                   AND c.visible = 1";

        $rows = $this->cached_records_sql('student_my_courses', $sql, ['userid' => $this->user->id]);
        $statuses = array_column($rows, 'status');

        return [
            'id' => 'student_courses',
            'title' => get_string('report_student_courses', 'block_graphreports'),
            'type' => 'doughnut',
            'labels' => [
                get_string('completed', 'completion'),
                get_string('inprogress', 'completion'),
            ],
            'datasets' => [[
                'data' => [
                    count(array_filter($statuses, fn($s) => $s === 'completed')),
                    count(array_filter($statuses, fn($s) => $s === 'inprogress')),
                ],
                'backgroundColor' => [self::COLOR_COMPLETED, self::COLOR_INPROGRESS],
            ]],
        ];
    }

    private function report_student_my_completion(): array {
        global $DB;

        $sql = "SELECT c.fullname AS course_name,
                       ROUND(
                           (
                               SELECT COUNT(*)
                                 FROM {course_modules_completion} cmc
                                 JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                                WHERE cm.course = c.id
                                  AND cmc.userid = ue.userid
                                  AND cmc.completionstate > 0
                           )
                           / NULLIF(
                               (
                                   SELECT COUNT(*)
                                     FROM {course_modules} cm2
                                    WHERE cm2.course = c.id
                                      AND cm2.completion > 0
                               ),
                               0
                           ) * 100,
                           1
                       ) AS pct
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.userid = :userid
                   AND c.visible = 1";

        $rows = $this->cached_records_sql('student_my_completion', $sql, ['userid' => $this->user->id]);

        return [
            'id' => 'student_completion',
            'title' => get_string('report_student_completion', 'block_graphreports'),
            'type' => 'bar',
            'labels' => array_column($rows, 'course_name'),
            'datasets' => [[
                'label' => get_string('pct_progress', 'block_graphreports'),
                'data' => array_values(array_column($rows, 'pct')),
                'backgroundColor' => self::COLOR_YELLOW_LIGHT,
            ]],
        ];
    }

    private function report_student_my_grades(): array {
        global $DB;

        $sql = "SELECT gi.itemname AS activity_name, ROUND(gg.finalgrade, 1) AS grade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid
                   AND gi.itemtype = 'mod'
                   AND gg.finalgrade IS NOT NULL
              ORDER BY gg.timemodified DESC
                 LIMIT 8";

        $rows = $this->cached_records_sql('student_my_grades', $sql, ['userid' => $this->user->id]);

        return [
            'id' => 'student_grades',
            'title' => get_string('report_student_grades', 'block_graphreports'),
            'type' => 'radar',
            'labels' => array_column($rows, 'activity_name'),
            'datasets' => [[
                'label' => get_string('grades', 'grades'),
                'data' => array_values(array_column($rows, 'grade')),
                'backgroundColor' => self::COLOR_ORANGE_FILL,
                'borderColor' => self::COLOR_BROWN,
            ]],
        ];
    }

    private function empty_report(string $id): array {
        return [
            'id' => $id,
            'type' => 'empty',
            'title' => get_string('empty_state', 'block_graphreports'),
            'labels' => [],
            'datasets' => [],
        ];
    }

    /**
     * @return array<int, \stdClass>
     */
    private function cached_records_sql(string $key, string $sql, array $params = []): array {
        global $DB;

        $cache = \cache::make('block_graphreports', 'reportdata');
        $cachekey = $this->build_cache_key('rows_' . $key, $params);
        $rows = $cache->get($cachekey);
        if ($rows === false) {
            $rows = $DB->get_records_sql($sql, $params);
            $cache->set($cachekey, $rows);
        }

        return array_values((array) $rows);
    }

    private function cached_count_sql(string $key, string $sql, array $params = []): int {
        global $DB;

        $cache = \cache::make('block_graphreports', 'reportdata');
        $cachekey = $this->build_cache_key('count_' . $key, $params);
        $count = $cache->get($cachekey);
        if ($count === false) {
            $count = (int) $DB->count_records_sql($sql, $params);
            $cache->set($cachekey, $count);
        }

        return (int) $count;
    }

    private function build_cache_key(string $prefix, array $params): string {
        $ttlminutes = (int) get_config('block_graphreports', 'cache_ttl');
        if ($ttlminutes <= 0) {
            $ttlminutes = 60;
        }

        $bucket = (int) floor(time() / ($ttlminutes * MINSECS));
        $paramhash = substr(sha1(json_encode($params)), 0, 12);
        $rawkey = implode('_', [$prefix, (string) $this->user->id, (string) $bucket, $paramhash]);
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $rawkey);
    }
}
