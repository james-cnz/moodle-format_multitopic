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
 * Strings for component Multitopic course format.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 1999 onwards Martin Dougiamas  {@link http://moodle.com},
 * @copyright based on work by 2012 David Herney Bernal - cirano,
 * @copyright based on work by 2014 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// CHANGED.
$string['addsectionpage'] = 'Add page';
$string['addsectiontopic'] = 'Add topic';
$string['currentsection'] = 'This section';
// REMOVED 'editsection' .
// REMOVED 'editsectionname' .
// REMOVED 'deletesection' .
// REMOVED 'newsectionname' .
$string['sectionname'] = 'Section';
$string['pluginname'] = 'Multitopic format';
$string['section0name'] = 'General';
$string['page-course-view-multitopic'] = 'Any course main page in Multitopic format';
$string['page-course-view-multitopic-x'] = 'Any course page in Multitopic format';
$string['hidefromothers'] = 'Hide section';
$string['showfromothers'] = 'Show section';
$string['privacy:metadata'] = 'The Multitopic format plugin does not store any personal data.';
// END CHANGED.

// INCLUDED /lang/en/moodle.php $string['topicoutline'] CHANGED.
$string['sectionoutline'] = 'Section outline';
// END INCLUDED.

// INCLUDED /course/format/onetopic/lang/en/format_onetopic.php $string['level'] - $string['level_help'] CHANGED.
$string['level'] = 'Level';
// REMOVED 'index' .
$string['asprincipal'] = 'First-level page';
// REMOVED 'asbrother' .
$string['aschild'] = 'Second-level page';
$string['level_help'] = 'Set the section level.
 This is an advanced setting.
 Where possible, it is recommended to use page "Edit" menu options "Raise page level" and "Lower page level" instead.';
// END INCLUDED.

// INCLUDED /course/format/periods/lang/en/format_periods.php $string['perioddurationdefault'] - $string['perioddurationoverride_help'] CHANGED.
$string['perioddurationdefault'] = 'Topic duration';
$string['perioddurationoverride'] = 'Topic duration override';
$string['perioddurationdefault_help'] = 'Set the default duration for topics to have dates shown.
 e.g. A setting of "1 week" would be like the Weekly course format.
 Set to "Unspecified" to not have dates shown, like the Topics course format.';
$string['perioddurationoverride_help'] = 'The duration of this topic.
 Set to "No time" for, e.g., assignments that students are to complete while working on other topics.
 (Not applicable to pages.)';
// END INCLUDED.

// ADDED.
$string['activityclipboard_disable']    = 'Disable activity clipboard';
$string['activityclipboard_enable']     = 'Enable activity clipboard';
$string['activityclipboard_placeholder'] = 'Click the up/down arrows next to an activity to move it to the clipboard.';

$string['back_to_course']   = 'Back to course';

$string['bannerslice']      = 'Banner slice';
$string['bannerslice_help'] = 'The slice of the course summary image to use in the course banner.
 e.g.  Set to "0%" to use the top of the course summary image in the course banner, "50%" to use the middle, or "100%" to use the bottom.';

$string['collapsibledefault']      = 'Collapsible topics';
$string['collapsibledefault_help'] = 'Whether topics are collapsible by default.';
$string['collapsibleoverride']      = 'Collapsible topic override';
$string['collapsibleoverride_help'] = 'Whether this topic is collapsible.
 (Not applicable to pages.)';

$string['image']            = 'Image';
$string['image_by']         = 'by';
$string['image_licence']    = 'licence';

$string['move_level_down']   = 'Lower page level';
$string['move_level_up']     = 'Raise page level';
$string['move_page_next']    = 'Move page right';
$string['move_page_prev']    = 'Move page left';
$string['move_to_next_page'] = 'Move to next page';
$string['move_to_prev_page'] = 'Move to previous page';

$string['period_0_days']    = 'No time';
$string['period_undefined'] = 'Unspecified';
$string['weeks_capitalised'] = 'Weeks';
$string['weeks_mindays']    = 'First week minimum days of year';
$string['weeks_mindays_desc'] = 'The first week of the year contains a minimum of how many days of the year?';
$string['weeks_partial']    = 'Partial weeks';
$string['weeks_partial_desc'] = 'Whether there are partial weeks at the start and end of the year.';
// END ADDED.
