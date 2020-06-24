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
 * This file contains main class for the course format Multitopic
 *
 * @since     Moodle 2.0
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2009 Sam Hemelryk,
 *            2012 David Herney Bernal - cirano,
 *            2014 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');
// ADDED.
require_once(__DIR__ . '/classes/global_navigation_wrapper.php');
require_once(__DIR__ . '/classes/courseheader.php');
require_once(__DIR__ . '/classes/coursecontentheaderfooter.php');
// END ADDED.

// ADDED.
/** @var int The level of the General section, which represents the course as a whole.
 * Set to -1, to be a level above the top-level sections in OneTopic format, which are numbered 0.
 * NOTE: Not sure this can be changed without breaking stuff.
 */
const FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT    = -1;

/** @var int Deepest level of page to let users create.  Must be between the root level and the topic level. */
const FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE = 1;

/** @var int Level of topics, which are displayed within pages.
 * NOTE: This could have been made larger, to allow more page levels, but more page levels seemed too confusing.
 */
const FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC   = 2;
// END ADDED.

/**
 * Main class for the Multitopic course format
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2012 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_multitopic extends format_base {

    /** @var int ID of section 0 / the General section, treated as the section root by the Multitopic format */
    public $fmtrootsectionid;

    /**
     * Creates a new instance of class
     *
     * Please use see course_get_format() to get an instance of the format class
     *
     * @param string $format
     * @param int $courseid
     * @return format_base
     */
    protected function __construct($format, $courseid) {
        global $DB;
        parent::__construct($format, $courseid);
        if ($courseid) {
            $this->fmtrootsectionid = $DB->get_field('course_sections', 'id', ['section' => 0, 'course' => $courseid]);
            // TODO: Check if this is set correctly for new courses?
        }
    }

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() : bool {
        return true;
    }

    // INCLUDED /course/format/lib function get_sections .
    /**
     * Returns a list of sections used in the course.
     *
     * CHANGED: Indexed by ID, with calculated properties:
     * - levelsan:          Sanatised section level
     * - parentid:          ID of section's parent (previous section at a higher level)
     * - prevupid:          ID of the previous section at the same or higher level
     * - prevpageid:        ID of the previous section above topic level
     * - prevanyid:         ID of the previous section at any level
     * - nextupid:          ID of the next section at the same or higher level
     * - nextpageid:        ID of the next section above topic level
     * - nextanyid:         ID of the next section at any level
     * - hassubsections:    Whether this section has subsections
     * - pagedepth:         The lowest level of subpages
     * - pagedepthdirect:   The lowest level of direct subpages
     * - parentvisiblesan:  Sanatised parent's visibility
     * - visiblesan:        Sanatised visibility
     * - uservisiblesan:    Sanatised uservisibility
     * - datestart:         Section's start date
     * - dateend:           Section's end date
     * - currentnestedlevel: The level down to which this section contains the current section.
     *                      A page-level section may be represented as multiple levels of tabs,
     *                      and higher levels may contain the current section, while lower levels don't.
     * - fmtdata:           Flag to indicate the presence of calculated properties
     *
     * @return array of section_info objects
     */
    public final function fmt_get_sections() : array {
        // CHANGED LINE ABOVE.
        // CHANGED: Get info, but don't return it yet.
        if ($course = $this->get_course()) {
            $modinfo = get_fast_modinfo($course);
            $sections = $modinfo->get_section_info_all();
        } else {
            return array();
        }
        // END CHANGED.

        // ADDED.

        $timenow = time();

        $courseperioddays = null;
        switch($course->periodduration) {
            case '1 day':
                $courseperioddays = 1;
                break;
            case '1 week':
                $courseperioddays = 7;
                break;
            default:
                $courseperioddays = null;
        }

        // Forward pass.

        // Generated list of sections.
        $fmtsections = [];

        // The previous section at, or above, each level.
        $sectionprevatlevel = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
                                         FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1, null);

        // The current section at, or above, each level.
        $sectionatlevel = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
                                     FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1, null);

        foreach ($sections as $thissection) {

            // Check section number is not negative.
            if ($thissection->section < 0) {
                throw new moodle_exception('cannotcreateorfindstructs');
            }

            // Add this section the the list.
            $fmtsections[$thissection->id] = $thissection;

            // Fix the section's level within appropriate bounds.
            $levelsan = ($sectionatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT] == null) ?
                        FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT
                        : max(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1,
                          min($thissection->level ?? FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC, FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC));
            $thissection->levelsan = $levelsan;

            // Update remembered sections.
            for ($sublevel = $levelsan; $sublevel <= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $sublevel++) {
                $sectionprevatlevel[$sublevel] = $sectionatlevel[$sublevel];
                $sectionatlevel[$sublevel] = $thissection;
            }

            // The previous section at or above this section's level.
            $thissection->prevupid = $sectionprevatlevel[$levelsan] ? $sectionprevatlevel[$levelsan]->id : null;

            // The previous page.
            $thissection->prevpageid = $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1] ?
                                            $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1]->id
                                            : null;

            // The previous section at any level.
            $thissection->prevanyid = $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC] ?
                                            $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC]->id
                                            : null;

            // The section's parent.
            $thissection->parentid = ($levelsan > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) ? $sectionatlevel[$levelsan - 1]->id : null;

            // Initialise tree-related properties to be set in the reverse pass.
            $thissection->hassubsections = false;   // Whether this section has any subsections (page or topic).
            $thissection->pagedepth     = $levelsan;   // The lowest level of all sub-pages.
            $thissection->pagedepthdirect = $levelsan; // The lowest level of direct sub-pages.

            // Set visibility properties.
            $thissection->parentvisiblesan  = ($levelsan <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) ?
                                                true
                                                : $sectionatlevel[$levelsan - 1]->visiblesan;
            $thissection->visiblesan        = ($levelsan <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) ?
                                                true
                                                : ($sectionatlevel[$levelsan - 1]->visiblesan && $thissection->visible);
            $thissection->uservisiblesan    = ($levelsan <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) ?
                                                true
                                                : ($sectionatlevel[$levelsan - 1]->uservisiblesan && $thissection->uservisible);

            // Set date-start property from previous section.
            $thissection->datestart = $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC] ?
                                            $sectionprevatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC]->dateend
                                            : $course->startdate;

            // Set date-end property.
            if ($levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
                $sectionperioddays = 0;
            } else {
                switch($thissection->periodduration) {
                    case '0 days':
                        $sectionperioddays = 0;
                        break;
                    default:
                        $sectionperioddays = $courseperioddays;
                }
            }
            $thissection->dateend = (is_null($thissection->datestart) || is_null($sectionperioddays)) ?
                                        null
                                        : ($thissection->datestart + $sectionperioddays * 24 * 60 * 60);

            // The level down to which this section contains the current section.
            // Initialise for reverse pass.
            $iscurrent = $thissection->dateend
                        && ($thissection->datestart <= $timenow) && ($timenow < $thissection->dateend);
            $thissection->currentnestedlevel = $iscurrent ? FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC
                                                          : FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT - 1;

        }

        // Reverse pass.

        // Remembered sections.
        $sectionnextatlevel = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
                                         FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1, null);

        for ($thissection = $sectionatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC];
                $thissection;
                $thissection = $thissection->prevanyid ? $fmtsections[$thissection->prevanyid] : null) {
            $levelsan = $thissection->levelsan;

            // Tree properties from next sections.
            $thissection->nextupid  = $sectionnextatlevel[$levelsan] ?
                                            $sectionnextatlevel[$levelsan]->id
                                            : null;
            $thissection->nextpageid = $sectionnextatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1] ?
                                            $sectionnextatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1]->id
                                            : null;
            $thissection->nextanyid = $sectionnextatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC] ?
                                            $sectionnextatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC]->id
                                            : null;

            // Flag to indicate the presence of calculated properties.
            $thissection->fmtdata = true;

            // Parent's tree properties.
            if ($thissection->parentid) {
                $parent = $fmtsections[$thissection->parentid];
                $parent->hassubsections = true;
                if ($levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
                    $parent->pagedepth = max($parent->pagedepth, $thissection->pagedepth);
                    $parent->pagedepthdirect = max($parent->pagedepthdirect, $levelsan);
                }
                if ($thissection->currentnestedlevel >= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) {
                    $parent->currentnestedlevel = max($parent->currentnestedlevel, $levelsan - 1);
                }
            }

            // Update remembered next sections.
            for ($sublevel = $levelsan; $sublevel <= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $sublevel++) {
                $sectionnextatlevel[$sublevel] = $thissection;
            }

        }

        return $fmtsections;

        // END ADDED.

    }

    /**
     * Returns information about section used in course.
     *
     * NOTE: If passed section info with calculated properties already in place, they will be returned as is.
     * see fmt_get_sections() for details of calculated properties.
     *
     * @param int|stdClass $section either section number (field course_section.section) or row from course_section table
     * @param int $strictness
     * @return section_info
     */
    public final function fmt_get_section($section, int $strictness = IGNORE_MISSING) {
        // CHANGED: Convert from section number to section info, rather than the other way around.
        if (is_numeric($section)) {
            $sectionnum = $section;
            $section = new stdClass();
            $section->section = $sectionnum;
        }
        // END CHANGED.
        // ADDED.
        if (isset($section->fmtdata) && $section->fmtdata) {
            return $section;
        }
        // END ADDED.
        // CHANGED.
        $sections = $this->fmt_get_sections();
        if (isset($section->id)) {
            if (array_key_exists($section->id, $sections)) {
                return $sections[$section->id];
            }
        } else if (isset($section->section)) {
            foreach ($sections as $thissection) {
                if ($thissection->section == $section->section) {
                    return $thissection;
                }
            }
        }
        // END CHANGED.
        if ($strictness == MUST_EXIST) {
            throw new moodle_exception('sectionnotexist');
        }
        return null;
    }
    // END INCLUDED.

    // INCLUDED instead /course/format/weeks/lib.php function get_section_name .
    /**
     * Returns the display name of the given section that the course prefers.
     *
     * @param int|stdClass $section Section object from database.  Should specify fmt calculated properties.
     * @return string Display name that the course format prefers, e.g. "Section 2"
     */
    public function get_section_name($section) : string {

        // ADDED.
        // If we don't have calculated data, don't bother fetching it.
        if (!is_object($section) || !isset($section->fmtdata)) {
            // INCLUDED: /course/format/topics/lib.php function get_section_name body .
            $section = $this->get_section($section);
            if ((string)$section->name !== '') {
                return format_string($section->name, true,
                        array('context' => context_course::instance($this->courseid)));
            } else {
                return $this->get_default_section_name($section);
            }
            // END INCLUDED.
        }

        $weekword = new lang_string('week');
        $weeksword = new lang_string('weeks');

        // Figure out the string for the week number.
        $daystring = '';
        if ($section->dateend && ($section->datestart < $section->dateend)) {
            $currentyear = date('o');
            $datestart = $section->datestart + 12 * 60 * 60;
            $dateend = $section->dateend - 12 * 60 * 60;
            if (date('o', $datestart) == date('o', $dateend)) {
                // Within one year.
                $yearstring = date('o', $datestart) != $currentyear ? date('o ', $datestart) : '';
                if (date('o W', $datestart) == date('o W', $dateend)) {
                    // Within one week.
                    $weekstring = $yearstring . $weekword . ' ' . date('W', $datestart);
                    if (date('o W N', $datestart) == date('o W N', $dateend)) {
                        // One day.
                        $daystring = $weekstring . ' ' . date('D', $datestart);
                    } else if ((date('N', $datestart) == '1') && (date('N', $dateend) == '7')) {
                        // Whole week.
                        $daystring = $weekstring;
                    } else {
                        // Partial week.
                        $daystring = $weekstring . ' ' . date('D', $datestart) . '–' . date('D', $dateend);
                    }
                } else if ((date('N', $datestart) == '1') && (date('N', $dateend) == '7')) {
                    // Spans whole weeks.
                    $daystring = $yearstring . $weeksword . ' ' . date('W', $datestart) . '–' . date('W', $dateend);
                } else {
                    // Spans partial weeks.
                    $daystring = $yearstring . $weekword . ' ' . date('W D', $datestart)
                                . '–' . $weekword . ' ' . date('W D', $dateend);
                }
            } else {
                // Spans years.
                $yearstartstring = date('o', $datestart) != $currentyear ? date('o ', $datestart) : '';
                $yearendstring = date('o', $dateend) != $currentyear ? date('o ', $dateend) : '';
                if ((date('N', $datestart) == '1') && (date('N', $dateend) == '7')) {
                    // Spans whole weeks.
                    $daystring = $yearstartstring . $weekword . ' ' . date('W', $datestart)
                            . '–' . $yearendstring . $weekword . ' ' . date('W', $dateend);
                } else {
                    // Spans partial weeks.
                    $daystring = $yearstartstring . $weekword . ' ' . date('W D', $datestart)
                            . '–' . $yearendstring . $weekword . ' ' . date('W D', $dateend);
                }
            }
            $daystring = $daystring . ': ';
        }
        // END ADDED.

        if ((string)$section->name !== '') {
            // Return the name the user set.
            return format_string($daystring . $section->name, true,
                    array('context' => context_course::instance($this->courseid))); // CHANGED.
        } else {
            return $daystring . $this->get_default_section_name($section);
        }
    }
    // END INCLUDED.

    /**
     * Returns the default section name for the multitopic course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) : string {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_multitopic');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *                      Should specify fmt calculated properties,
     *                      specifically levelsan, and parentid where levelsan is topic level.
     * @param array $options options for view URL. At the moment core uses:
     *     'fmtedit' (bool)    if true, return URL for edit page rather than view page
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url( ($options['fmtedit'] ?? false) ? '/course/format/multitopic/_course_view.php'
                                : '/course/view.php', array('id' => $course->id)); // CHANGED.
        // REMOVED section return.
        // REMOVED convert sectioninfo to number.
        if ($section !== null) {                                                // CHANGED.
            $section = $this->fmt_get_section($section, MUST_EXIST);            // ADDED.
            // CHANGED.
            $pageid  = ($section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) ? $section->id : $section->parentid;
            if ($pageid != $this->fmtrootsectionid) {
                $url->param('sectionid', $pageid);
            }
            if ($section->levelsan >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('sectionid-' . $section->id);
            }
            // END CHANGED.
        }
        return $url;
    }

    // INCLUDED instead /course/format/onetopic/lib.php function supports_ajax .
    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() : stdClass {
        global $COURSE, $USER;

        if (!isset($USER->onetopic_da)) {
            $USER->onetopic_da = array();
        }

        if (empty($COURSE)) {
            $disableajax = false;
        } else {
            $disableajax = $USER->onetopic_da[$COURSE->id] ?? false;
        }

        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = !$disableajax;
        return $ajaxsupport;
    }
    // END INCLUDED.

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;

        $navigationwrapper = new \format_multitopic\global_navigation_wrapper($navigation); // ADDED.

        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            // CHANGED.
            $selectedsectionid = optional_param('sectionid', null, PARAM_INT);
            if ($selectedsectionid !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigationwrapper->innerincludesectionid = $selectedsectionid;
            }
            // END CHANGED.
        }

        // Check if there are callbacks to extend course navigation.
        // REMOVED function call.
        // INCLUDED instead /course/format/lib.php function extend_course_navigation body.
        if ($course = $this->get_course()) {
            $navigationwrapper->load_generic_course_sections($course, $node);   // CHANGED: Wrapped navigation object.
        }
        // END INCLUDED.

        // We want to remove the general section if it is empty.
        // REMOVED.
    }

    // INCLUDED instead /course/format/weeks/lib.php function ajax_section_move .
    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() : array {
        global $PAGE;
        $titles = array();
        $current = -1;
        $course = $this->get_course();
        // REMOVED: Replaced $modinfo with fmt_get_sections.
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $this->fmt_get_sections())) {             // CHANGED: Replaced $modinfo with fmt_get_sections.
            foreach ($sections as $section) {
                $titles[$section->section] = $renderer->section_title($section, $course);
                if ($this->is_section_current($section)) {
                    $current = $section->section;
                }
            }
        }
        return array('sectiontitles' => $titles, 'current' => $current, 'action' => 'move');
    }
    // END INCLUDED.

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() : array {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for courses
     *
     * Multitopic format uses the following options:
     * - periodduration (from Periods format): how long each topic takes.  (Only 1 week or null are currently supported.)
     * - hiddensections (from the standard Topics format): whether hidden sections are shown collapsed, or not shown at all.
     * - bannerslice (custom option): how far down the course image to take the banner slice from (0-100).
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) : array {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                // INCLUDED /course/format/periods/lib.php function course_format_options 'periodduration'.
                'periodduration' => array(
                    'default' => null,                                          // CHANGED.
                    'type' => PARAM_NOTAGS
                ),
                // END INCLUDED.
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                // REMOVED: course display.
                // ADDED.
                'bannerslice' => array(
                    'default' => 0,
                    'type' => PARAM_INT,
                ),
                // END ADDED.
            );
        }
        if ($foreditform && !isset($courseformatoptions['hiddensections']['label'])) { // CHANGED.
            $courseformatoptionsedit = array(
                // INCLUDED /course/format/periods/lib.php function course_format_options $foreditform 'periodduration' .
                'periodduration' => array(
                    'label' => new lang_string('perioddurationdefault', 'format_multitopic'), // CHANGED.
                    'help' => 'perioddurationdefault',
                    'help_component' => 'format_multitopic',                    // CHANGED.
                    'element_type' => 'select',                                 // CHANGED.
                    // REMOVED: Replaced periodduration type.
                    // ADDED.
                    'element_attributes' => array(array(
                        null => new lang_string('period_undefined', 'format_multitopic'),
                        '1 week' => new lang_string('numweek', '', 1),
                    )),
                    // END ADDED.
                ),
                // END INCLUDED.
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                // REMOVED: coursedisplay .
                // ADDED.
                'bannerslice' => array(
                    'label' => new lang_string('bannerslice', 'format_multitopic'),
                    'help' => 'bannerslice',
                    'help_component' => 'format_multitopic',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(' 0%', ' 1%', ' 2%', ' 3%', ' 4%', ' 5%', ' 6%', ' 7%', ' 8%', ' 9%',
                              '10%', '11%', '12%', '13%', '14%', '15%', '16%', '17%', '18%', '19%',
                              '20%', '21%', '22%', '23%', '24%', '25%', '26%', '27%', '28%', '29%',
                              '30%', '31%', '32%', '33%', '34%', '35%', '36%', '37%', '38%', '39%',
                              '40%', '41%', '42%', '43%', '44%', '45%', '46%', '47%', '48%', '49%',
                              '50%', '51%', '52%', '53%', '54%', '55%', '56%', '57%', '58%', '59%',
                              '60%', '61%', '62%', '63%', '64%', '65%', '66%', '67%', '68%', '69%',
                              '70%', '71%', '72%', '73%', '74%', '75%', '76%', '77%', '78%', '79%',
                              '80%', '81%', '82%', '83%', '84%', '85%', '86%', '87%', '88%', '89%',
                              '90%', '91%', '92%', '93%', '94%', '95%', '96%', '97%', '98%', '99%',
                              '100%')
                    ),
                ),
                // END ADDED.
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    // INCLUDED course/format/lib.php function section_format_options declaration.
    /**
     * Definitions of the additional options that this course format uses for section
     *
     * See see format_base::course_format_options() for return array definition.
     *
     * Additionally section format options may have property 'cache' set to true
     * if this option needs to be cached in see get_fast_modinfo(). The 'cache' property
     * is recommended to be set only for fields used in see format_base::get_section_name(),
     * see format_base::extend_course_navigation() and see format_base::get_view_url()
     *
     * For better performance cached options are recommended to have 'cachedefault' property
     * Unlike 'default', 'cachedefault' should be static and not access get_config().
     *
     * Regardless of value of 'cache' all options are accessed in the code as
     * $sectioninfo->OPTIONNAME
     * where $sectioninfo is instance of section_info, returned by
     * get_fast_modinfo($course)->get_section_info($sectionnum)
     * or get_fast_modinfo($course)->get_section_info_all()
     *
     * All format options for particular section are returned by calling:
     * $this->get_format_options($section);
     *
     * @param bool $foreditform
     * @return array
     */
    public function section_format_options($foreditform = false) : array {
        // INCLUDED instead /course/format/lib.php function course_format_options body (excluding array items).
        static $sectionformatoptions = false;
        if ($sectionformatoptions === false) {
            $sectionformatoptions = array(
                // INCLUDED /course/format/onetopic/lib.php function section_format_options 'level'.
                'level' => array(
                    'default' => FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC,         // CHANGED.
                    'type' => PARAM_INT
                ),
                // END INCLUDED.
                // INCLUDED /course/format/periods/lib.php function section_format_options 'periodduration'.
                'periodduration' => array(
                    'default' => null,                                          // ADDED.
                    'type' => PARAM_NOTAGS
                ),
                // END INCLUDED.
            );
        }
        if ($foreditform && !isset($sectionformatoptions['level']['label'])) {
            $sectionformatoptionsedit = array(
                // INCLUDED /course/format/onetopic/lib.php function section_format_options $foreditform 'level'.
                'level' => array(
                    // REMOVED: 'default' & 'type'.
                    'label' => get_string('level', 'format_multitopic'),        // CHANGED.
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1 => get_string('asprincipal', 'format_multitopic'), // CHANGED.
                            FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 2 => get_string('aschild', 'format_multitopic'),     // CHANGED.
                            FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC => get_string('topic') // ADDED.
                        )
                    ),
                    'help' => 'level',
                    'help_component' => 'format_multitopic',
                ),
                // END INCLUDED.
                // INCLUDED /course/format/periods/lib.php function section_format_options $foreditform 'periodduration'.
                'periodduration' => array(
                    'label' => new lang_string('perioddurationoverride', 'format_multitopic'), // CHANGED.
                    'help' => 'perioddurationoverride',
                    'help_component' => 'format_multitopic',                    // CHANGED.
                    'element_type' => 'select',                                 // CHANGED.
                    // REMOVED: Changed type.
                    // ADDED.
                    'element_attributes' => array(array(
                        null => new lang_string('default'),
                        '0 days' => new lang_string('period_0_days', 'format_multitopic'),
                    )),
                    // END ADDED.
                ),
                // END INCLUDED.
            );
            $sectionformatoptions = array_merge_recursive($sectionformatoptions, $sectionformatoptionsedit); // CHANGED.
        }
        return $sectionformatoptions;                                           // CHANGED.
        // END INCLUDED.
    }
    // END INCLUDED.

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from see course_edit_form::definition_after_data().
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) : array {
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // REMOVED: numsections .

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * If the course format was changed to 'multitopic', we try to copy options
     * 'periodduration', 'hiddensections', and 'bannerslice' from the previous format.
     *
     * @param stdClass|array $data return value from see moodleform::get_data() or array with data
     * @param stdClass $oldcourse if this function is called from see update_course()
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) : bool {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
            if (!array_key_exists('periodduration', $data) && array_key_exists('layoutstructure', $oldcourse)) {
                if ($oldcourse['layoutstructure'] == 2 || $oldcourse['layoutstructure'] == 3) {
                    $data['periodduration'] = '1 week';
                }
            }
        }
        return $this->update_format_options($data);
    }

    // TODO: Customise editsection_form to sanitise periodduration?

    // INCLUDED /course/format/lib.php function course_header declaration.
    /**
     * Create course header: A banner showing the course name, with a slice of the course image as the background.
     *
     * @return null|renderable
     */
    public function course_header() {
        // REMOVED: Removed empty function body.
        // ADDED.
        return new \format_multitopic\courseheader($this->get_course());
        // END ADDED.
    }
    // END INCLUDED.

    // INCLUDED /course/format/lib.php function course_content_header declaration.
    /**
     * Create course content header when applicable: A "back to course" button.
     *
     * @return renderable|null
     */
    public function course_content_header() {
        global $PAGE;
        // Don't show in manage files popup.  TODO: Better way?
        if (class_exists('format_multitopic_renderer')) {
            return new \format_multitopic\coursecontentheaderfooter($PAGE, -1);
        } else {
            return null;
        }

    }
    // END INCLUDED.

    // INCLUDED /course/format/lib.php function course_content_footer declaration.
    /**
     * Create course content footer when applicable: Another "back to course" button.
     *
     * @return renderable|null
     */
    public function course_content_footer() {
        global $PAGE;
        // Don't show in manage files popup.  TODO: Better way?
        if (class_exists('format_multitopic_renderer')) {
            return new \format_multitopic\coursecontentheaderfooter($PAGE, 1);
        } else {
            return null;
        }

    }
    // END INCLUDED.

    // INCLUDED /course/format/lib.php function is_section_current .
    /**
     * Returns true if the specified section is current
     *
     * @param int|stdClass|section_info $section The section to check.  Should specify fmt calculated properties.
     * @return bool
     */
    public function is_section_current($section) : bool {

        // If we don't have calculated data, don't bother fetching it.
        if (!is_object($section) || !isset($section->fmtdata)) {
            return false;
        }

        return ($section->section && $section->currentnestedlevel >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC); // CHANGED.
    }
    // END INCLUDED.

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use see course_can_delete_section()
     *
     * @param int|stdClass|section_info $section The section to check.
     *                                  Must specify section number or id.  Should specify fmt calculated properties.
     * @return bool
     */
    public function can_delete_section($section) : bool {
        $section = $this->fmt_get_section($section);                            // ADDED.
        return !$section->hassubsections;                                       // CHANGED.
    }

    // TODO: Customise delete_section to be recursive?

    /**
     * Prepares the templateable object to display section name
     *
     * @param section_info|stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                            $editable = null, $edithint = null, $editlabel = null) : \core\output\inplace_editable {
        $section = $this->fmt_get_section($section);                            // ADDED.
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', '', $title);
        }

        // REMOVED function call.
        // INCLUDED instead /course/format/lib.php function inplace_editable_render_section_name body.
        global $USER, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($editable === null) {
            $editable = !empty($USER->editing) && has_capability('moodle/course:update',
                    context_course::instance($section->course));
        }

        $displayvalue = $title = html_writer::tag('i', '', ['class' =>
                                        ($section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC ? 'icon fa fa-folder-o fa-fw'
                                                                                                    : 'icon fa fa-list fa-fw')])
                                    . ' ' . get_section_name($section->course, $section);
        // TODO: Fix collapse icon for AJAX rename, somehow?
        if ($linkifneeded) {
            // Display link under the section name, for collapsible sections.
            $url = course_get_url($section->course, $section, array('navigation' => ($section->periodduration == '0 days'))); // CHANGED.
            if ($url) {
                $displayvalue = html_writer::link($url, $title);
            }
            $itemtype = 'sectionname';
        } else {
            // If $linkifneeded==false, we never display the link (this is used when rendering the section header).
            // Itemtype 'sectionnamenl' (nl=no link) will tell the callback that link should not be rendered -
            // there is no other way callback can know where we display the section name.
            $itemtype = 'sectionnamenl';
        }
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname');
        }
        if (empty($editlabel)) {
            $editlabel = new lang_string('newsectionname', '', $title);
        }

        return new \core\output\inplace_editable('format_' . $this->format, $itemtype, $section->id, $editable,
            $displayvalue, $section->name, $edithint, $editlabel);
        // END INCLUDED.
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() : bool {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) : bool {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide)
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register
     *
     * @param stdClass|section_info $section
     * @param string $action
     * @param int $sr unused
     * @return null|array|stdClass any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr): array {
        global $PAGE;

        // REMOVED: marker.

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, null);                  // CHANGED: removed section return.
        $renderer = $PAGE->get_renderer('format_multitopic');                   // CHANGED.
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() : array {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_multitopic_inplace_editable(string $itemtype, int $itemid, $newvalue) : \core\output\inplace_editable {
    // CHANGED LINE ABOVE.
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'multitopic'), MUST_EXIST);                          // CHANGED.
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
