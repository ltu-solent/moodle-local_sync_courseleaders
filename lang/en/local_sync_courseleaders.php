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
 * English language pack for local_sync_courseleaders
 *
 * @package    local_sync_courseleaders
 * @category   string
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allstatus'] = 'All';

$string['course'] = 'Course';

$string['description'] = "<p>This displays a list of all Module/Course mappings where at least one student on a Module
    is also enrolled on a Course. Any Course leaders on the Course pages will be enrolled on the corresponding Module pages,
    unless you Disable the mapping.</p>
    <p>Enrolments are updated via a Scheduled Task (Sync course leaders to modules).
    If you change the Enabled state, enrolments are updated when the task runs.</p>
    <p>Search will match either Course or Module.</p>";
$string['disable'] = 'Disable';
$string['disableselected'] = 'Disable selected';

$string['enable'] = 'Enable';
$string['enabled'] = 'Enabled';
$string['enableselected'] = 'Enable selected';
$string['expireenrolments'] = 'Expire enrolments';
$string['expireenrolments_desc'] = 'How long should the enrolment last. Expiry date is calculated when the enrolment is created, and can be altered after the fact.';

$string['filtermappings'] = 'Filter mappings';

$string['managecourseleadermappings'] = 'Manage course leader mappings to modules';
$string['module'] = 'Module';

$string['neverexpire'] = 'Never expire';
$string['noselection'] = 'No selection';
$string['notenabled'] = 'Not enabled';

$string['pluginname'] = 'Sync course leaders';

$string['resetall'] = 'Reset filters';

$string['selectedcourses'] = 'Selected courses';
$string['selectitem'] = 'Select mapping: {$a->moduleshortcode} -> {$a->courseshortcode}';

$string['task:syncleaders'] = 'Sync course leaders to modules';

$string['withselectedmappings'] = 'With selected mappings...';
