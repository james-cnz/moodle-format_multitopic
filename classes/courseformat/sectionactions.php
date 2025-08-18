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

namespace format_multitopic\courseformat;

use stdClass;
use core_courseformat\local\sectionactions as base_sectionactions;

/**
 * Section course format actions.
 *
 * @package    format_multitopic
 * @copyright  2024 onwards James Calder and Otago Polytechnic
 * @copyright  based on work by 2023 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionactions extends base_sectionactions {

    /**
     * Create a course section using a record object.
     *
     * If no position is specified, the section is added to the end of the course.
     *
     * @param stdClass $fields the fields to set on the section
     * @param bool $skipcheck the position check has already been made and we know it can be used
     * @return stdClass the created section record
     */
    public function fmt_create_from_object(stdClass $fields, bool $skipcheck = false): stdClass {
        global $DB;
        require_once(__DIR__.'/../../locallib.php');

        $skipcheck = $skipcheck && isset($fields->section);

        // Determine the create position, and adapt fields for the move method, if necessary.
        if ($skipcheck) {
            $createnum = $fields->section;
        } else {
            $createnum = $DB->get_field_sql(
                'SELECT max(section) from {course_sections} WHERE course = ?',
                [$this->course->id]
            ) + 1;
            if (!isset($fields->section) && !isset($fields->prevupid) && !isset($fields->nextupid) && !isset($fields->parentid)) {
                $fields->nextupid = null;
            }
            if (empty($fields->component) && !isset($fields->level)) {
                $fields->level = 2;
            }
        }

        // First add section to the end.
        $sectionrecord = (object) [
            'course' => $this->course->id,
            'section' => $createnum,
            'summary' => $fields->summary ?? '',
            'summaryformat' => $fields->summaryformat ?? FORMAT_HTML,
            'sequence' => '',
            'name' => $fields->name ?? null,
            'visible' => $fields->visible ?? 1,
            'availability' => $fields->availability ?? null,
            'component' => $fields->component ?? null,
            'itemid' => $fields->itemid ?? null,
            'timemodified' => time(),
        ];
        $sectionrecord->id = $DB->insert_record("course_sections", $sectionrecord);
        if (empty($fields->component) && !($skipcheck && ($fields->section > 0))) {
            $DB->insert_record("course_format_options", [
                'courseid' => $this->course->id,
                'format' => 'multitopic',
                'sectionid' => $sectionrecord->id,
                'name' => 'level',
                'value' => 0,
            ]);
        }

        // Now move it to the specified position, if necessary.
        $skipmove = $skipcheck || !empty($fields->component) && property_exists($fields, 'nextupid') && ($fields->nextupid == null);
        if (!$skipmove) {
            try {
                $movednews = format_multitopic_move_section_to(
                                $this->course, [$sectionrecord], $fields, !empty($fields->component) ? 2 : 1
                            );
                $sectionrecord->section = $movednews[$sectionrecord->id]->section;
            } catch (\moodle_exception $e) {
                $DB->delete_records('course_sections', ['id' => $sectionrecord->id]);
                throw $e;
            }
        }

        \core\event\course_section_created::create_from_section($sectionrecord)->trigger();

        rebuild_course_cache($this->course->id, true);
        return $sectionrecord;
    }

}
