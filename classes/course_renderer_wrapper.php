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
 * INCLUDED /course/renderer.php selected functions .
 * CHANGED: Use section IDs instead of section numbers.  Delay use of section numbers until later, using _course_jumpto.php .
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_multitopic;

defined('MOODLE_INTERNAL') || die;                                              // ADDED.

/**
 * Wrapper for the core course renderer
 *
 * @copyright 2019 James Calder and Otago Polytechnic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer_wrapper {

    // ADDED.
    /** @var core_course_renderer wrapped renderer */
    private $inner;

    // NOTE: We need access to these protected variables, so store our own copy.
    /**
     * @var moodle_page The Moodle page the renderer has been created to assist with.
     */
    protected $innerpage;
    /**
     * @var renderer_base|core_renderer A reference to the current renderer.
     * The renderer provided here will be determined by the page but will in 90%
     * of cases by the see core_renderer
     */
    protected $inneroutput;

    /**
     * Construct wrapper
     *
     * @param \core_course_renderer $inner renderer to be wrapped
     */
    public function __construct(\core_course_renderer $inner) {
        global $PAGE, $OUTPUT;
        $this->inner = $inner;
        $this->innerpage = $PAGE;
        $this->inneroutput = $OUTPUT;
    }
    // END ADDED.


    /**
     * Renders HTML for the menus to add activities and resources to the current course
     *
     * @param \stdClass $course
     * @param \section_info $section section info
     * @param int $sectionreturn The section to link back to (unused)
     * @param array $displayoptions additional display options, for example blocks add
     *     option 'inblock' => true, suggesting to display controls vertically
     * @return string
     */
    public function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = array()) : string {
        // CHANGED ABOVE: Specify section info instead of number.
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
        $urlparams = array('sectionid' => $section->id);                        // CHANGED: Used section ID.

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
            array('class' => 'section_add_menus'));                             // CHANGED: Removed HTML ID--not used?

        if (!$vertical) {
            $output .= \html_writer::start_tag('div', array('class' => 'horizontal'));
        }

        if (!empty($activities[MOD_CLASS_RESOURCE])) {
            $select = new \url_select($activities[MOD_CLASS_RESOURCE], '', array('' => $straddresource));
            // CHANGED LINE ABOVE: Removed form ID.
            $select->set_help_icon('resources');
            $select->set_label($strresourcelabel, array('class' => 'accesshide'));
            $output .= preg_replace('/\/course\/jumpto.php\b/', "/course/format/multitopic/_course_jumpto.php",
                                    $this->inneroutput->render($select));
            // CHANGED LINE ABOVE: Use custom script to convert section ID back to section number.
        }

        if (!empty($activities[MOD_CLASS_ACTIVITY])) {
            $select = new \url_select($activities[MOD_CLASS_ACTIVITY], '', array('' => $straddactivity));
            // CHANGED LINE ABOVE: Removed form ID.
            $select->set_help_icon('activities');
            $select->set_label($stractivitylabel, array('class' => 'accesshide'));
            $output .= preg_replace('/\/course\/jumpto.php\b/', "/course/format/multitopic/_course_jumpto.php",
                                    $this->inneroutput->render($select));
            // CHANGED LINE ABOVE: Use custom script to convert section ID back to section number.
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
            $output = $this->inner->course_modchooser($modules, $course) . $modchooser . $output; // CHANGED.
        }

        return $output;
    }


}
