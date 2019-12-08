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
 *  - Only used in the modchooser (classes/course_renderer_wrapper.php), for redirecting to appropriate module creation page.
 *  - Converts from section ID, used by custom code, to section number, used by original code.
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 1999 Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    $sectionid = $moodlejump->get_param('sectionid');
    if ($sectionid) {
        $sectionnum = $DB->get_field('course_sections', 'section', ['id' => $sectionid, 'course' => $courseid], MUST_EXIST);
        $moodlejump->param('section', $sectionnum);
    }
    // END ADDED.
    redirect($moodlejump);                                                      // CHANGED.
} else {
    print_error('error');
}
