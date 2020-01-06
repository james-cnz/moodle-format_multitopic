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
 * Renderer for outputting the multitopic course format.
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2012 Dan Poltawski,
 *            2012 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/renderer.php');
// ADDED.
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/classes/course_renderer_wrapper.php');
require_once(__DIR__ . '/classes/courseheader.php');
require_once(__DIR__ . '/classes/coursecontentheaderfooter.php');
// END ADDED.

/**
 * Basic renderer for multitopic format.
 *
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_multitopic_renderer extends format_section_renderer_base {         // CHANGED.

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, string $target) {
        parent::__construct($page, $target);

        // REMOVED: Marker stuff.

        // ADDED.
        // If we're on the view page, patch the URL to use the section ID instead of section number.
        global $PAGE;
        if ($PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
            && ($id = optional_param('id', null, PARAM_INT))) {
            $params = ['id' => $id];
            if ($sectionid = optional_param('sectionid', null, PARAM_INT)) {
                $params['sectionid'] = $sectionid;
            }
            $PAGE->set_url('/course/view.php', $params);
        }
        // END ADDED.

    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() : string {
        return html_writer::start_tag('ul', array('class' => 'sections'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() : string {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() : string {
        return get_string_manager()->string_exists('sectionoutline', 'format_multitopic') ?
                get_string('sectionoutline', 'format_multitopic') : get_string('topicoutline'); // CHANGED.
    }

    /**
     * Generate the section title, wraps it in a link to the section if section is collapsible
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $linkifneeded Whether to add link
     * @return string HTML to output.
     */
    public function section_title($section, $course, bool $linkifneeded = true) : string {
        // CHANGED LINE ABOVE.

        // ADDED.
        $section = course_get_format($course)->fmt_get_section($section);

        // Date range for the topic, to be placed under the title.
        $datestring = '';
        if (isset($section->dateend) && ($section->datestart < $section->dateend)) {

            $dateformat = get_string('strftimedateshort');
            $startday = userdate($section->datestart + 12 * 60 * 60, $dateformat);
            $endday = userdate($section->dateend - 12 * 60 * 60, $dateformat);

            if ($startday == $endday) {
                $datestring = "({$startday})";
            } else {
                $datestring = "({$startday}–{$endday})";
            }

        }
        // END ADDED.

        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, $linkifneeded))
                . html_writer::empty_tag('br')
                . html_writer::tag('span', $datestring, ['class' => 'section_subtitle']); // CHANGED.
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) : string {
        return $this->section_title($section, $course, false);                  // CHANGED.
    }

    // NOTE: Additional $section data passes through function section_right_content.

    // INCLUDED course/format/renderer.php function section_header .
    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action, unused
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) : string {
        // REMOVED: unused global $PAGE.

        $section = course_get_format($course)->fmt_get_section($section);

        $o = '';
        // REMOVED: unused local $currenttext.
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle .= ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle .= ' current';
            }
            // ADDED.
            if (!$section->uservisible) {
                $sectionstyle .= ' section-userhidden';
            }
            // END ADDED.
        }

        // ADDED.
        // Determine the section type.
        if ($section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
            $sectionstyle .= ' section-page';
        } else if ($section->periodduration == '0 days') {
            $sectionstyle .= ' section-topic section-topic-untimed';
        } else {
            $sectionstyle .= ' section-topic section-topic-timed';
        }

        $sectionstyle .= " sectionid-{$section->id}";
        // END ADDED.

        $o .= html_writer::start_tag('li', array('id' => 'section-' . $section->section,
            'class' => 'section main clearfix' . $sectionstyle, 'role' => 'region',
            'aria-label' => get_section_name($course, $section)));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'hidden sectionname'));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        // REMOVED: section title display rules.  Always display the section title.
        if (true) {
            $classes = '';
        }

        $sectionname = html_writer::tag('span', $this->section_title($section, $course, $section->levelsan >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC && $section->uservisible)); // CHANGED.
        $o .= $this->output->heading($sectionname, 3, 'sectionname' . $classes);

        $o .= $this->section_availability($section);

        $o .= html_writer::start_tag('div', array('class' => 'summary'));
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');

        return $o;
    }
    // END INCLUDED.

    // INCLUDED instead /course/format/renderer.php function section_edit_control_items .
    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) : array {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        // REMOVED sectionreturn .
        $section = course_get_format($course)->fmt_get_section($section);       // ADDED.
        $onsectionpage = $section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; // ADDED.

        $coursecontext = context_course::instance($course->id);
        $numsections = course_get_format($course)->get_last_section_number();
        $isstealth = false;                                                     // CHANGED: Don't use numsections.

        $baseurl = course_get_url($course, $section, ['fmtedit' => true]);      // CHANGED.
        $baseurl->param('sesskey', sesskey());

        $controls = array();

        if (!$isstealth && has_capability('moodle/course:update', $coursecontext)) {
            if ($section->section > 0
                && get_string_manager()->string_exists('editsection', 'format_' . $course->format)) {
                $streditsection = get_string('editsection', 'format_' . $course->format);
            } else {
                $streditsection = get_string('editsection');
            }

            $controls['edit'] = array(
                'url'   => new moodle_url('/course/format/multitopic/_course_editsection.php', array('id' => $section->id)), // CHANGED.
                'icon' => 'i/settings',
                'name' => $streditsection,
                'pixattr' => array('class' => ''),
                'attr' => array('class' => 'icon edit'));
        }

        if ($section->section) {
            $url = clone($baseurl);
            if (!$isstealth) {
                if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                    if ($section->visible) { // Show the hide/show eye.
                        $strhidefromothers = get_string_manager()->string_exists('hidefromothers', 'format_' . $course->format) ?
                                                get_string('hidefromothers', 'format_' . $course->format) : get_string('hide'); // CHANGED.
                        $url->param('hideid', $section->id);                    // CHANGED.
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/hide',
                            'name' => $strhidefromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'icon editing_showhide',
                                ));                                             // REMOVED section return & AJAX action .
                        // ADDED: AJAX action added back for topic-level sections only.
                        if (!$onsectionpage) {
                            $controls['visiblity']['attr']['data-action'] = 'hide';
                        }
                        // END ADDED.
                    } else if ($section->parentvisiblesan) {                    // CHANGED: Only allow unhide if parent is visible.
                        $strshowfromothers = get_string_manager()->string_exists('showfromothers', 'format_' . $course->format) ?
                                                get_string('showfromothers', 'format_' . $course->format) : get_string('show'); // CHANGED.
                        $url->param('showid',  $section->id);                   // CHANGED.
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/show',
                            'name' => $strshowfromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'icon editing_showhide',
                                )); // REMOVED section return & AJAX action.
                        // ADDED: AJAX action added back for topic-level sections only.
                        if (!$onsectionpage) {
                            $controls['visiblity']['attr']['data-action'] = 'show';
                        }
                        // END ADDED.
                    }
                }

                // INCLUDED /course/format/renderer.php function section_edit_control_items if (!$onsectionpage) .
                if ($onsectionpage) {                                           // CHANGED.
                    if (has_capability('moodle/course:movesections', $coursecontext)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext)
                        && has_capability('moodle/course:update', $coursecontext)) {
                        $url = clone($baseurl);
                        if ($section->levelsan - 1 > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) { // Raise section. // CHANGED.
                            // CHANGED.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->parentid);
                            $url->param('destlevel', $section->levelsan - 1);
                            $strmovelevelup = get_string_manager()->string_exists('move_level_up', 'format_multitopic') ?
                                                get_string('move_level_up', 'format_multitopic') : get_string('moveup');
                            // END CHANGED.
                            $controls['movelevelup'] = array(                   // CHANGED.
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmovelevelup,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmovelevelup')); // CHANGED.
                        }

                        $url = clone($baseurl);
                        if ($section->pagedepth + 1 <= FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE) { // Lower section. CHANGED.
                            // CHANGED.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->prevupid);
                            $url->param('destlevel', $section->levelsan + 1);
                            $strmoveleveldown = get_string_manager()->string_exists('move_level_down', 'format_multitopic') ?
                                                get_string('move_level_down', 'format_multitopic') : get_string('movedown');
                            // END CHANGED.
                            $controls['moveleveldown'] = array(                 // CHANGED.
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmoveleveldown,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmoveleveldown')); // CHANGED.
                        }
                    }
                    if (has_capability('moodle/course:movesections', $coursecontext)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                        $url = clone($baseurl);
                        // CHANGED: Replaced up with previous.
                        if (isset($section->prevupid) && $section->prevupid != course_get_format($course)->fmtrootsectionid) {
                                // Add a arrow to move section back.
                            $url->param('sectionid', $section->id);
                            $url->param('destnextupid', $section->prevupid);
                            $strmovepageprev = get_string_manager()->string_exists('move_page_prev', 'format_multitopic') ?
                                                get_string('move_page_prev', 'format_multitopic') : get_string('moveleft');
                            $controls['moveprev'] = array(
                                'url' => $url,
                                'icon' => 't/left',
                                'name' => $strmovepageprev,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmovepageprev'));
                        }
                        // END CHANGED.

                        $url = clone($baseurl);
                        // CHANGED: Replaced down with next.
                        if (isset($section->nextupid)) { // Add a arrow to move section forward.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->nextupid);
                            $strmovepagenext = get_string_manager()->string_exists('move_page_next', 'format_multitopic') ?
                                                get_string('move_page_next', 'format_multitopic') : get_string('moveright');
                            $controls['movenext'] = array(
                                'url' => $url,
                                'icon' => 't/right',
                                'name' => $strmovepagenext,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmovepagenext'));
                        }
                        // END CHANGED.
                    }
                } else { // END INCLUDED.
                    // Move sections left and right.
                    if (has_capability('moodle/course:movesections', $coursecontext)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                        $url = clone($baseurl);
                        // CHANGED: Replaced up with to previous page.
                        if ($section->prevpageid) { // Add a arrow to move section to previous page.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->prevpageid);
                            $strmovetoprevpage = get_string_manager()->string_exists('move_to_prev_page', 'format_multitopic') ?
                                                 get_string('move_to_prev_page', 'format_multitopic') : get_string('moveleft');
                            $controls['movetoprevpage'] = array(
                                'url' => $url,
                                'icon' => 't/left',
                                'name' => $strmovetoprevpage,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmovetoprevpage'));
                        }
                        // END CHANGED.

                        $url = clone($baseurl);
                        // CHANGED: Replaced down with to next page.
                        if ($section->nextpageid) { // Add a arrow to move section to next page.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->nextpageid);
                            $strmovetonextpage = get_string_manager()->string_exists('move_to_next_page', 'format_multitopic') ?
                                                 get_string('move_to_next_page', 'format_multitopic') : get_string('moveright');
                            $controls['movetonextpage'] = array(
                                'url' => $url,
                                'icon' => 't/right',
                                'name' => $strmovetonextpage,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon fmtmovetonextpage'));
                        }
                        // END CHANGED.

                    }
                    if (has_capability('moodle/course:movesections', $coursecontext)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                        $url = clone($baseurl);
                        if ($section->section > 1) { // Add a arrow to move section up.
                            $url->param('sectionid', $section->id);
                            $url->param('destnextupid', $section->prevupid);
                            $strmoveup = get_string('moveup');
                            $controls['moveup'] = array(
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmoveup,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon moveup'));
                        }

                        $url = clone($baseurl);
                        if ($section->section < $numsections) { // Add a arrow to move section down.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->nextupid);
                            $strmovedown = get_string('movedown');
                            $controls['movedown'] = array(
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmovedown,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'icon movedown'));
                        }
                    }
                }
            }

            if (\format_multitopic_course_can_delete_section($course, $section)) {
                if (get_string_manager()->string_exists('deletesection', 'format_' . $course->format)) {
                    $strdelete = get_string('deletesection', 'format_' . $course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new moodle_url('/course/format/multitopic/_course_editsection.php', array(
                    'id' => $section->id,
                    // REMOVED: section return.
                    'delete' => 1,
                    'sesskey' => sesskey()));
                $controls['delete'] = array(
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => array('class' => ''),
                    'attr' => array('class' => 'icon editing_delete'));
            }
        }

        return $controls;
    }
    // END INCLUDED.

    // INCLUDED /course/format/renderer.php function section_availability .
    /**
     * Displays availability information for the section (hidden, not available unless, etc.)
     *
     * @param section_info $section
     * @return string
     */
    public function section_availability($section) : string {
        $context = context_course::instance($section->course);
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);

        return html_writer::div($this->section_availability_message($section, $canviewhidden), 'section_availability'); // CHANGED.
    }
    // END INCLUDED.

    // INCLUDED /course/format/renderer.php function course_activity_clipboard .
    /**
     * Show if something is on on the course clipboard (moving around)
     *
     * @param stdClass $course The course entry from DB
     * @param section_info $section The section in the course which is being displayed.  Must specify id and section (number).
     * @return string HTML to output.
     */
    protected function fmt_course_activity_clipboard(stdClass $course, section_info $section) : string {
        global $USER, $PAGE;                                                    // CHANGED: Added $PAGE.

        if (!$PAGE->user_is_editing() && !ismoving($course->id)) {
            return '';
        }

        $context = context_course::instance($course->id);
        $o = '';

        // INCLUDED /course/format/onetopic/renderer.php function print_single_section_page utilities (parts).
        // Output the enable / disable button.
        $disableajax = false;
        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {

            $url = course_get_url($course, $section, ['fmtedit' => true]);
            $url->param('sesskey', sesskey());

            if ($USER->onetopic_da[$course->id] ?? false) {
                $disableajax = true;
                $url->param('onetopic_da', 0);
                $buttontext = get_string_manager()->string_exists('activityclipboard_disable', 'format_multitopic') ?
                                get_string('activityclipboard_disable', 'format_multitopic') : get_string('disable');
            } else {
                $url->param('onetopic_da', 1);
                $buttontext = get_string_manager()->string_exists('activityclipboard_enable', 'format_multitopic') ?
                                get_string('activityclipboard_enable', 'format_multitopic') : get_string('enable');
            }

            // ADDED.
            $button = new single_button($url, $buttontext, 'get');
            $button->disabled = $disableajax && ismoving($course->id);
            $o .= html_writer::tag('div', $this->render($button),
                                    ['class' => 'buttons visibleifjs', 'style' => 'float: right;']); // TODO: Use CSS?
            // END ADDED.
        }
        // END INCLUDED.

        // Output the clipboard itself
        if ($disableajax || ismoving($course->id)) {                            // TODO: Also show when JS disabled?
            $o .= html_writer::start_tag('div', array('class' => 'clipboard'));
            $o .= html_writer::tag('i', '', ['class' => 'icon fa fa-clipboard fa-fw']) . ' ';

            // If currently moving a file then show the current clipboard.
            if (ismoving($course->id)) {
                $url = new moodle_url('/course/mod.php',
                    array('sesskey' => sesskey(),
                        'cancelcopy' => true,
                        // REMOVED section return.
                    )
                );

                $o .= strip_tags(get_string('activityclipboard', '', $USER->activitycopyname));
                $o .= ' ('.html_writer::link($url, get_string('cancel')).')';
            } else {
                $o .= get_string_manager()->string_exists('activityclipboard_placeholder', 'format_' . $course->format) ?
                        '[' . get_string('activityclipboard_placeholder', 'format_' . $course->format) . ']' : '';
            }

            $o .= html_writer::end_tag('div');
        }

        return $o;
    }
    // END INCLUDED.


    // INCLUDED /course/format/renderer.php function print_single_section_page declaration .
    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int|section_info $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        $this->print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection); // ADDED.
    }
    // END INCLUDED.

    // INCLUDED /course/format/renderer.php function print_multiple_section_page .
    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int|section_info $displaysection
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection = 0) {
        // CHANGED ABOVE included displaysection from print_single_section_page
        global $PAGE, $OUTPUT;                                                  // CHANGED: Included output global.

        // REMOVED: Replaced modinfo with fmt_get_sections .
        $course = course_get_format($course)->get_course();

        // ADDED.
        $sections = course_get_format($course)->fmt_get_sections();

        // Find display section.
        if (is_object($displaysection && isset($displaysection->id))) {
            $displaysection = $sections[$displaysection->id] ?? null;
        } else if (is_numeric($displaysection) || isset($displaysection->section)) {
            $displaysectionnum = is_numeric($displaysection) ? $displaysection : $displaysection->section;
            $displaysection = null;
            foreach ($sections as $section) {
                if ($section->section == $displaysectionnum) {
                    $displaysection = $section;
                    break;
                }
            }
        } else {
            $displaysection = null;
        }

        // If display section is a topic, get the page it is on instead.
        if (isset($displaysection) && $displaysection->levelsan >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
            $displaysection = $sections[$displaysection->parentid];
        }
        // END ADDED.

        $context = context_course::instance($course->id);

        // INCLUDED /course/format/renderer.php function print_single_section_page section "Can we view...".
        // Can we view the section in question?
        if (!($sectioninfo = $displaysection) || !$sectioninfo->uservisiblesan) { // CHANGED: Already have section info.
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            print_error('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }
        // END INCLUDED.

        // Title with completion help icon.
        // REMOVED: Move completioninfo as per print_single_section_page.
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->fmt_course_activity_clipboard($course, $displaysection);    // CHANGED from print_single_section_page.

        // INCLUDED list of sections parts
        // and /course/format/onetopic/renderer.php function print_single_section_page tabs parts CHANGED.

        // Init custom tabs.
        $tabs = array();
        $inactivetabs = array();

        $tabln = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1,
                            FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT - 1, null);
        $sectionatlevel = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
                                     FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT, null);

        foreach ($sections as $thissection) {

            for ($level = $thissection->levelsan; $level < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $level++) {
                $sectionatlevel[$level] = $thissection;
            }

            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible || !$course->hiddensections)
                    && ($thissection->available || !empty($thissection->availableinfo));

            // Make and add tabs for visible pages.
            if ($thissection->levelsan <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT
                || $thissection->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC
                    && $sectionatlevel[$thissection->levelsan - 1]->uservisiblesan && $showsection) {

                $sectionname = get_section_name($course, $thissection);

                $url = course_get_url($course, $thissection);

                // REMOVED: marker.

                // Include main tab, and index tabs for pages with sub-pages.
                for ($level = max(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1, $thissection->levelsan);
                     $level <= $thissection->pagedepthdirect
                                + ($PAGE->user_is_editing()
                                    && $thissection->pagedepthdirect < FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE ? 1 : 0);
                     $level++) {

                    // Make tab.
                    $newtab = new tabobject("tab_id_{$thissection->id}_l{$level}", $url,
                        html_writer::tag('div', $sectionname, ['class' =>
                            'tab_content'
                            . ($thissection->currentnestedlevel >= $level ? ' marker' : '')
                            . (!$thissection->visible || !$thissection->available
                               || $level > $thissection->pagedepthdirect ? ' dimmed' : '')
                        ]),
                        $sectionname);
                    $newtab->level = $level - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT;

                    if ($thissection->id == $displaysection->id) {
                        $newtab->selected = true;
                    }

                    // Add tab.
                    if ($level <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1) {
                        $tabs[] = $newtab;
                    } else {
                        $tabln[$level - 1]->subtree[] = $newtab;
                    }
                    $tabln[$level] = $newtab;

                }

                // Disable tabs for hidden sections.
                if (!$thissection->uservisiblesan) {
                    $inactivetabs[] = "tab_id_{$thissection->id}_l{$thissection->levelsan}";
                }

            }

            // Include "add" sub-tabs if editing.
            if ($thissection->nextanyid == $thissection->nextpageid
                && $PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {

                // Include "add" sub-tabs for each level of page finished.
                $nextsectionlevel = $thissection->nextpageid ? $sections[$thissection->nextpageid]->levelsan
                                                            : FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT;
                for ($level = min($sectionatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1]->pagedepthdirect + 1,
                                    FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE);
                        $level >= $nextsectionlevel + 1;
                        $level--) {

                    // Make "add" tab.
                    $straddsection = get_string_manager()->string_exists('addsectionpage', 'format_' . $course->format) ?
                                        get_string('addsectionpage', 'format_' . $course->format) : get_string('addsections');
                    $url = new moodle_url('/course/format/multitopic/_course_changenumsections.php',
                        ['courseid' => $course->id,
                            'increase' => true,
                            'sesskey' => sesskey(),
                            'insertparentid' => $sectionatlevel[$level - 1]->id,
                            'insertlevel' => $level,                            // ADDED.
                        ]);
                    $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
                    $newtab = new tabobject("tab_id_{$sectionatlevel[$level - 1]->id}_l{($level - 1)}_add",
                        $url,
                        $icon,
                        s($straddsection));

                    // Add "add" tab.
                    if ($level <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1) {
                        $tabs[] = $newtab;
                    } else {
                        $tabln[$level - 1]->subtree[] = $newtab;
                    }
                    $tabln[$level] = null;

                }

            }

        }

        // Display tabs.
        echo html_writer::start_tag('div', ['style' => 'clear: both']); // TODO: Use CSS?
        echo $OUTPUT->tabtree($tabs,
            "tab_id_{$displaysection->id}_l{$displaysection->pagedepthdirect}",
            $inactivetabs);
        echo html_writer::end_tag('div');

        // END INCLUDED.

        // Now the list of sections..
        echo $this->start_section_list();

        // REMOVED numsections.

        // CHANGED.
        $sectionatlevel = array_fill(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
                                     FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT, null);

        foreach ($sections as $thissection) {

            for ($level = $thissection->levelsan; $level < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $level++) {
                $sectionatlevel[$level] = $thissection;
            }

            // REMOVED: Section 0 differentiation and numsections.

            // ADDED.
            // If we're at the start of a page-level section, then open a DIV for it.
            if ($thissection->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
                echo html_writer::start_tag('div',
                    ['style' => 'display: ' . (($thissection->id == $displaysection->id) ? 'block' : 'none')]);
            }
            // END ADDED.

            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible || !$course->hiddensections)
                    && ($thissection->available || !empty($thissection->availableinfo));
            // REMOVED: return if section hidden (we may have more to do), and coursedisplay.

            if ($thissection->levelsan <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT
                || $sectionatlevel[$level - 1]->uservisiblesan && $showsection) {   // ADDED.
                $pageid = ($thissection->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) ? $thissection->id
                                                                                           : $thissection->parentid;
                echo $this->section_header($thissection, $course, $thissection->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC);
                if ($thissection->uservisible && $pageid == $displaysection->id) {
                    // CHANGED LINE ABOVE.
                    // ADDED moved here as per print_single_section_page.
                    if ($thissection->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) {
                        $completioninfo = new completion_info($course);
                        echo $completioninfo->display_help_icon();
                    }
                    // END ADDED.
                    echo $this->courserenderer->course_section_cm_list($course, $thissection); // CHANGED removed section return.
                    echo (new \format_multitopic\course_renderer_wrapper($this->courserenderer)
                         )->course_section_add_cm_control($course, $thissection); // CHANGED removed section return.
                }
                echo $this->section_footer();
            }

            // ADDED.
            // If we're at the end of a page-level section, then close it off.
            if ($thissection->nextanyid == $thissection->nextpageid) {
                if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
                    $insertsection = new stdClass();
                    $insertsection->parentid = $sectionatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1]->id;
                    echo $this->change_number_sections($course, null, $insertsection); // CHANGED.
                }
                echo html_writer::end_tag('div');
            }
            // END ADDED.

        }

        // REMOVED: numsections .

        echo $this->end_section_list();                                         // ADDED moved from above.
        // END CHANGED.

    }
    // END INCLUDED.


    // INCLUDED course/format/renderer.php function change_number_sections .
    // NOTE: Modified to allow inserting at different positions.
    /**
     * Returns controls in the bottom of the page to increase/decrease number of sections
     *
     * @param stdClass $course
     * @param int|null $sectionreturn unused
     * @param stdClass $insertsection
     * @return string
     */
    protected function change_number_sections($course, $sectionreturn = null, stdClass $insertsection = null) : string {
        // CHANGED LINE ABOVE.
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('moodle/course:update', $coursecontext)
            || !has_capability('moodle/course:movesections', $coursecontext)) {
            return '';
        }

        $format = course_get_format($course);
        $maxsections = method_exists($format, "get_max_sections") ? $format->get_max_sections() : 52; // CHANGED for Moodle 3.5.0 .
        $lastsection = $format->get_last_section_number();

        // REMOVED: numsections .

        if (course_get_format($course)->uses_sections()) {
            if ($lastsection >= $maxsections) {
                // Don't allow more sections if we already hit the limit.
                return '';
            }
            // Current course format does not have 'numsections' option but it has multiple sections suppport.
            // Display the "Add section" link that will insert a section in the end.
            // Note to course format developers: inserting sections in the other positions should check both
            // capabilities 'moodle/course:update' and 'moodle/course:movesections'.
            $o = '';
            $o .= html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));
            if (get_string_manager()->string_exists('addsectiontopic', 'format_' . $course->format)) {
                $straddsections = get_string('addsectiontopic', 'format_' . $course->format);
            } else {
                $straddsections = get_string('addsections');
            }
            $url = new moodle_url('/course/format/multitopic/_course_changenumsections.php',
                ['courseid' => $course->id, 'insertparentid' => $insertsection->parentid, 'numsections' => 1,
                'insertlevel' => FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC, 'sesskey' => sesskey()]);
            // REMOVED section return.
            $icon = $this->output->pix_icon('t/add', '');
            $o .= html_writer::link($url, $icon . $straddsections);              // CHANGED: Only add single section.
            $o .= html_writer::end_tag('div');
            return $o;
        }
    }
    // END INCLUDED.

    // INCLUDED /course/format/renderer.php function format_summary_text .
    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) : string {
        $context = context_course::instance($section->course);

        // ADDED.
        // Variables for section image details.
        $imageurl       = null;
        $imagename      = null;
        $authorwithurl  = null;
        $licencecode    = null;

        // Find section image details.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'section', $section->id);
        foreach ($files as $file) {
            $filename       = $file->get_filename();
            $filenameextpos = strrpos($filename, '.');
            if ((substr($filename, 0, 4) == 'goi_') && $filenameextpos) {
                $imageurl       = moodle_url::make_file_url('/pluginfile.php' ,
                                    "/{$file->get_contextid()}/course/section/{$section->id}{$file->get_filepath()}{$filename}");
                $imagename      = substr($filename, 4, $filenameextpos - 4);
                $authorwithurl  = $file->get_author();
                $licencecode    = $file->get_license();
                break;
            }
        }

        $o = '';

        // Output section image, if any.
        if (isset($imageurl)) {
            $o .= html_writer::start_tag('div', ['class' => 'section_image_holder']);
            $o .= html_writer::empty_tag('img', ['src' => $imageurl]);
            $o .= html_writer::start_tag('p');
            $o .= \format_multitopic_image_attribution($imagename, $authorwithurl, $licencecode);
            $o .= html_writer::end_tag('p');
            $o .= html_writer::end_tag('div');
        }
        // END ADDED.

        $summarytext = $o . file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);

        $options = new stdClass();
        $options->noclean   = true;
        $options->overflowdiv = true;
        return format_text($summarytext, $section->summaryformat, $options);
    }
    // END INCLUDED.

    // ADDED.
    /**
     * Generate HTML for course header: A banner with the course title and a slice of the course image.
     *
     * @param \format_multitopic\courseheader $header header to render
     * @return string HTML to output.
     */
    protected function render_courseheader(\format_multitopic\courseheader $header) : string {

        global $PAGE;

        // Include code to preview the banner (and attribution, to an extent), if we're on the course edit page.
        if ($PAGE->has_set_url() && $PAGE->url->compare(new moodle_url('/course/edit.php'), URL_MATCH_BASE)) {
            $PAGE->requires->js('/course/format/multitopic/_course_edit.js');
        }

        return $header->output();
    }

    /**
     * Generate HTML for course content header/footer: Back to course button.
     *
     * @param \format_multitopic\coursecontentheaderfooter $headerfooter header/footer to render
     * @return string HTML to output.
     */
    protected function render_coursecontentheaderfooter(
                            \format_multitopic\coursecontentheaderfooter $headerfooter) : string {
        return $headerfooter->output();
    }
    // END ADDED.

}
