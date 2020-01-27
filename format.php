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
 * Multitopic course format.  Display the course as pages of topics made of modules.
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2006 The Open University
 * @author    based on work by N.D.Freear@open.ac.uk and others.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once('locallib.php');                                               // ADDED.

// Horrible backwards compatible parameter aliasing..
// REMOVED.

$context = \context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

// REMOVED set course marker.

// ADDED.

$disableajax = optional_param('onetopic_da', -1, PARAM_INT);
// INCLUDED LINE ABOVE from /course/format/onetopic/format.php $disableajax .
$hideid      = optional_param('hideid', null, PARAM_INT);
$showid      = optional_param('showid', null, PARAM_INT);
// ADDED instead: Specify destination via relationship to another section (identified by ID).
$destparentid = optional_param('destparentid', null, PARAM_INT);
$destprevupid = optional_param('destprevupid', null, PARAM_INT);
$destnextupid = optional_param('destnextupid', null, PARAM_INT);
$destlevel    = optional_param('destlevel', null, PARAM_INT);
// END ADDED.

// ADDED.
$hide = null;
if (isset($hideid)) {
    $hide = new \stdClass();
    $hide->id = $hideid;
}
$show = null;
if (isset($showid)) {
    $show = new \stdClass();
    $show->id = $showid;
}
$dest = null;
if (isset($destparentid) || isset($destprevupid) || isset($destnextupid)) {
    $dest = new \stdClass();
    if (isset($destparentid)) {
        $dest->parentid = $destparentid;
    }
    if (isset($destprevupid)) {
        $dest->prevupid = $destprevupid;
    }
    if (isset($destnextupid)) {
        $dest->nextupid = $destnextupid;
    }
    if (isset($destlevel)) {
        $dest->level = $destlevel;
    }
}
// END ADDED.

// Sectionid should get priority over section number.
// CHANGED.
if ($sectionid) {
    $sectioninfo = new stdClass();
    $sectioninfo->id = $sectionid;
    // NOTE: This parameter is changed from number to ID, in renderer.php for view pages, and here for edit pages.
    // $urlparams['sectionid'] = $section->id;
}
// END CHANGED.

// $PAGE->set_url('/course/view.php', $urlparams); // Defined here to avoid notices on errors etc.

if ($PAGE->user_allowed_editing()) {

    // INCLUDED /course/format/onetopic/format.php $disableajax .
    if (!isset($USER->onetopic_da)) {
        $USER->onetopic_da = array();
    }
    if ($disableajax !== -1) {
        $USER->onetopic_da[$course->id] = $disableajax ? true : false;
        //redirect($PAGE->url);
    }
    // END INCLUDED.

    if (has_capability('moodle/course:sectionvisibility', $context)) {
        // CHANGED: Call custom functions, pass section info.
        if ($hide && confirm_sesskey()) {
            format_multitopic_set_section_visible($course->id, $hide, 0);
            //redirect($PAGE->url);
        }

        if ($show && confirm_sesskey()) {
            format_multitopic_set_section_visible($course->id, $show, 1);
            //redirect($PAGE->url);
        }
        // END CHANGED.
    }

    if (isset($sectioninfo) && !empty($dest) &&
            has_capability('moodle/course:movesections', $context) &&
            (has_capability('moodle/course:update', $context) || !isset($dest->level)) &&
            confirm_sesskey()) {                                            // CHANGED: Check update capability on level change.
        $destsection = $dest;                                               // CHANGED: Use section info with ID instead of num.
        try {                                                               // CHANGED: Use try/catch instead of return false.
            format_multitopic_move_section_to($course, $sectioninfo, $destsection, false);
            //if ($course->id == SITEID) {
            //    redirect($CFG->wwwroot . '/?redirect=0');
            //} else {
            //    redirect(course_get_url($course, $sectioninfo));                // CHANGED: Return to the moved section.
            //}
        } catch (moodle_exception $e) {                                     // CHANGED: Use returned error message.
            echo $OUTPUT->notification($e->getMessage());
        }
    }

}

// END ADDED.

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_multitopic');

if (false) {                                                                    // CHANGED: Always use multi-section page.
    $renderer->print_single_section_page($course, null, null, null, null, isset($sectioninfo) ? $sectioninfo : $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null, isset($sectioninfo) ? $sectioninfo : $displaysection); // CHANGED: Pass display section.
}

// Include course format js module.
$PAGE->requires->js('/course/format/multitopic/format.js');
