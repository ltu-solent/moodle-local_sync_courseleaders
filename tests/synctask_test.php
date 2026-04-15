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

use local_sync_courseleaders\task\syncleaders;
use core\context;


/**
 * Tests for Sync course leaders
 *
 * @package    local_sync_courseleaders
 * @category   test
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_sync_courseleaders\task\syncleaders
 */
final class synctask_test extends \advanced_testcase {
    /**
     * Reset DB after test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test cours leader enrolments on related modules
     *
     * @param int $expiry Expiry seconds
     * @return void
     * @dataProvider syncleaders_provider
     */
    public function test_syncleaders_enrols_course_leaders_on_modules_with_solaissits(int $expiry): void {
        global $DB;
        set_config('expireenrolment', $expiry, 'local_sync_courseleaders');
        // Create roles.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $courseleaderroleid = create_role('Course leader', 'courseleader', 'Course leader role');

        // Create users.
        $student = $this->getDataGenerator()->create_user();
        $leader = $this->getDataGenerator()->create_user();

        // Create courses: one course, two modules.
        $currentacademicyear = helper::get_currentacademicyear();
        set_config('sessions', $currentacademicyear, 'local_sync_courseleaders');
        $course = $this->getDataGenerator()->create_course(['shortname' => 'COURSE']);
        $module1 = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_' . $currentacademicyear]);
        $module2 = $this->getDataGenerator()->create_course(['shortname' => 'MOD102_' . $currentacademicyear]);
        $oldmodule = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_2022/23']);
        $excludedmodule = $this->getDataGenerator()->create_course(['shortname' => 'MOD103_' . $currentacademicyear]);

        // Add enrol_solaissits instances to all courses.
        /** @var \enrol_solaissits_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('solaissits');
        foreach ([$course, $module1, $module2, $oldmodule, $excludedmodule] as $c) {
            $enrolid = $enrolplugin->add_instance($c, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            // Enrol student via solaissits.
            $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        }

        // Assign course leader role on course.
        role_assign($courseleaderroleid, $leader->id, context\course::instance($course->id));

        // Ensure leader is not assigned on modules.
        foreach ([$module1, $module2, $oldmodule, $excludedmodule] as $mod) {
            $this->assertFalse($DB->record_exists('role_assignments', [
                'roleid' => $courseleaderroleid,
                'userid' => $leader->id,
                'contextid' => context\course::instance($mod->id)->id,
            ]));
        }

        set_config('excludeshortname', $excludedmodule->shortname, 'local_sync_courseleaders');

        // Run the task.
        $task = new syncleaders();
        $task->execute();
        $expireenrolments = get_config('local_sync_courseleaders', 'expireenrolment');
        $expiredate = date('Y-m-d', 0);
        if ($expireenrolments > 0) {
            $expiredate = date('Y-m-d', (time() + $expireenrolments));
        }

        // Now leader should be assigned as courseleader on both modules.
        foreach ([$module1, $module2] as $mod) {
            $instances = enrol_get_instances($mod->id, true);
            $manual = array_filter($instances, function ($instance) {
                return $instance->enrol == 'manual';
            });
            $manual = reset($manual);
            $this->assertTrue($DB->record_exists('role_assignments', [
                'roleid' => $courseleaderroleid,
                'userid' => $leader->id,
                'contextid' => context\course::instance($mod->id)->id,
            ]));
            $enrolments = $DB->get_records('user_enrolments', [
                'userid' => $leader->id,
                'enrolid' => $manual->id,
            ]);
            $enrolment = reset($enrolments);
            $this->assertCount(1, $enrolments);
            $this->assertEquals($expiredate, date('Y-m-d', $enrolment->timeend));
        }

        // The old module should not have any enrolments.
        $instances = enrol_get_instances($oldmodule->id, true);
        $manual = array_filter($instances, function ($instance) {
            return $instance->enrol == 'manual';
        });
        $manual = reset($manual);
        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($oldmodule->id)->id,
        ]));

        // The excluded module should not have any enrolments.
        $instances = enrol_get_instances($excludedmodule->id, true);
        $manual = array_filter($instances, function ($instance) {
            return $instance->enrol == 'manual';
        });
        $manual = reset($manual);
        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($excludedmodule->id)->id,
        ]));

        // Not really interested in the mtrace output.
        $this->expectOutputRegex('#Enrolling#');
    }

    /**
     * Expiry time frames provider
     *
     * @return array
     */
    public static function syncleaders_provider(): array {
        return [
            'unlimited' => [
                'expiry' => 0,
            ],
            'six months' => [
                'expiry' => (DAYSECS * 182),
            ],
            'one year' => [
                'expiry' => (DAYSECS * 365),
            ],
            'eighteen months' => [
                'expiry' => (DAYSECS * 547),
            ],
            'two years' => [
                'expiry' => (DAYSECS * 730),
            ],
        ];
    }

    /**
     * Check disabled mappings unenrol users
     *
     * @return void
     */
    public function test_disable_mapping(): void {
        global $DB;
        set_config('expireenrolment', 0, 'local_sync_courseleaders');
        // Create roles.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $courseleaderroleid = create_role('Course leader', 'courseleader', 'Course leader role');

        // Create users.
        $student = $this->getDataGenerator()->create_user();
        $leader = $this->getDataGenerator()->create_user();

        // Create courses: one course, two modules.
        $currentacademicyear = helper::get_currentacademicyear();
        set_config('sessions', $currentacademicyear, 'local_sync_courseleaders');
        $course = $this->getDataGenerator()->create_course(['shortname' => 'COURSE']);
        $module1 = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_' . $currentacademicyear]);
        $module2 = $this->getDataGenerator()->create_course(['shortname' => 'MOD102_' . $currentacademicyear]);

        // Add enrol_solaissits instances to all courses.
        $enrolplugin = enrol_get_plugin('solaissits');
        foreach ([$course, $module1, $module2] as $c) {
            $enrolid = $enrolplugin->add_instance($c, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            // Enrol student via solaissits.
            $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        }

        // Assign course leader role on course.
        role_assign($courseleaderroleid, $leader->id, context\course::instance($course->id));

        // Run the task to create the mappings.
        $task = new syncleaders();
        $task->execute();

        $instances = enrol_get_instances($module1->id, true);
        $manual = array_filter($instances, function ($instance) {
            return $instance->enrol == 'manual';
        });
        $manual = reset($manual);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($module1->id)->id,
        ]));
        $enrolments = $DB->get_records('user_enrolments', [
            'userid' => $leader->id,
            'enrolid' => $manual->id,
        ]);
        $this->assertCount(1, $enrolments);

        $disablethis = $DB->get_record('local_sync_courseleaders_map', [
            'moduleshortcode' => 'MOD101_' . $currentacademicyear,
            'courseshortcode' => 'COURSE',
        ]);
        $disablethis->enabled = 0;
        $DB->update_record('local_sync_courseleaders_map', $disablethis);
        $task->execute();

        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($module1->id)->id,
        ]));
        $enrolments = $DB->get_records('user_enrolments', [
            'userid' => $leader->id,
            'enrolid' => $manual->id,
        ]);
        $this->assertCount(0, $enrolments);

        $expected = '#Unenrolling ' . $leader->firstname . ' ' . $leader->lastname . ' from MOD101_' . $currentacademicyear . '#';
        $this->expectOutputRegex($expected);
    }

    /**
     * Check disabled mappings unenrol users
     *
     * @return void
     */
    public function test_suspending_courseleader(): void {
        global $DB;
        set_config('expireenrolment', 0, 'local_sync_courseleaders');
        set_config('sessions', helper::get_currentacademicyear(), 'local_sync_courseleaders');
        // Create roles.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $courseleaderroleid = create_role('Course leader', 'courseleader', 'Course leader role');

        // Create users.
        $student = $this->getDataGenerator()->create_user();
        $leader = $this->getDataGenerator()->create_user();

        // Create courses: one course, two modules.
        $currentacademicyear = helper::get_currentacademicyear();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'COURSE']);
        $module1 = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_' . $currentacademicyear]);
        $module2 = $this->getDataGenerator()->create_course(['shortname' => 'MOD102_' . $currentacademicyear]);

        // Add enrol_solaissits instances to all courses.
        $enrolplugin = enrol_get_plugin('solaissits');
        foreach ([$course, $module1, $module2] as $c) {
            $enrolid = $enrolplugin->add_instance($c, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            // Enrol student via solaissits.
            $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        }

        // Assign course leader role on course.
        role_assign($courseleaderroleid, $leader->id, context\course::instance($course->id));

        // Run the task to create the mappings.
        $task = new syncleaders();
        $task->execute();

        $instances = enrol_get_instances($module1->id, true);
        $manual = array_filter($instances, function ($instance) {
            return $instance->enrol == 'manual';
        });
        $manual = reset($manual);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($module1->id)->id,
        ]));
        $enrolments = $DB->get_records('user_enrolments', [
            'userid' => $leader->id,
            'enrolid' => $manual->id,
        ]);
        $this->assertCount(1, $enrolments);

        $leader->suspended = 1;
        user_update_user($leader, false, false);
        $task->execute();

        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($module1->id)->id,
        ]));
        $enrolments = $DB->get_records('user_enrolments', [
            'userid' => $leader->id,
            'enrolid' => $manual->id,
        ]);
        $this->assertCount(0, $enrolments);

        $expected = '#Unenrolling ' . $leader->firstname . ' ' . $leader->lastname . ' from MOD101_' . $currentacademicyear . '#';
        $this->expectOutputRegex($expected);
    }

    /**
     * Remove mappings where the module code doesn't match the selected academic year.
     *
     * @return void
     */
    public function test_remove_mappings(): void {
        global $DB;
        $this->resetAfterTest();
        $task = new syncleaders();
        $currentacademicyear = helper::get_currentacademicyear();
        $currentyearstart = explode('/', $currentacademicyear)[0];
        $startyears = range((int)$currentyearstart - 5, (int)$currentyearstart);
        set_config('sessions', $currentacademicyear, 'local_sync_courseleaders');
        $mappings = [];
        foreach ($startyears as $year) {
            for ($x = 0; $x < 5; $x++) {
                $mappings[] = [
                    // MOD400_2024/25.
                    'moduleshortcode' => 'MOD40' . $x . '_' . $year . '/' . (substr((string)((int)$year + 1), 2, 2)),
                    'courseshortcode' => 'XXCOURSECODE',
                ];
            }
        }
        $DB->insert_records('local_sync_courseleaders_map', $mappings);
        // Should expect 5 years * 5 module mappings.
        $this->assertCount(count($mappings), $DB->get_records('local_sync_courseleaders_map'));
        // Run the task to remove the old mappings.
        $task->execute();
        // Should only be 5 module mappings left for the selected academic year.
        $this->assertCount(5, $DB->get_records('local_sync_courseleaders_map'));
        $this->expectOutputRegex('#Removing old mappings...#');
    }

    /**
     * If any excluded shortnames are included in the mappings, they will be removed.
     *
     * @return void
     */
    public function test_remove_excluded_shortnames(): void {
        global $DB;
        $this->resetAfterTest();
        $task = new syncleaders();
        $currentacademicyear = helper::get_currentacademicyear();
        set_config('sessions', $currentacademicyear, 'local_sync_courseleaders');
        $exclude = [
            'ABC101_' . $currentacademicyear,
            'Someother_page',
        ];
        $mappings = [
            [
                'moduleshortcode' => 'ABC101_' . $currentacademicyear,
                'courseshortcode' => 'XXCOURSECODE',
            ],
            [
                'moduleshortcode' => 'Someother_page',
                'courseshortcode' => 'XXCOURSECODE',
            ],
            [
                'moduleshortcode' => 'ABC102_' . $currentacademicyear,
                'courseshortcode' => 'XXCOURSECODE',
            ],
        ];
        set_config('excludeshortname', implode(',', $exclude), 'local_sync_courseleaders');
        $DB->insert_records('local_sync_courseleaders_map', $mappings);
        $this->assertCount(count($mappings), $DB->get_records('local_sync_courseleaders_map'));
        // Run the task to remove the old mappings.
        $task->execute();
        // Should only be 1 module mappings left for the selected academic year.
        $this->assertCount(1, $DB->get_records('local_sync_courseleaders_map'));
        $this->expectOutputRegex('#Removing old mappings...#');
    }

    /**
     * The session setting determines which academic years are included for mapping modules and courses.
     *
     * @return void
     */
    public function test_session_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        // Create roles.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $courseleaderroleid = create_role('Course leader', 'courseleader', 'Course leader role');

        // Create and enrol users.
        $student = $this->getDataGenerator()->create_user();
        $leader = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'XXCOURSECODE']);
        /** @var \enrol_solaissits_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('solaissits');
        $enrolid = $enrolplugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
        $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
        // Enrol student via solaissits.
        $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        // Assign course leader role on course.
        role_assign($courseleaderroleid, $leader->id, context\course::instance($course->id));

        $task = new syncleaders();
        $sessionmenu = helper::get_session_menu();
        // Pick off the first 3 sessions for testing, which should include the next year, the current academic year and last year.
        $selectedsessions = array_slice($sessionmenu, 0, 3, true);
        set_config('sessions', implode(',', $selectedsessions), 'local_sync_courseleaders');
        $mappings = [];
        // Add a mapping for all sessions available in the menu.
        foreach ($sessionmenu as $session) {
            $mappings[] = [
                'moduleshortcode' => 'ABC101_' . $session,
                'courseshortcode' => 'XXCOURSECODE',
            ];
            // Create module for mapping.
            $module = $this->getDataGenerator()->create_course(['shortname' => 'ABC101_' . $session]);
            // Add student to module via solaissits.
            $enrolid = $enrolplugin->add_instance($module, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        }
        $DB->insert_records('local_sync_courseleaders_map', $mappings);
        $this->assertCount(count($mappings), $DB->get_records('local_sync_courseleaders_map'));

        // Run the task to remove mappings not in the selected sessions and enrol course leaders.
        $task->execute();

        $remainingmappings = $DB->get_records('local_sync_courseleaders_map');
        $this->assertCount(3, $remainingmappings);
        // Course leaders should only be enrolled on modules for the selected sessions.
        foreach ($mappings as $mapping) {
            $module = $DB->get_record('course', ['shortname' => $mapping['moduleshortcode']]);
            // Get last value after the underscore, which should be the year e.g. 2026/27.
            $len = strlen($mapping['moduleshortcode']);
            $year = substr($mapping['moduleshortcode'], $len - 7, $len);
            if (in_array($year, $selectedsessions)) {
                $this->assertTrue($DB->record_exists('role_assignments', [
                    'roleid' => $courseleaderroleid,
                    'userid' => $leader->id,
                    'contextid' => context\course::instance($module->id)->id,
                ]));
            } else {
                $this->assertFalse($DB->record_exists('role_assignments', [
                    'roleid' => $courseleaderroleid,
                    'userid' => $leader->id,
                    'contextid' => context\course::instance($module->id)->id,
                ]));
            }
        }
        // Remove the oldest session. This won't unenrol anyone, but will stop new enrolments.
        array_pop($selectedsessions);
        set_config('sessions', implode(',', $selectedsessions), 'local_sync_courseleaders');
        $task->execute();
        $remainingmappings = $DB->get_records('local_sync_courseleaders_map');
        $this->assertCount(2, $remainingmappings);
        $this->expectOutputRegex('#Removing old mappings...#');
    }
}
