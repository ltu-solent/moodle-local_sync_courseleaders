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

namespace local_sync_courseleaders\task;

use core\context;
use core_php_time_limit;
use core_user;

/**
 * Class syncleaders
 *
 * @package    local_sync_courseleaders
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syncleaders extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:syncleaders', 'local_sync_courseleaders');
    }

    /**
     * Execute mapping and syncing task
     *
     * @return void
     */
    public function execute() {
        global $DB;
        // This may take a long time.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $courseleaderrole = $DB->get_record('role', ['shortname' => 'courseleader']);
        $c1shortnamelike = $DB->sql_like('c1.shortname', ':c1shortname');
        $c2shortnamenotlike = $DB->sql_like('c2.shortname', ':c2shortname', false, false, true);
        // 1. Build mapping table from student enrolments.
        // For Moodle, must have a distinct first field as this is the key.
        $sql = "SELECT DISTINCT(CONCAT(c1.shortname, '|', c2.shortname)) id,
                    c1.shortname AS moduleshortcode, c2.shortname AS courseshortcode
                  FROM {user_enrolments} ue
                  JOIN {enrol} e1 ON e1.id = ue.enrolid
                    AND e1.enrol = :enrol1
                  JOIN {course} c1 ON c1.id = e1.courseid
                  JOIN {context} cx1 ON cx1.instanceid = c1.id
                    AND cx1.contextlevel = :context1
                  JOIN {role_assignments} ra1 ON ra1.contextid = cx1.id
                    AND ra1.roleid = :roleid1
                    AND ra1.userid = ue.userid
                    AND ra1.component = :component1
                    AND ra1.itemid = e1.id
                  JOIN {user_enrolments} ue2 ON ue2.userid = ue.userid
                    AND ue2.status = :status2active
                  JOIN {enrol} e2 ON e2.id = ue2.enrolid
                    AND e2.enrol = :enrol2
                  JOIN {course} c2 ON c2.id = e2.courseid
                  JOIN {context} cx2 ON cx2.instanceid = c2.id
                    AND cx2.contextlevel = :context2
                  JOIN {role_assignments} ra2 ON ra2.contextid = cx2.id
                    AND ra2.roleid = :roleid2
                    AND ra2.userid = ue.userid
                    AND ra2.component = :component2
                    AND ra2.itemid = e2.id
                 WHERE c1.id <> c2.id
                    AND ue.status = :status1active
                    AND {$c1shortnamelike}
                    AND {$c2shortnamenotlike}";
        // This assumes module shortnames end with the academic year and course shortnames do not have underscores.
        // I could use relative categories or the course custom field "pagetype" to be more precise, but that would involve
        // more joins.
        // I want to exclude "Additional Resources".

        $params = [
            'roleid1' => $studentrole->id,
            'enrol1' => 'solaissits',
            'context1' => CONTEXT_COURSE,
            'component1' => 'enrol_solaissits',
            'status2active' => ENROL_USER_ACTIVE,
            'enrol2' => 'solaissits',
            'context2' => CONTEXT_COURSE,
            'roleid2' => $studentrole->id,
            'component2' => 'enrol_solaissits',
            'status1active' => ENROL_USER_ACTIVE,
            'c1shortname' => '%\_' . self::get_currentacademicyear(),
            'c2shortname' => '%\_%',
        ];
        $records = $DB->get_records_sql($sql, $params);

        // Insert unique mappings.
        $map = [];
        foreach ($records as $key => $rec) {
            $map[$key] = ['moduleshortcode' => $rec->moduleshortcode, 'courseshortcode' => $rec->courseshortcode];
        }
        foreach ($map as $pair) {
            if (!$DB->record_exists('local_sync_courseleaders_map', $pair)) {
                $DB->insert_record('local_sync_courseleaders_map', $pair, false, true);
            }
        }

        // 2. For each mapping, enrol course leaders on modules.
        // Do I want to go through all mappings ever?
        // Might be an idea to clear up old mappings.
        $mappings = $DB->get_records('local_sync_courseleaders_map');
        $enrolplugin = enrol_get_plugin('manual');
        $expireenrolment = get_config('local_sync_courseleaders', 'expireenrolment') ?? (60 * 60 * 24 * 547); // 18 months.
        $timeend = 0;
        if ($expireenrolment > 0) {
            $timeend = time() + $expireenrolment;
        }

        foreach ($mappings as $mapping) {
            $course = $DB->get_record('course', ['shortname' => $mapping->courseshortcode]);
            // Using visibility as a proxy for being templated.
            $module = $DB->get_record('course', ['shortname' => $mapping->moduleshortcode, 'visible' => 1]);
            if (!$course || !$module) {
                continue;
            }
            $leaders = $DB->get_records('role_assignments', [
                'roleid' => $courseleaderrole->id,
                'contextid' => context\course::instance($course->id)->id,
            ]);
            if (count($leaders) == 0) {
                continue;
            }
            $enabled = $mapping->enabled
                ? get_string('enabled', 'local_sync_courseleaders')
                : get_string('notenabled', 'local_sync_courseleaders');
            mtrace(count($leaders) . ' leaders found for mapping ' .
                $mapping->courseshortcode . ' to ' . $mapping->moduleshortcode . " ($enabled)");
            $instances = enrol_get_instances($module->id, true);
            $manualinstance = array_filter($instances, function($instance) {
                return $instance->enrol == 'manual';
            });
            if (count($manualinstance) == 0) {
                continue;
            }
            $manualinstance = reset($manualinstance);
            $modulecontext = context\course::instance($module->id);
            foreach ($leaders as $leader) {
                // Check if already enrolled as course leader on the module.
                $raexists = $DB->record_exists('role_assignments', [
                    'roleid' => $courseleaderrole->id,
                    'userid' => $leader->userid,
                    'contextid' => $modulecontext->id,
                ]);
                $cl = core_user::get_user($leader->userid);
                $fullname = core_user::get_fullname($cl);
                if ($raexists && !$mapping->enabled) {
                    mtrace('- Unenrolling ' . $fullname . ' from ' . $mapping->moduleshortcode);
                    $enrolplugin->unenrol_user($manualinstance, $leader->userid);
                    role_unassign($courseleaderrole->id, $leader->userid, $modulecontext->id);
                }

                if (!$raexists && $mapping->enabled) {
                    $expirydate = '';
                    if ($timeend > 0) {
                        $expirydate = ' and will expire on ' . date('Y-m-d', $timeend);
                    }
                    mtrace('- Enrolling ' . $fullname . ' on ' . $mapping->moduleshortcode . $expirydate);
                    // Doing this as a manual enrolment, so we can suspend later if we want.
                    $enrolplugin->enrol_user($manualinstance, $leader->userid, $courseleaderrole->id, 0, $timeend);
                }
            }
        }
    }

    /**
     * Returns the current academic year, assuming it starts on 1st August.
     *
     * @return string Formatted YYYY/YY
     */
    public static function get_currentacademicyear(): string {
        $cyear = date('Y');
        $cmonth = date('n');
        $yearend = $cyear;
        $yearstart = $cyear;
        if ($cmonth < 8) {
            $yearstart = $cyear - 1;
        } else {
            $yearend = $cyear + 1;
        }
        $yearend = substr($yearend, 2, 2);
        return "$yearstart/$yearend";
    }
}
