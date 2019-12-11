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
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 1999 Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


/**
 * For a given course section, marks it visible or hidden,
 * and does the same for every activity in that section
 *
 * @param int $courseid course id
 * @param stdClass $section The section to adjust.  Must specify id
 * @param int $visibility The new visibility.  0 = hidden, 1 = visible
 * @return array A list of resources which were hidden in the section
 */
function format_multitopic_set_section_visible(int $courseid, \stdClass $section, int $visibility) : array {
    // CHANGED LINE ABOVE: Use sectioninfo, not section number.
    global $DB;

    $resourcestotoggle = array();
    // ADDED.
    // Fetch section info.
    $sections = course_get_format($courseid)->fmt_get_sections();
    $section = array_key_exists($section->id, $sections) ? $sections[$section->id] : null;
    // We will recurse if setting visibility to hidden, because hidden sections should not contain visible sections.
    $recurse = ($visibility == 0);
    // END ADDED.
    for ($subsection = $section;
        $subsection && ($subsection->id == $section->id || $recurse && $subsection->levelsan > $section->levelsan);
        $subsection = array_key_exists($subsection->nextanyid, $sections) ? $sections[$subsection->nextanyid] : null) {
        // CHANGED LINES ABOVE: Recurse, if necessary.
        course_update_section($courseid, $subsection, array('visible' => $visibility)); // CHANGED: $section -> $subsection .

        // Determine which modules are visible for AJAX update.
        $modules = !empty($subsection->sequence) ? explode(',', $subsection->sequence) : array();
        // CHANGED LINE ABOVE: $section -> $subsection.
        if (!empty($modules)) {
            list($insql, $params) = $DB->get_in_or_equal($modules);
            $select = 'id ' . $insql . ' AND visible = ?';
            array_push($params, $visibility);
            if (!$visibility) {
                $select .= ' AND visibleold = 1';
            }
            $resourcestotoggle = array_merge($resourcestotoggle,
                                            $DB->get_fieldset_select('course_modules', 'id', $select, $params));
            // CHANGED LINE ABOVE: Merge results.
        }
    }
    return $resourcestotoggle;
}


/**
 * Creates a course section and adds it to the specified position
 *
 * @param stdClass $courseorid course id or course object
 * @param stdClass $section position to insert at.  Must specify parentid.  May specify level.
 * @return stdClass created section object.  Has database properties plus parentid and levelsan.
 */
function format_multitopic_course_create_section(\stdClass $courseorid, \stdClass $section) : \stdClass {
    // CHANGED LINE ABOVE: Use section info, specifying parentid and level, instead of section number.
    global $DB;
    $courseid = is_object($courseorid) ? $courseorid->id : $courseorid;

    // ADDED: Require that the parent exists.
    $parent = $DB->get_record('course_sections', ['id' => $section->parentid, 'course' => $courseid]);
    if (!$parent) {
        throw new \moodle_exception('sectionnotexist');
    }
    // END ADDED.

    // Find the last sectionnum among existing sections.
    if (false) {                                                                // CHANGED: Don't skip check.
        $lastsection = $section->section - 1;                                   // CHANGED: Extract from section info.
    } else {
        $lastsection = (int)$DB->get_field_sql('SELECT max(section) from {course_sections} WHERE course = ?', [$courseid]);
    }

    // First add section to the end.
    $cw = new \stdClass();
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
    if (true) {                                                                 // CHANGED: We've already checked the parent exists.
        $course = is_object($courseorid) ? $courseorid : get_course($courseorid);
        rebuild_course_cache($courseid, true);                                  // ADDED.
        format_multitopic_move_section_to($course, $cw, $section, true);        // CHANGED: Use section info instead of position.
        // END CHANGED.
        $cw->section = (int)$DB->get_field_sql('SELECT section from {course_sections} WHERE course = ? AND id = ?',
                                                [$courseid, $cw->id]);          // CHANGED.
        $cw->parentid = $section->parentid;                                     // ADDED.
        $cw->levelsan = $section->level ?? FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; // ADDED.
    }

    \core\event\course_section_created::create_from_section($cw)->trigger();

    rebuild_course_cache($courseid, true);
    return $cw;
}


/**
 * Moves a section within a course, from a position to another.
 *
 * @param stdClass $course
 * @param stdClass $section The section to be moved.  Must specify id.
 * @param stdClass $destination Where to move it to.  Must specify parentid, prevupid, or nextupid.  May specify level.
 */
function format_multitopic_move_section_to(\stdClass $course, \stdClass $section, \stdClass $destination) {
    // CHANGED LINE ABOVE: Use section info instead of number.  No return value (use exceptions).
    // Moves a course section within the course.
    // CHANGES THROUGHOUT: Use section info instead of number.
    global $DB;                                                                 // CHANGED: Removed $USER.

    if (!isset($destination)) {                                                 // CHANGED.
        throw new \moodle_exception('sectionnotexist');                         // CHANGED.
    }

    // Compatibility with course formats using field 'numsections'.
    // REMOVED.

    // Get all sections for this course and re-order them.
    if (!$sections = course_get_format($course)->fmt_get_sections()) {          // CHANGED.
        throw new \moodle_exception('cannotcreateorfindstructs');               // CHANGED.
    }

    $movedsections = format_multitopic_reorder_sections($sections, $section, $destination); // CHANGED.

    // Update all sections. Do this in 2 steps to avoid breaking database
    // uniqueness constraint.
    $transaction = $DB->start_delegated_transaction();
    // CHANGED.
    foreach ($movedsections as $id => $movedsection) {
        $position = $movedsection->section;
        if ($sections[$id]->section !== $position) {
            $DB->set_field('course_sections', 'section', -$position, array('id' => $id));
        }
    }
    foreach ($movedsections as $id => $movedsection) {
        $position = $movedsection->section;
        if ($sections[$id]->section !== $position) {
            $DB->set_field('course_sections', 'section', $position, array('id' => $id));
        }
    }
    // END CHANGED.

    // If we move the highlighted section itself, then just highlight the destination.
    // Adjust the higlighted section location if we move something over it either direction.
    // REMOVED.

    $transaction->allow_commit();
    rebuild_course_cache($course->id, true);

    // ADDED.
    // Set properties for moved sections.
    foreach ($movedsections as $id => $movedsection) {
        // Find differences between original section and moved section, and store as updates.
        $updates = [];
        if ($sections[$id]->level !== $movedsection->level) {
            $updates['level'] = $movedsection->level;
        }
        if ($sections[$id]->visible !== $movedsection->visible) {
            $updates['visible'] = $movedsection->visible;
        }
        // Set page-level sections to untimed.
        if ($movedsection->level < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC && $sections[$id]->periodduration != '0 days') {
            $updates['periodduration'] = '0 days';
        }
        // Apply section updates.
        if ($updates) {
            course_update_section($course, $movedsection, $updates);
        }
    }
    // END ADDED.

    return;                                                                     // CHANGED.
}


/**
 * Checks if the current user can delete a section (if course format allows it and user has proper permissions).
 *
 * CHANGED: Pass section info through to corresponding course format function.
 *
 * @param stdClass $course
 * @param section_info $section The section to check.  Must specify section (number).  Should specify calculated properties.
 * @return bool
 */
function format_multitopic_course_can_delete_section(\stdClass $course, \section_info $section) : bool {
    // CHANGED LINE ABOVE.
    // REMOVED: extract number from section parameter.
    if (!$section->section) {                                                   // CHANGED: Check inside section info.
        // Not possible to delete 0-section.
        return false;
    }
    // Course format should allow to delete sections.
    if (!course_get_format($course)->can_delete_section($section)) {
        return false;
    }
    // Make sure user has capability to update course and move sections.
    $context = \context_course::instance(is_object($course) ? $course->id : $course);
    if (!has_all_capabilities(array('moodle/course:movesections', 'moodle/course:update'), $context)) {
        return false;
    }
    // Make sure user has capability to delete each activity in this section.
    $modinfo = get_fast_modinfo($course);
    if (!empty($modinfo->sections[$section->section])) {                        // CHANGED.
        foreach ($modinfo->sections[$section->section] as $cmid) {              // CHANGED.
            if (!has_capability('moodle/course:manageactivities', \context_module::instance($cmid))) {
                return false;
            }
        }
    }
    return true;
}


/**
 * Reordering algorithm for course sections. Given an array of sections indexed by section->id,
 * an origin, and a target, rebuilds the array.
 *
 * @param array $sections The list of sections.  Must specify fmt calculated properties.
 * @param stdClass $origin The section to be moved.  Must specify id.
 * @param stdClass $target The destination.  Must specify parentid, prevupid, or nextupid.  May specify level.
 * @return array
 */
function format_multitopic_reorder_sections(array $sections, \stdClass $origin, \stdClass $target) : array {
    // CHANGED THROUGHOUT: Section numbers changed to IDs, used exceptions instead of returning false.
    // Reads Calculated section values (levelsan, visiblesan).
    // Writes raw section values (level, visible).
    if (!is_array($sections)) {
        throw new \moodle_exception('cannotcreateorfindstructs');
    }

    // We can't move section position 0.
    if (isset($origin->section) && $origin->section < 1) {
        throw new \moodle_exception('cannotcreateorfindstructs');
    }

    // Locate origin section in sections array.
    if (!($origin = array_key_exists($origin->id, $sections) ? $sections[$origin->id] : null)) {
        throw new \moodle_exception('sectionnotexist');
    }

    // Extract origin sections.
    $originarray = [];
    for ($originsubkey = $origin->id;
        $originsubkey == $origin->id || $originsubkey && $sections[$originsubkey]->levelsan > $origin->levelsan;
        $originsubkey = $originarray[$originsubkey]->nextanyid) {
        $originarray[$originsubkey] = $sections[$originsubkey];
        unset($sections[$originsubkey]);
    }

    // Find target position and extract remaining sections.
    $target->level = $target->level ?? $origin->levelsan;
    $parent = null;
    $prev = null;
    $found = false;
    $appendarray = array();
    foreach ($sections as $id => $section) {
        if ($found) {
            // Target position already found, extract remaining sections.
            $appendarray[$id] = $section;
            unset($sections[$id]);
        } else if (isset($target->parentid) && $section->id == $target->parentid) {
            // Reached the target parent section, remember it.
            $parent = $section;
            if ($target->level <= $parent->levelsan) {
                // The moved section can not be a child of the specified parent.
                throw new \moodle_exception('cannotcreateorfindstructs');
            }
        } else if (isset($target->prevupid) && $section->id == $target->prevupid) {
            // Reached the target previous section, remember it.
            $prev = $section;
            if ($target->level < $prev->levelsan) {
                // The moved section can not have the specified section as its previous.
                throw new \moodle_exception('cannotcreateorfindstructs');
            }
        } else if (isset($parent) && ($section->levelsan < $target->level || $section->levelsan <= $parent->levelsan)
                    || isset($prev) && ($section->levelsan <= $target->level)
                    || isset($target->nextupid) && $section->id == $target->nextupid) {
            // Reached the last position in a specified parent in which the moved section would be a (direct) child,
            // or the appropriate position after a specified previous section,
            // or the position before a specified next section.
            if ($section->levelsan > $target->level) {
                // If inserted here, the moved section would absorb other sections.
                throw new \moodle_exception('cannotcreateorfindstructs');
            }
            $appendarray[$id] = $section;
            unset($sections[$id]);
            $found = true;
        }
    }
    if (isset($parent) || isset($prev)) {
        // If a specified parent or previous was found, but no position within the section list was appropriate,
        // the appropriate position must be the end of the section list.
        $found = true;
    }
    if (!$found) {
        throw new \moodle_exception('sectionnotexist');
    }

    // Clone pre-target sections (to avoid cross-linking),
    // and check if the target location's parent is visible.
    $parentvisible = true;
    if (true) {
        foreach ($sections as $id => $section) {
            $sections[$id] = new \stdClass;
            $sections[$id]->id = $id;
            $sections[$id]->visible = $section->visiblesan;
            $sections[$id]->level = $section->levelsan;
            if ($section->levelsan < $target->level) {
                $parentvisible = $section->visiblesan;
            }
        }
    }

    // Append moved sections.
    $levelchange = $target->level - $origin->levelsan;
    foreach ($originarray as $id => $section) {
        $sections[$id] = new \stdClass;
        $sections[$id]->id = $id;
        $sections[$id]->visible = $section->visible && $parentvisible;
        $sections[$id]->level = ($id == $origin->id) ?
                                $target->level
                                : ($section->levelsan >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC ?
                                    FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC       // Don't change topic level.
                                    : $section->levelsan + $levelchange);
    }

    // Append rest of array.
    if (true) {                                                                 // CHANGED: Don't need to check for empty array?
        foreach ($appendarray as $id => $section) {
            $sections[$id] = new \stdClass;
            $sections[$id]->id = $id;
            $sections[$id]->visible = $section->visiblesan;
            $sections[$id]->level = $section->levelsan;
        }
    }

    // Renumber positions.
    $position = 0;
    foreach ($sections as $section) {
        $section->section = $position;
        $position++;
    }

    return $sections;

}


// ADDED.


/**
 * Generate attribution string from info
 *
 * @param string|null $imagename
 * @param string|null $authorwithurl
 * @param string|null $licencecode
 * @return string
 */
function format_multitopic_image_attribution($imagename, $authorwithurl, $licencecode) : string {
    $o = '';
    $authorwithurlarray = explode('|', $authorwithurl);
    $authorhtml         = $authorwithurlarray[0];
    if (count($authorwithurlarray) > 1) {
        $authorurl  = $authorwithurlarray[1];
        $authorhtml = \html_writer::tag('a', $authorhtml, ['href' => $authorurl, 'target' => '_blank']);
    }
    $licencehtml = ($licencecode && $licencecode != 'unknown') ? get_string($licencecode, 'license') : '';
    if ($licencehtml && substr($licencecode, 0 , 3) == 'cc-') { // TODO: Links to other licences? Make this into a list?
        $licenceurl = 'https://creativecommons.org/licenses/by-' . substr($licencecode, 3, 5) . '/4.0';
        $licencehtml = \html_writer::tag('a', $licencehtml, ['href' => $licenceurl, 'target' => '_blank']);
    }
    $o .= \html_writer::tag('span', get_string('image', 'format_multitopic') . ": {$imagename}"
                            . (($authorhtml || $licencehtml) ? ',' : ''),
                            ['style' => 'white-space: nowrap;']) . ' ';
    if ($authorhtml) {
        $o .= \html_writer::tag('span', get_string('image_by', 'format_multitopic') . " {$authorhtml}"
                                . ($licencehtml ? ',' : ''),
                                ['style' => 'white-space: nowrap;']) . ' ';
    }
    if ($licencehtml) {
        $o .= \html_writer::tag('span', get_string('image_licence', 'format_multitopic') . " {$licencehtml}",
                                ['style' => 'white-space: nowrap;']);
    }
    return $o;
}