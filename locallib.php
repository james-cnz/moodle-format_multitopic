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
 * Library of useful functions
 * 
 * INCLUDED /course/lib.php selected functions
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_course
 */

defined('MOODLE_INTERNAL') || die;



/**
 * For a given course section, marks it visible or hidden,
 * and does the same for every activity in that section
 *
 * @param int $courseid course id
 * @param int $sectionnumber The section number to adjust
 * @param int $visibility The new visibility
 * @return array A list of resources which were hidden in the section
 */
function set_section_visible($courseid, $sectionnumber, $visibility) {
    global $DB;

    $resourcestotoggle = array();
    if ($section = $DB->get_record("course_sections", array("course"=>$courseid, "section"=>$sectionnumber))) {
        course_update_section($courseid, $section, array('visible' => $visibility));

        // Determine which modules are visible for AJAX update
        $modules = !empty($section->sequence) ? explode(',', $section->sequence) : array();
        if (!empty($modules)) {
            list($insql, $params) = $DB->get_in_or_equal($modules);
            $select = 'id ' . $insql . ' AND visible = ?';
            array_push($params, $visibility);
            if (!$visibility) {
                $select .= ' AND visibleold = 1';
            }
            $resourcestotoggle = $DB->get_fieldset_select('course_modules', 'id', $select, $params);
        }
    }
    return $resourcestotoggle;
}


/**
 * Creates a course section and adds it to the specified position
 *
 * @param int|stdClass $courseorid course id or course object
 * @param int $position position to add to, 0 means to the end. If position is greater than
 *        number of existing secitons, the section is added to the end. This will become sectionnum of the
 *        new section. All existing sections at this or bigger position will be shifted down.
 * @param bool $skipcheck the check has already been made and we know that the section with this position does not exist
 * @return stdClass created section object
 */
function course_create_section($courseorid, $position = 0, $skipcheck = false) {
    global $DB;
    $courseid = is_object($courseorid) ? $courseorid->id : $courseorid;

    // Find the last sectionnum among existing sections.
    if ($skipcheck) {
        $lastsection = $position - 1;
    } else {
        $lastsection = (int)$DB->get_field_sql('SELECT max(section) from {course_sections} WHERE course = ?', [$courseid]);
    }

    // First add section to the end.
    $cw = new stdClass();
    $cw->course   = $courseid;
    $cw->section  = $lastsection + 1;
    $cw->summary  = '';
    $cw->summaryformat = FORMAT_HTML;
    $cw->sequence = '';
    $cw->name = null;
    $cw->visible = 1;
    $cw->availability = null;
    $cw->timemodified = time();
    $cw->id = $DB->insert_record("course_sections", $cw);

    // Now move it to the specified position.
    if ($position > 0 && $position <= $lastsection) {
        $course = is_object($courseorid) ? $courseorid : get_course($courseorid);
        move_section_to($course, $cw->section, $position, true);
        $cw->section = $position;
    }

    core\event\course_section_created::create_from_section($cw)->trigger();

    rebuild_course_cache($courseid, true);
    return $cw;
}


/**
 * Moves a section within a course, from a position to another.
 * Be very careful: $section and $destination refer to section number,
 * not id!.
 *
 * @param object $course
 * @param int $section Section number (not id!!!)
 * @param int $destination
 * @param bool $ignorenumsections
 * @return boolean Result
 */
function move_section_to($course, $section, $destination, $ignorenumsections = false) {
/// Moves a whole course section up and down within the course
    global $USER, $DB;

    if (!$destination && $destination != 0) {
        return true;
    }

    // compartibility with course formats using field 'numsections'
    $courseformatoptions = course_get_format($course)->get_format_options();
    if ((!$ignorenumsections && array_key_exists('numsections', $courseformatoptions) &&
            ($destination > $courseformatoptions['numsections'])) || ($destination < 1)) {
        return false;
    }

    // Get all sections for this course and re-order them (2 of them should now share the same section number)
    if (!$sections = $DB->get_records_menu('course_sections', array('course' => $course->id),
            'section ASC, id ASC', 'id, section')) {
        return false;
    }

    $movedsections = reorder_sections($sections, $section, $destination);

    // Update all sections. Do this in 2 steps to avoid breaking database
    // uniqueness constraint
    $transaction = $DB->start_delegated_transaction();
    foreach ($movedsections as $id => $position) {
        if ($sections[$id] !== $position) {
            $DB->set_field('course_sections', 'section', -$position, array('id' => $id));
        }
    }
    foreach ($movedsections as $id => $position) {
        if ($sections[$id] !== $position) {
            $DB->set_field('course_sections', 'section', $position, array('id' => $id));
        }
    }

    // If we move the highlighted section itself, then just highlight the destination.
    // Adjust the higlighted section location if we move something over it either direction.
    if ($section == $course->marker) {
        course_set_marker($course->id, $destination);
    } elseif ($section > $course->marker && $course->marker >= $destination) {
        course_set_marker($course->id, $course->marker+1);
    } elseif ($section < $course->marker && $course->marker <= $destination) {
        course_set_marker($course->id, $course->marker-1);
    }

    $transaction->allow_commit();
    rebuild_course_cache($course->id, true);
    return true;
}


/**
 * Checks if the current user can delete a section (if course format allows it and user has proper permissions).
 *
 * @param int|stdClass $course
 * @param int|stdClass|section_info $section
 * @return bool
 */
function course_can_delete_section($course, $section) {
    if (is_object($section)) {
        $section = $section->section;
    }
    if (!$section) {
        // Not possible to delete 0-section.
        return false;
    }
    // Course format should allow to delete sections.
    if (!course_get_format($course)->can_delete_section($section)) {
        return false;
    }
    // Make sure user has capability to update course and move sections.
    $context = context_course::instance(is_object($course) ? $course->id : $course);
    if (!has_all_capabilities(array('moodle/course:movesections', 'moodle/course:update'), $context)) {
        return false;
    }
    // Make sure user has capability to delete each activity in this section.
    $modinfo = get_fast_modinfo($course);
    if (!empty($modinfo->sections[$section])) {
        foreach ($modinfo->sections[$section] as $cmid) {
            if (!has_capability('moodle/course:manageactivities', context_module::instance($cmid))) {
                return false;
            }
        }
    }
    return true;
}


/**
 * Reordering algorithm for course sections. Given an array of section->section indexed by section->id,
 * an original position number and a target position number, rebuilds the array so that the
 * move is made without any duplication of section positions.
 * Note: The target_position is the position AFTER WHICH the moved section will be inserted. If you want to
 * insert a section before the first one, you must give 0 as the target (section 0 can never be moved).
 *
 * @param array $sections
 * @param int $origin_position
 * @param int $target_position
 * @return array
 */
function reorder_sections($sections, $origin_position, $target_position) {
    if (!is_array($sections)) {
        return false;
    }

    // We can't move section position 0
    if ($origin_position < 1) {
        echo "We can't move section position 0";
        return false;
    }

    // Locate origin section in sections array
    if (!$origin_key = array_search($origin_position, $sections)) {
        echo "searched position not in sections array";
        return false; // searched position not in sections array
    }

    // Extract origin section
    $origin_section = $sections[$origin_key];
    unset($sections[$origin_key]);

    // Find offset of target position (stupid PHP's array_splice requires offset instead of key index!)
    $found = false;
    $append_array = array();
    foreach ($sections as $id => $position) {
        if ($found) {
            $append_array[$id] = $position;
            unset($sections[$id]);
        }
        if ($position == $target_position) {
            if ($target_position < $origin_position) {
                $append_array[$id] = $position;
                unset($sections[$id]);
            }
            $found = true;
        }
    }

    // Append moved section
    $sections[$origin_key] = $origin_section;

    // Append rest of array (if applicable)
    if (!empty($append_array)) {
        foreach ($append_array as $id => $position) {
            $sections[$id] = $position;
        }
    }

    // Renumber positions
    $position = 0;
    foreach ($sections as $id => $p) {
        $sections[$id] = $position;
        $position++;
    }

    return $sections;

}
