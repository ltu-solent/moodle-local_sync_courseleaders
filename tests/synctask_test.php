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
use enrol_manual_plugin;

/**
 * Tests for Sync course leaders
 *
 * @package    local_sync_courseleaders
 * @category   test
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * @covers \local_sync_courseleaders\task\syncleaders
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
        $currentacademicyear = syncleaders::get_currentacademicyear();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'COURSE']);
        $module1 = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_' . $currentacademicyear]);
        $module2 = $this->getDataGenerator()->create_course(['shortname' => 'MOD102_' . $currentacademicyear]);
        $oldmodule = $this->getDataGenerator()->create_course(['shortname' => 'MOD101_2022/23']);

        // Add enrol_solaissits instances to all courses.
        $enrolplugin = enrol_get_plugin('solaissits');
        foreach ([$course, $module1, $module2, $oldmodule] as $c) {
            $enrolid = $enrolplugin->add_instance($c, ['status' => ENROL_INSTANCE_ENABLED, 'name' => 'solaissits']);
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            // Enrol student via solaissits.
            $enrolplugin->enrol_user($enrolinstance, $student->id, $studentrole->id);
        }

        // Assign course leader role on course.
        role_assign($courseleaderroleid, $leader->id, context\course::instance($course->id));

        // Ensure leader is not assigned on modules.
        foreach ([$module1, $module2, $oldmodule] as $mod) {
            $this->assertFalse($DB->record_exists('role_assignments', [
                'roleid' => $courseleaderroleid,
                'userid' => $leader->id,
                'contextid' => context\course::instance($mod->id)->id,
            ]));
        }

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
            $manual = array_filter($instances, function($instance) {
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
        $manual = array_filter($instances, function($instance) {
            return $instance->enrol == 'manual';
        });
        $manual = reset($manual);
        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $courseleaderroleid,
            'userid' => $leader->id,
            'contextid' => context\course::instance($oldmodule->id)->id,
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
                'expiry' => (60 * 60 * 24 * 182),
            ],
            'one year' => [
                'expiry' => (60 * 60 * 24 * 365),
            ],
            'eighteen months' => [
                'expiry' => (60 * 60 * 24 * 547),
            ],
            'two years' => [
                'expiry' => (60 * 60 * 24 * 730),
            ],
        ];
    }
}
