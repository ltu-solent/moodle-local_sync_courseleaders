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
 * Class update_mappings
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_mappings extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'mappingids' => new external_multiple_structure(new external_value(PARAM_INT, 'Mapping ID')),
            'enabled' => new external_value(PARAM_INT, 'Enabled', VALUE_REQUIRED, 1),
        ]);
    }

    public static function execute($mappingids, int $enabled) {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'mappingids' => $mappingids,
                'enabled' => $enabled,
            ]
        );
        require_capability('moodle/site:config', \core\context\system::instance());
        [$insql, $inparams] = $DB->get_in_or_equal($params['mappingids'], SQL_PARAMS_NAMED);
        $inparams['enabled'] = $enabled;
        $sql = "UPDATE {local_sync_courseleaders_map} SET enabled = :enabled WHERE id {$insql}";
        $DB->execute($sql, $inparams);
        return [
            'result' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'The processing result'),
        ]);
    }

}
