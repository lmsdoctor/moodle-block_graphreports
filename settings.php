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
 * Plugin settings for block_graphreports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configselect(
        'block_graphreports/max_reports',
        get_string('setting_max_reports', 'block_graphreports'),
        get_string('setting_max_reports_desc', 'block_graphreports'),
        '5',
        ['3' => '3', '5' => '5', '7' => '7', '10' => '10']
    ));

    $settings->add(new admin_setting_configselect(
        'block_graphreports/cache_ttl',
        get_string('setting_cache_ttl', 'block_graphreports'),
        get_string('setting_cache_ttl_desc', 'block_graphreports'),
        '60',
        ['15' => '15 min', '30' => '30 min', '60' => '1 hour', '120' => '2 hours']
    ));
}
