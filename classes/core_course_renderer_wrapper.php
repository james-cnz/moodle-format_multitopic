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
 * Renderer for use with the course section and all the goodness that falls within it.
 *
 * INCLUDED from /course/renderer.php .
 * CHANGED: Use section IDs instead of section numbers.  Delay use of section numbers until later.
 *
 * @package   format_multitopic
 * @copyright 2010 Sam Hemelryk,
 *            2012 David Herney Bernal - cirano,
 *            2018 Otago Polytechnic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_multitopic;

defined('MOODLE_INTERNAL') || die;                                              // ADDED.

/**
 * Wrapper for the core course renderer
 *
 * @copyright  2018 Otago Polytechnic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_multitopic_core_course_renderer_wrapper {

    // ADDED.
    /** @var core_course_renderer wrapped renderer */
    private $inner;
    /** @var moodle_page PAGE global, needed by the wrapper, but private to the wrapped object, so we store our own copy instead */
    private $innerpage;
    /** @var renderer_base OUTPUT global, as above */
    private $inneroutput;

    /**
     * Construct wrapper
     *
     * @param core_course_renderer $inner renderer to be wrapped
     */
    public function __construct(\core_course_renderer $inner) {
        global $PAGE, $OUTPUT;
        $this->inner = $inner;
        $this->innerpage = $PAGE;
        $this->inneroutput = $OUTPUT;
    }
    // END ADDED.

    // REMOVED start - function render_modchooser .

    /**
     * Build the HTML for the module chooser javascript popup
     *
     * @param array $modules A set of modules as returned from
     * @see get_module_metadata
     * @param object $course The course that will be displayed
     * @return string The composed HTML for the module
     */
    public function course_modchooser($modules, $course) : string {
        if (!$this->innerpage->requires->should_create_one_time_item_now('core_course_modchooser')) {
            return '';
        }
        $modchooser = new \core_course\output\modchooser($course, $modules);
        $modchooser->actionurl = new \moodle_url('/course/format/multitopic/_course_jumpto.php'); // ADDED.
        // TODO: Use section ID.
        return $this->inner->render($modchooser);
    }

    // REMOVED function course_modchooser_module_types - function course_section_cm_edit_actions .

    /**
     * Renders HTML for the menus to add activities and resources to the current course
     *
     * @param stdClass $course
     * @param section_info $section section
     * @param int $sectionreturn The section to link back to (unused)
     * @param array $displayoptions additional display options, for example blocks add
     *     option 'inblock' => true, suggesting to display controls vertically
     * @return string
     */
    public function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = array()) : string {
        global $CFG;

        $vertical = !empty($displayoptions['inblock']);

        // Check to see if user can add menus and there are modules to add.
        if (!has_capability('moodle/course:manageactivities', \context_course::instance($course->id))
                || !$this->innerpage->user_is_editing()                         // CHANGED.
                || !($modnames = get_module_types_names()) || empty($modnames)) {
            return '';
        }

        // Retrieve all modules with associated metadata.
        $modules = get_module_metadata($course, $modnames);                     // CHANGED: Removed sectionreturn.
        $urlparams = array('sectionid' => $section->id);                        // CHANGED: Used section id.

        // We'll sort resources and activities into two lists.
        $activities = array(MOD_CLASS_ACTIVITY => array(), MOD_CLASS_RESOURCE => array());

        foreach ($modules as $module) {
            $activityclass = MOD_CLASS_ACTIVITY;
            if ($module->archetype == MOD_ARCHETYPE_RESOURCE) {
                $activityclass = MOD_CLASS_RESOURCE;
            } else if ($module->archetype === MOD_ARCHETYPE_SYSTEM) {
                // System modules cannot be added by user, do not add to dropdown.
                continue;
            }
            $link = $module->link->out(true, $urlparams);
            $activities[$activityclass][$link] = $module->title;
        }

        $straddactivity = get_string('addactivity');
        $straddresource = get_string('addresource');
        $sectionname = get_section_name($course, $section);
        $strresourcelabel = get_string('addresourcetosection', null, $sectionname);
        $stractivitylabel = get_string('addactivitytosection', null, $sectionname);

        $output = \html_writer::start_tag('div',
            array('class' => 'section_add_menus'));                             // CHANGED.

        if (!$vertical) {
            $output .= \html_writer::start_tag('div', array('class' => 'horizontal'));
        }

        if (!empty($activities[MOD_CLASS_RESOURCE])) {
            $select = new \url_select($activities[MOD_CLASS_RESOURCE], '', array('' => $straddresource)); // CHANGED.
            $select->set_help_icon('resources');
            $select->set_label($strresourcelabel, array('class' => 'accesshide'));
            $output .= preg_replace('/\/course\/jumpto.php\b/', "/course/format/multitopic/_course_jumpto.php",
                                    $this->inneroutput->render($select));
            // CHANGED LINE ABOVE: Convert section ID back to section number later.
        }

        if (!empty($activities[MOD_CLASS_ACTIVITY])) {
            $select = new \url_select($activities[MOD_CLASS_ACTIVITY], '', array('' => $straddactivity)); // CHANGED.
            $select->set_help_icon('activities');
            $select->set_label($stractivitylabel, array('class' => 'accesshide'));
            $output .= preg_replace('/\/course\/jumpto.php\b/', "/course/format/multitopic/_course_jumpto.php",
                                    $this->inneroutput->render($select));
            // CHANGED LINE ABOVE: Convert section ID back to section number later.
        }

        if (!$vertical) {
            $output .= \html_writer::end_tag('div');
        }

        $output .= \html_writer::end_tag('div');

        if (course_ajax_enabled($course) && $course->id == $this->innerpage->course->id) { // CHANGED.
            // Modchooser can be added only for the current course set on the page!
            $straddeither = get_string('addresourceoractivity');
            // The module chooser link.
            $modchooser = \html_writer::start_tag('div', array('class' => 'mdl-right'));
            $modchooser .= \html_writer::start_tag('div', array('class' => 'section-modchooser'));
            $icon = $this->inneroutput->pix_icon('t/add', '');              // CHANGED.
            $span = \html_writer::tag('span', $straddeither, array('class' => 'section-modchooser-text'));
            $modchooser .= \html_writer::tag('span', $icon . $span, array('class' => 'section-modchooser-link'));
            $modchooser .= \html_writer::end_tag('div');
            $modchooser .= \html_writer::end_tag('div');

            // Wrap the normal output in a noscript div.
            $usemodchooser = get_user_preferences('usemodchooser', $CFG->modchooserdefault);
            if ($usemodchooser) {
                $output = \html_writer::tag('div', $output, array('class' => 'hiddenifjs addresourcedropdown'));
                $modchooser = \html_writer::tag('div', $modchooser, array('class' => 'visibleifjs addresourcemodchooser'));
            } else {
                // If the module chooser is disabled, we need to ensure that the dropdowns are shown even if javascript is disabled.
                $output = \html_writer::tag('div', $output, array('class' => 'show addresourcedropdown'));
                $modchooser = \html_writer::tag('div', $modchooser, array('class' => 'hide addresourcemodchooser'));
            }
            $output = $this->course_modchooser($modules, $course) . $modchooser . $output; // CHANGED.
        }

        return $output;
    }

    // REMOVED course_search_form - class end .

}

// REMOVED - end .
