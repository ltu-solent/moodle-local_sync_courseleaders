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

namespace local_sync_courseleaders\form;

use core\lang_string;
use moodleform;

/**
 * Class mapping_filter_form
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_filter_form extends moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'filtermappingshdr', new lang_string('filtermappings', 'local_sync_courseleaders'));
        $mform->setExpanded('filtermappingshdr', true);
        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'local_sync_courseleaders'),
            'ajax' => 'local_sync_courseleaders/form-course-selector',
            'valuehtmlcallback' => function($value) {
                global $DB;
                $course = $DB->get_record('course', ['id' => $value]);
                return $course->shortname . ': ' . $course->fullname;
            },
        ];
        $mform->addElement('autocomplete',
            'selectedcourses',
            new lang_string('selectedcourses',
            'local_sync_courseleaders'),
            [],
            $options
        );
        $mform->setDefault('selectedcourses', []);

        $options = [
            '' => new lang_string('allstatus', 'local_sync_courseleaders'),
            'enabled' => new lang_string('enabled', 'local_sync_courseleaders'),
            'disabled' => new lang_string('notenabled', 'local_sync_courseleaders'),
        ];
        $mform->addElement('select', 'enabled', new lang_string('enabled', 'local_sync_courseleaders'), $options);
        $mform->setDefault('enabled', '');

        $buttons = [];
        $buttons[] = $mform->createElement('cancel', 'cancel', new lang_string('resetall', 'local_sync_courseleaders'));
        $buttons[] = $mform->createElement('submit', 'submitbutton', new lang_string('filtermappings', 'local_sync_courseleaders'));
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Reset the form
     *
     * @return void
     */
    public function reset() {
        $this->_form->updateSubmission(null, null);
    }
}
