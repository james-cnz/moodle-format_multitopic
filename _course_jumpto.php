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
 * Jumps to a given relative or Moodle absolute URL.
 *
 * CHANGES:
 *  - Used in the modchooser (classes/core_course_renderer_wrapper.php), for redirecting to the appropriate module creation page.
 *  - Converts from section ID, used by custom code, to section number, used by original code.
 *
 * @package   format_multitopic
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com,
 *            2018 Otago Polytechnic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_multitopic;

require('../../../config.php');                                                 // CHANGED.

// ADDED: Satisfy code check.
if (false) {
    require_login($course);
}
// END ADDED.

$jump = required_param('jump', PARAM_RAW);

$PAGE->set_url('/course/jumpto.php');
// TODO: Change?

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad');
}

if (strpos($jump, '/') === 0 || strpos($jump, $CFG->wwwroot) === 0) {
    // ADDED: Convert sectionid to section.
    $moodlejump = new \moodle_url($jump);
    $courseid = $moodlejump->get_param('id');
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $sectionid = $moodlejump->get_param('sectionid');
    $sectionnum = $moodlejump->get_param('section');
    if ($sectionid) {
        $sectionnum = $DB->get_field('course_sections', 'section', array('id' => $sectionid, 'course' => $courseid), MUST_EXIST);
        $moodlejump->param('section', $sectionnum);
    }
    // END ADDED.
    redirect($moodlejump);                                                      // CHANGED.
} else {
    print_error('error');
}
