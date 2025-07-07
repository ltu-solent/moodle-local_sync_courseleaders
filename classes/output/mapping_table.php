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

namespace local_sync_courseleaders\output;

use core\lang_string;
use core\output\html_writer;
use core\url;
use core_table\sql_table;
use stdClass;

/**
 * Class mapping_table
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_table extends sql_table {
    /**
     * Constructor
     *
     * @param string $uniqueid
     * @param array $filters
     */
    public function __construct($uniqueid, $filters) {
        global $OUTPUT;
        parent::__construct($uniqueid);
        $this->set_attribute('id', 'coursemodulemappings_table');
        $checkbox = new \core\output\checkbox_toggleall($uniqueid, true, [
            'id' => 'select-all-mappings',
            'name' => 'select-all-mappings',
            'label' => get_string('selectall'),
            'labelclasses' => 'sr-only',
            'classes' => 'm-1',
            'checked' => false,
        ]);
        $columns = [
            'select' => $OUTPUT->render($checkbox),
            'id' => 'id',
            'module' => new lang_string('module', 'local_sync_courseleaders'),
            'course' => new lang_string('course', 'local_sync_courseleaders'),
            'enabled' => new lang_string('enabled', 'local_sync_courseleaders'),
        ];
        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));
        $this->define_baseurl(new url("/local/sync_courseleaders/index.php", []));
        $select = "m.id, m.moduleshortcode, m.courseshortcode, m.enabled,
            module.fullname modulefullname, module.id moduleid,
            course.fullname coursefullname, course.id courseid";
        $from = "{local_sync_courseleaders_map} m
            JOIN {course} module ON module.shortname = m.moduleshortcode
            JOIN {course} course ON course.shortname = m.courseshortcode
        ";
        $where = "1=1";
        $this->set_sql($select, $from, $where);
    }

    public function col_course($row): string {
        $courselink = html_writer::link(
            new url('/course/view.php', ['id' => $row->courseid]),
            $row->coursefullname
        );
        $participantslink = html_writer::link(
            new url('/user/index.php', ['id' => $row->courseid]),
            new lang_string('participants')
        );
        $html = $courselink . '<br><small>' . $row->courseshortcode . ' ' . $participantslink . '</small>';
        return $html;
    }

    public function col_enabled($row): string {
        return ($row->enabled)
            ? new lang_string('enabled', 'local_sync_courseleaders')
            : new lang_string('notenabled', 'local_sync_courseleaders');
    }

    public function col_module($row): string {
        $courselink = html_writer::link(
            new url('/course/view.php', ['id' => $row->moduleid]),
            $row->modulefullname
        );
        $participantslink = html_writer::link(
            new url('/user/index.php', ['id' => $row->moduleid]),
            new lang_string('participants')
        );
        $html = $courselink . '<br><small>' . $row->moduleshortcode . ' ' . $participantslink . '</small>';
        return $html;
    }

    public function col_select($row): string {
        global $OUTPUT;
        $name = 'mapping' . $row->id;
        $checkbox = new \core\output\checkbox_toggleall(
            $this->uniqueid,
            false,
            [
                'classes' => 'mappingcheckbox m-1',
                'id' => $name,
                'name' => $name,
                'checked' => false,
                'label' => new lang_string('selectitem', 'local_sync_courseleaders', $row),
                'labelclasses' => 'accesshide',
                'value' => $row->id,
            ]
        );
        return $OUTPUT->render($checkbox);
    }

    public function wrap_html_finish(): void {
        global $OUTPUT;

        $data = new stdClass();
        $data->showbulkactions = true;

        if ($data->showbulkactions) {
            $data->id = 'courseleadersmappingsbulkactions';
            $data->attributes = [
                [
                    'name' => 'data-action',
                    'value' => 'toggle',
                ],
                [
                    'name' => 'data-togglegroup',
                    'value' => $this->uniqueid,
                ],
                [
                    'name' => 'data-toggle',
                    'value' => 'action',
                ],
                [
                    'name' => 'disabled',
                    'value' => true,
                ],
            ];
            $data->actions = [
                [
                    'value' => '#disableselect',
                    'name' => get_string('disableselected', 'local_sync_courseleaders'),
                ],
                [
                    'value' => '#enableselect',
                    'name' => get_string('enableselected', 'local_sync_courseleaders'),
                ],
            ];
        }

        echo $OUTPUT->render_from_template('local_sync_courseleaders/bulk_action_menu', $data);
    }
}

