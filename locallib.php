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
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 1999 Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\exception\moodle_exception;
use core\lang_string;
use core\output\html_writer;


/**
 * Checks if the current user can delete a section (if course format allows it and user has proper permissions).
 *
 * CHANGED: Pass section info through to corresponding course format function.
 *
 * @deprecated since Moodle 5.3 MDL-86884
 * @param stdClass $course
 * @param section_info $section The section to check.  Must specify section (number).  Should specify calculated properties.
 * @return bool
 */
function format_multitopic_course_can_delete_section(\stdClass $course, \section_info $section): bool {
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
    if (!has_all_capabilities(['moodle/course:movesections', 'moodle/course:update'], $context)) {
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


// ADDED.


/**
 * Generate attribution string from info
 *
 * @param string|null $imagename
 * @param string|null $authorwithurl
 * @param string|null $licencecode
 * @return string
 */
function format_multitopic_image_attribution($imagename, $authorwithurl, $licencecode): string {
    global $CFG;
    require_once($CFG->libdir . '/licenselib.php');

    $o = '';
    $authorwithurlarray = explode('|', $authorwithurl ?? '');
    $authorhtml = $authorwithurlarray[0];
    if (count($authorwithurlarray) > 1) {
        $authorurl = $authorwithurlarray[1];
        $authorhtml = html_writer::tag('a', $authorhtml, ['href' => $authorurl, 'target' => '_blank']);
    }
    $licence = license_manager::get_licenses()[$licencecode] ?? null;
    $licencehtml = ($licencecode && ($licencecode != 'unknown') && $licence) ? $licence->fullname : '';
    if ($licencehtml && $licence->source) {
        $licencehtml = html_writer::tag('a', $licencehtml, ['href' => $licence->source, 'target' => '_blank']);
    }
    $o .= html_writer::tag(
        'span',
        get_string('image', 'format_multitopic') . ": {$imagename}" . (($authorhtml || $licencehtml) ? ',' : ''),
        ['style' => 'white-space: nowrap;']
    ) . ' ';
    if ($authorhtml) {
        $o .= html_writer::tag(
            'span',
            get_string('image_by', 'format_multitopic') . " {$authorhtml}" . ($licencehtml ? ',' : ''),
            ['style' => 'white-space: nowrap;']
        ) . ' ';
    }
    if ($licencehtml) {
        $o .= html_writer::tag(
            'span',
            get_string('image_licence', 'format_multitopic') . " {$licencehtml}",
            ['style' => 'white-space: nowrap;']
        );
    }
    return $o;
}


/**
 * Convert duration string to days.
 * Note: Doesn't handle months or years correctly.
 *
 * @param string|null $duration
 * @return int|null
 */
function format_multitopic_duration_as_days($duration) {
    $days = null;
    $matchok = preg_match('/^([0-9]+) (day|week|month|year)(s)?$/', $duration ?? '', $matches);
    if ($matchok) {
        $match1 = (int)$matches[1];
        switch ($matches[2]) {
            case 'day':
                $days = $match1 * 1;
                break;
            case 'week':
                $days = $match1 * 7;
                break;
            case 'month':
                $days = $match1 * 30;
                break;
            case 'year':
                $days = $match1 * 365;
                break;
            default:
                $days = null;
        }
    } else {
        $days = null;
    }
    return $days;
}


/**
 * Get week date.
 *
 * @param int $date Unix timestamp for date.
 * @return stdClass Week date.
 */
function format_multitopic_week_date($date) {

    $config = get_config('format_multitopic');

    $mstartwday = $config->startwday;       // Starting week day, 0 = Sunday.
    $wmd = $config->weeks_mindays;          // First week of year contains a minimum of how many days of that year, 1-7.
    $weekspartial = $config->weeks_partial; // Partial weeks.

    $dow = (date('w', $date) - $mstartwday + 7) % 7 + 1;                // Day of week, 1 = starting week day.
    $down = new lang_string(strtolower(date('D', $date)), 'calendar');  // Day of week name.

    $y = date('Y', $date);                      // Year.
    $doy = date('z', $date) + 1;                // Day of year, 1 = Jan 1.
    $woy = intdiv(14 - $wmd + $doy - $dow, 7);  // Week of year, 1 = first.

    if (!$weekspartial) {
        if ($woy < 1) { // Last week of previous year.
            $y = date('Y', $date - 7 * 24 * 60 * 60);
            $doy = date('z', $date - 7 * 24 * 60 * 60) + 7 + 1;
            $woy = intdiv(14 - $wmd + $doy - $dow, 7);
        } else if (($ny = date('Y', $date + 7 * 24 * 60 * 60)) > $y) {    // Maybe first week of next year.
            $dony = date('z', $date + 7 * 24 * 60 * 60) - 7 + 1;
            $wony = intdiv(14 - $wmd + $dony - $dow, 7);
            if ($wony >= 1) {
                $y = $ny;
                $doy = $dony;
                $woy = $wony;
            }
        }
    }

    $result = new \stdClass();
    $result->o = $y;                                    // Year.
    $result->W = str_pad($woy, 2, "0", STR_PAD_LEFT);   // Week of year.
    $result->N = $dow;                                  // Day of week (number).
    $result->D = $down;                                 // Day of week (name).
    return $result;
}
