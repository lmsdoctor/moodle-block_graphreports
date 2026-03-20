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
 * English language strings for block_graphreports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Graph reports';
$string['graphreports:addinstance'] = 'Add a new Graph reports block';
$string['graphreports:myaddinstance'] = 'Add a new Graph reports block to My Moodle';
$string['privacy:metadata'] = 'The Graph reports block does not store personal data.';

$string['dashboard_admin'] = 'Executive dashboard';
$string['dashboard_teacher'] = 'My classroom dashboard';
$string['dashboard_parent'] = 'My child dashboard';
$string['dashboard_student'] = 'My progress dashboard';
$string['role_admin'] = 'Admin';
$string['role_teacher'] = 'Teacher';
$string['role_parent'] = 'Parent';
$string['role_student'] = 'Student';

$string['setting_max_reports'] = 'Max reports per role';
$string['setting_max_reports_desc'] = 'Maximum number of charts shown in each dashboard role.';
$string['setting_cache_ttl'] = 'Cache refresh (minutes)';
$string['setting_cache_ttl_desc'] = 'Refresh window used for report cache buckets.';

$string['empty_state'] = 'No data available for this chart yet.';
$string['no_permission_dashboard'] = 'You do not have permission to view this dashboard.';
$string['active_users'] = 'Active users';
$string['inactive_users'] = 'Inactive users';
$string['users_with_access'] = 'Users with access';
$string['users_never_logged'] = 'Never logged in';
$string['students'] = 'Students';
$string['pending'] = 'Pending activities';
$string['pct_progress'] = '% Progress';
$string['daysago'] = 'Days since last login';
$string['kpi_total_charts'] = 'Total charts';
$string['kpi_charts_with_data'] = 'Charts with data';
$string['kpi_empty_charts'] = 'Empty charts';
$string['kpi_data_points'] = 'Data points';
$string['logins'] = 'Logins';
$string['low'] = 'Low';
$string['medium'] = 'Medium';
$string['high'] = 'High';
$string['critical'] = 'Critical';

$string['report_logins_120'] = 'Logins - Last 120 days';
$string['report_enrollments_course'] = 'Enrollments per course';
$string['report_active_inactive'] = 'Active vs Inactive users';
$string['report_completions_course'] = 'Completions per course';
$string['report_never_logged'] = 'Users who never logged in';
$string['report_new_registrations'] = 'New registrations per month';
$string['report_enrollments_category'] = 'Enrollments per category';

$string['report_teacher_enrollments'] = 'My students per course';
$string['report_teacher_completion'] = 'Completion progress per course';
$string['report_teacher_inactive'] = 'Inactive students (+4 weeks)';
$string['report_teacher_grades'] = 'Average grade per activity';
$string['report_teacher_forum'] = 'Forum activity by student';

$string['report_parent_courses'] = 'My child\'s course status';
$string['report_parent_completion'] = 'My child\'s completion (%)';
$string['report_parent_lastlogin'] = 'Last login';
$string['report_parent_grades'] = 'My child\'s grades';
$string['report_parent_pending'] = 'My child\'s pending activities';
$string['report_parent_grades_by_course'] = 'My child\'s grades by course';
$string['report_parent_completion_criteria'] = 'My child\'s completion criteria';
$string['report_parent_time_spent'] = 'Time spent in courses';
$string['report_parent_recent_logins'] = 'Recent activity (last 7 days)';
$string['report_parent_badges'] = 'My child\'s badges earned';

$string['report_teacher_enrollments'] = 'My students per course';
$string['report_teacher_completion'] = 'Completion progress per course';
$string['report_teacher_inactive'] = 'Inactive students (+4 weeks)';
$string['report_teacher_grades'] = 'Average grade per activity';
$string['report_teacher_forum'] = 'Forum activity by student';
$string['report_teacher_learner'] = 'Learner report with grades';
$string['report_teacher_activity_completions'] = 'Activity completions with dates';
$string['report_teacher_badges'] = 'Students\' badges earned';
$string['report_teacher_scorm_status'] = 'SCORM completion status';
$string['report_teacher_analytics'] = 'Predictive analytics indicators';

$string['report_student_courses'] = 'My courses';
$string['report_student_completion'] = 'My completion progress';
$string['report_student_grades'] = 'My grades';

// Instance config form.
$string['config_roles_header']    = 'Roles & Reports Configuration';
$string['config_role_admin']      = 'Enable Admin Dashboard';
$string['config_role_teacher']    = 'Enable Teacher Dashboard';
$string['config_role_parent']     = 'Enable Parent Dashboard';
$string['config_role_student']    = 'Enable Student Dashboard';
$string['size_half']              = 'Half';
$string['size_full']              = 'Full width';
$string['config_order_hint']      = 'Drag rows to reorder reports';
$string['error_min_one_role']     = 'At least one role must be enabled.';
$string['error_min_one_report']   = 'At least one report must be enabled for this role.';
$string['error_invalid_order']    = 'Invalid report order data.';
$string['error_invalid_size']     = 'Invalid column size value.';
