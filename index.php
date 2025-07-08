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
 * TODO describe file index
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\html_writer;
use core\url;

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_sync_courseleaders', '', null, '/local/sync_courseleaders.index.php');

$url = new url('/local/sync_courseleaders/index.php', []);
$PAGE->set_url($url);
$PAGE->set_context(\core\context\system::instance());
$PAGE->requires->js_call_amd('local_sync_courseleaders/mapping_actions_bulk', 'init');

$resetfilter = optional_param('cancel', null, PARAM_TEXT);
$params = [
    'selectedcourses' => [],
    'enabled' => '',
];

$PAGE->set_heading(get_string('managecourseleadermappings', 'local_sync_courseleaders'));
echo $OUTPUT->header();

$filterform = new \local_sync_courseleaders\form\mapping_filter_form(null);
if ($resetfilter) {
    $filterform->reset();
}
if ($filterdata = $filterform->get_data()) {
    $params['selectedcourses'] = $filterdata->selectedcourses;
    $params['enabled'] = $filterdata->enabled;
} else if (!$resetfilter) {
    $params['selectedcourses'] = optional_param_array('selectedcourses', [], PARAM_INT);
    $params['enabled'] = optional_param('enabled', '', PARAM_ALPHA);
}

echo html_writer::div(get_string('description', 'local_sync_courseleaders'));

$filterform->display();

$table = new \local_sync_courseleaders\output\mapping_table('courseleadersync', $params);

$table->out(50, false);

echo $OUTPUT->footer();
