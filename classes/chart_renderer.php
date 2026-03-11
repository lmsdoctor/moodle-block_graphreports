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
 * Chart renderer class for block_graphreports.
 *
 * @package    block_graphreports
 * @copyright  2026, LMS Doctor <info@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_graphreports;

defined('MOODLE_INTERNAL') || die();

class chart_renderer {
    /** @var int */
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

    public function prepare(array $reports, string $role): array {
        $maxreports = (int) get_config('block_graphreports', 'max_reports');
        if ($maxreports > 0) {
            $reports = array_slice($reports, 0, $maxreports);
        }

        $charts = [];
        $defaultoptions = [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];

        foreach ($reports as $report) {
            if (($report['type'] ?? '') === 'empty') {
                $charts[] = [
                    'chart_id' => $report['id'] ?? uniqid('chart_', true),
                    'chart_title' => $report['title'] ?? get_string('empty_state', 'block_graphreports'),
                    'has_data' => false,
                    'chart_data' => '',
                ];
                continue;
            }

            $config = [
                'type' => $report['type'] ?? 'bar',
                'data' => [
                    'labels' => $report['labels'] ?? [],
                    'datasets' => $report['datasets'] ?? [],
                ],
                'options' => array_replace_recursive($defaultoptions, $report['options'] ?? []),
            ];

            $hasdata = false;
            foreach (($report['datasets'] ?? []) as $dataset) {
                if (!empty($dataset['data'])) {
                    $hasdata = true;
                    break;
                }
            }

            $charts[] = [
                'chart_id' => $report['id'] ?? uniqid('chart_', true),
                'chart_title' => $report['title'] ?? '',
                'has_data' => $hasdata,
                'chart_data' => json_encode($config, self::JSON_FLAGS),
            ];
        }

        $chartcount = count($charts);
        $chartswithdata = 0;
        $pointcount = 0;
        foreach ($charts as $chart) {
            if ($chart['has_data']) {
                $chartswithdata++;
            }
        }
        foreach ($reports as $report) {
            foreach (($report['datasets'] ?? []) as $dataset) {
                $pointcount += count($dataset['data'] ?? []);
            }
        }

        $kpis = [
            [
                'value' => $chartcount,
                'label' => get_string('kpi_total_charts', 'block_graphreports'),
                'color' => 'blue',
            ],
            [
                'value' => $chartswithdata,
                'label' => get_string('kpi_charts_with_data', 'block_graphreports'),
                'color' => 'green',
            ],
            [
                'value' => max(0, $chartcount - $chartswithdata),
                'label' => get_string('kpi_empty_charts', 'block_graphreports'),
                'color' => 'yellow',
            ],
            [
                'value' => $pointcount,
                'label' => get_string('kpi_data_points', 'block_graphreports'),
                'color' => 'red',
            ],
        ];

        $badgeclass = match ($role) {
            'admin' => 'graphreports-header-badge--admin',
            'teacher' => 'graphreports-header-badge--teacher',
            'parent' => 'graphreports-header-badge--parent',
            default => 'graphreports-header-badge--student',
        };
        $rolename = match ($role) {
            'admin' => get_string('role_admin', 'block_graphreports'),
            'teacher' => get_string('role_teacher', 'block_graphreports'),
            'parent' => get_string('role_parent', 'block_graphreports'),
            default => get_string('role_student', 'block_graphreports'),
        };
        $gridclass = in_array($role, ['admin', 'teacher', 'parent'], true)
            ? 'graphreports-grid--2col'
            : 'graphreports-grid--1col';

        return [
            'role' => $role,
            'dashboard_title' => get_string('dashboard_' . $role, 'block_graphreports'),
            'role_label' => $rolename,
            'role_badge_class' => $badgeclass,
            'grid_class' => $gridclass,
            'charts' => $charts,
            'kpis' => $kpis,
        ];
    }
}
