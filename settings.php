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
 * TODO describe file settings
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\lang_string;
use core\url;

defined('MOODLE_INTERNAL') || die();

$settings = new admin_settingpage('local_sync_courseleaders', new lang_string('pluginname', 'local_sync_courseleaders'));
if ($hassiteconfig) {
    $ADMIN->add('enrolments', new admin_externalpage('local_sync_courseleaders_index',
        get_string('pluginname', 'local_sync_courseleaders'),
        new url('/local/sync_courseleaders/index.php'),
        'moodle/site:config')
    );

    $settings->add(new admin_setting_configselect(
        'local_sync_courseleaders/expireenrolment',
        new lang_string('expireenrolments', 'local_sync_courseleaders'),
        new lang_string('expireenrolments_desc', 'local_sync_courseleaders'),
        (60 * 60 * 24 * 547),
        [
            0   => new lang_string('neverexpire', 'local_sync_courseleaders'),
            (60 * 60 * 24 * 182) => new lang_string('numdays', '', 182), // 6 months.
            (60 * 60 * 24 * 365) => new lang_string('numdays', '', 365), // 1 year.
            (60 * 60 * 24 * 547) => new lang_string('numdays', '', 547), // 18 months.
            (60 * 60 * 24 * 730) => new lang_string('numdays', '', 730), // 2 years.
        ]
    ));
    $ADMIN->add('localplugins', $settings);
}
