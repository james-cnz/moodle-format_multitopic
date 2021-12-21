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
 * Upgrade scripts for Multitopic course format.
 *
 * @package    format_multitopic
 * @copyright  2020 James Calder and Otago Polytechnic
 * @copyright  based on work by 2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for Multitopic course format.
 *
 * @param int|float $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_multitopic_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2020090400) {

        // Change periodduration "0 days" to "0 day".
        $DB->execute(
            "UPDATE {course_format_options} SET value = '0 day'
             WHERE format = ? AND name = 'periodduration' AND value = '0 days'",
            ['multitopic']
        );

        upgrade_plugin_savepoint(true, 2020090400, 'format', 'multitopic');
    }

    if ($oldversion < 2021092301) {

        // Add collapsible.
        $DB->execute(
            "INSERT IGNORE INTO {course_format_options} (courseid, format, sectionid, name, value)
             SELECT course AS courseid, 'multitopic' AS format, cs.id AS sectionid, 'collapsible' AS name,
             (CASE WHEN cfo.value LIKE '0 %' THEN '0' ELSE '1' END) AS value
             FROM {course_sections} cs
             JOIN {course} c ON c.id = cs.course
             LEFT JOIN {course_format_options} cfo
             ON cfo.courseid = cs.course AND cfo.sectionid = cs.id AND cfo.name = 'periodduration'
             WHERE c.format = ? AND cfo.value LIKE '0 %'",
            ['multitopic']
        );

        upgrade_plugin_savepoint(true, 2021092301, 'format', 'multitopic');
    }

    return true;
}
