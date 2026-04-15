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

namespace local_sync_courseleaders;

/**
 * Class helper
 *
 * @package    local_sync_courseleaders
 * @copyright  2026 Southampton Solent University {@link https://www.solent.ac.uk}
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Returns key/value pairs for Session menu. Used also for validation.
     *
     * @return array
     */
    public static function get_session_menu(): array {
        $years = range(2020, date('Y') + 1);
        $options = [];
        foreach ($years as $year) {
            $yearplusone = (string)((int)substr($year, 2, 2) + 1);
            $options[$year . '/' . $yearplusone] = $year . '/' . $yearplusone;
        }
        return array_reverse($options);
    }

    /**
     * Take a csv string and return a clean array
     *
     * @param string $csv
     * @return array
     */
    public static function clean_csv(string $csv): array {
        $list = [];
        if (empty(trim($csv))) {
            return $list;
        }
        $items = explode(',', $csv);
        if (count($items) == 0) {
            return $list;
        }
        foreach ($items as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $list[] = $item;
            }
        }
        return $list;
    }
}
