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

namespace local_sync_courseleaders\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Class search_courses
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_courses extends external_api {
    /**
     * Parameters requires to search courses
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search string'),
            'enabled' => new external_value(PARAM_ALPHA, 'Enabled mappings', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Search courses
     *
     * @param string $query
     * @param string $enabled
     * @return array
     */
    public static function execute($query, $enabled): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'query' => $query,
                'enabled' => $enabled,
            ]
        );
        $select = "SELECT c.id courseid, CONCAT(c.shortname, ': ', c.fullname) label FROM {course} c";
        $wheres = [];
        $qparams = [];
        $join = ' JOIN {local_sync_courseleaders_map} m ON ' .
                    ' (m.moduleshortcode = c.shortname OR m.courseshortcode = c.shortname) ';
        switch ($enabled) {
            case 'enabled':
                $wheres[] = 'm.enabled = 1';
                break;
            case 'disabled':
                $wheres[] = 'm.enabled = 0';
                break;
            default:
                $join = '';
                break;
        }
        $select .= $join;
        if ($params['query']) {
            $likeshortname = $DB->sql_like("c.shortname", ':shortname', false, false);
            $likefullname = $DB->sql_like("c.fullname", ':fullname', false, false);
            $qparams['shortname'] = '%' . $DB->sql_like_escape($params['query']) . '%';
            $qparams['fullname'] = '%' . $DB->sql_like_escape($params['query']) . '%';
            $wheres[] = " ($likeshortname OR $likefullname) ";
        }

        $where = " WHERE 1=1 ";
        if (!empty($wheres)) {
            $where = " WHERE " . join(' AND ', $wheres);
        }

        $courses = $DB->get_records_sql($select . $where, $qparams, 0, 50);
        return $courses;
    }

    /**
     * Defines the returned structure of the array.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'courseid'),
                'label' => new external_value(PARAM_RAW, 'User friendly label - Shortname'),
            ])
        );
    }
}
