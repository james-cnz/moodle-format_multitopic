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

namespace format_multitopic;

use section_info;
use core\context\module as context_module;
use core\context_helper;
use core\output\action_link;
use core\output\actions\component_action;
use core\output\pix_icon;
use core\url;
use moodle_page;
use stdClass;
use global_navigation;
use navigation_node;


/**
 * The global navigation class used for... the global navigation
 *
 * This class is used by PAGE to store the global navigation for the site
 * and is then used by the settings nav and navbar to save on processing and DB calls
 *
 * INCLUDED from /lib/classes/navigation/global_navigation.php
 * CHANGED: Modified to show sections in a heirarchy, and use section IDs where possible.
 *
 * @package   format_multitopic
 * @copyright 2019 James Calder and Otago Polytechnic
 * @copyright based on work by 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class global_navigation_wrapper {
    // CHANGED LINE ABOVE.

    // ADDED.
    /** @var global_navigation wrapped renderer */
    private $inner;
    // END ADDED.

    // NOTE: We need access to these private variables, so store our own copy.
    /** @var moodle_page The Moodle page this navigation object belongs to. */
    protected moodle_page $innerpage;
    /** @var bool A switch for whether to show empty sections in the navigation. */
    protected bool $innershowemptysections = true;

    // INCLUDED class navigation_node $includesectionnum .
    /** @var int|null If set to an int, that section will be included even if it has no activities */
    public int|null $innerincludesectionid = null;
    // END INCLUDED.

    /**
     * Construct wrapper
     *
     * @param global_navigation $inner navigation to wrap
     * @param moodle_page $page The page this navigation object belongs to
     */
    public function __construct(global_navigation $inner, moodle_page $page) {
        $this->inner = $inner;
        $this->innerpage = $page;
    }

    /**
     * Generates an array of sections and an array of activities for the given course.
     *
     * @param stdClass $course
     * @return array[] Array($sections, $activities)
     */
    protected function generate_sections_and_activities(stdClass $course): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $modinfo = get_fast_modinfo($course);
        $sectionsorig = $modinfo->get_section_info_all();                       // CHANGED.
        $format = course_get_format($course);

        // For course formats using 'numsections' trim the sections list.
        // REMOVED.

        $sections = [];                                                         // ADDED.
        $activities = [];

        foreach ($sectionsorig as $section) {                                   // CHANGED.
            $key = $section->id;                                                // ADDED.
            // Clone and unset summary to prevent $SESSION bloat (MDL-31802).
            $sections[$key] = clone($section);
            unset($sections[$key]->summary);
            $sections[$key]->hasactivites = false;

            foreach ($section->get_sequence_cm_infos() as $cm) {
                $activity = new stdClass();
                $activity->id = $cm->id;
                $activity->course = $course->id;
                $activity->section = $section->section;
                $activity->name = $cm->name;
                $activity->icon = $cm->icon;
                $activity->iconcomponent = $cm->iconcomponent;
                $activity->hidden = (!$cm->visible);
                $activity->modname = $cm->modname;
                $activity->nodetype = navigation_node::NODETYPE_LEAF;
                $activity->onclick = $cm->onclick;
                $url = $cm->url;

                // Activities witout url but with delegated section uses the section url.
                $activity->delegatedsection = $cm->get_delegated_section_info();
                if (empty($cm->url) && $activity->delegatedsection) {
                    $url = $format->get_view_url(
                        $activity->delegatedsection->sectionnum
                    );
                }

                if (!$url) {
                    $activity->url = null;
                    $activity->display = false;
                } else {
                    $activity->url = $url->out();
                    $activity->display = $cm->is_visible_on_course_page() ? true : false;
                    if (\global_navigation::module_extends_navigation($cm->modname)) { // CHANGED.
                        $activity->nodetype = navigation_node::NODETYPE_BRANCH;
                    }
                }
                $activities[$cm->id] = $activity;
                if ($activity->display) {
                    $sections[$key]->hasactivites = true;
                }
            }
        }

        return [$sections, $activities];
    }

    /**
     * Generically loads the course sections into the course's navigation.
     *
     * @param stdClass $course
     * @param navigation_node $coursenode
     * @return object[] An array of course section nodes
     */
    public function load_generic_course_sections(stdClass $course, navigation_node $coursenode): array {
        global $CFG, $SITE;                                                     // CHANGED: Removed $DB and $USER.
        require_once($CFG->dirroot . '/course/lib.php');

        $format = course_get_format($course);
        [$sections, $activities] = $this->generate_sections_and_activities($course);

        $navigationsections = [];
        // ADDED.
        // Navigation node at level n.
        // This is a list of the navigation nodes currently at each level,
        // from the node for the course, down to the node we're currently working on.
        $nodeln = array_fill(
            FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
            FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1,
            $coursenode
        );
        // The navigation node we're currently working on.
        $sectionnode = $coursenode;
        // Extra navigation node ID counter.
        // Each navigation node must have an ID. Normally we would use section IDs,
        // but we need extra IDs for sections shown at multiple levels.
        // (These extra IDs are negative so they don't conflict with existing IDs).
        $extraid = -1;
        // END ADDED.
        foreach ($sections as $section) {
            // Delegated sections should be added from the activity node.
            if ($section->component) {
                continue;
            }

            if ($course->id == $SITE->id) {
                $this->load_section_activities_navigation($coursenode, $section, $activities);
                continue;
            }

            $sectionid = $section->id;
            $sectionextra = course_get_format($course)->fmt_get_section_extra($section); // ADDED.

            if (
                !(($section->section == 0) || $section->uservisible && course_get_format($course)->is_section_visible($section))
                || (
                    !$this->innershowemptysections
                    && !$section->hasactivites && !$sectionextra->hassubsections
                    && ($this->inner->includesectionnum != $section->section)
                    && ($this->innerincludesectionid != $section->id)
                )
            ) {
                        // CHANGED ABOVE: Use sanitised visibility, check for subsections, and use section ID.
                continue;
            }

            // CHANGED.
            $sectionname = $format->get_section_short_name($section);
            $url = $format->get_view_url($section);

            // Add multiple nodes per section, one per level as required.
            // The course node already exists, so we must start below course level.
            // And activities don't seem to get removed from the course node in the Boost theme,
            // so we need at least one section node to attach activities to.
            // ADDED.
            $firstlevel = max($sectionextra->levelsan, FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1);
            $lastlevel = max($sectionextra->pagedepthdirect, $firstlevel);
            // END ADDED.
            for ($level = $firstlevel; $level <= $lastlevel; $level++) {
                $parentnode = $nodeln[$level - 1];                          // ADDED.
                $nodeid = ($level == $lastlevel) ? $sectionid : $extraid--; // ADDED.
                $sectionnode = $parentnode->add(
                    text: $sectionname,
                    action: $url,
                    type: navigation_node::TYPE_SECTION,
                    key: $nodeid,
                    icon: new pix_icon(
                        ($sectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) ? 'i/section' : 'e/bullet_list',
                        ''
                    )
                );
                // CHANGED ABOVE: Attach to parentnode with nodeid as defined above, and use a list icon for topic sections.
                $sectionnode->nodetype = navigation_node::NODETYPE_BRANCH;
                $sectionnode->hidden = (!$section->visible || !$section->available) && ($section->section != 0);
                $sectionnode->add_attribute('data-section-name-for', $section->id);
                $nodeln[$level] = $sectionnode;                             // ADDED.
            }

            // Fill the rest of the list of nodes with the node we're currently working on.
            // This is so Topic-level sections will find the correct parent.
            for ($level = $level; $level <= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $level++) {
                $nodeln[$level] = $sectionnode;
            }

            if (
                ($this->inner->includesectionnum == $section->section)
                || ($this->innerincludesectionid == $section->id) // TODO: Replace with is_section_in_breadcrumbs?
                || $sectionextra->hassubsections
                // CHANGED ABOVE: Use section ID.
                // Also check for subsections, because activities might not get loaded otherwise.
            ) {
                $this->load_section_activities_navigation($sectionnode, $section, $activities);
            }
            $navigationsections[$sectionid] = $section;
            // END CHANGED.
        }
        return $navigationsections;
    }

    /**
     * Loads all of the activities for a section into the navigation structure.
     *
     * This method is called from global_navigation::load_section_navigation(),
     * It is not intended to be called directly.
     *
     * @param navigation_node $sectionnode
     * @param section_info $section
     * @param stdClass[] $activitiesdata Array of objects containing activities data indexed by cmid.
     * @return stdClass[] Array of activity nodes
     */
    protected function load_section_activities_navigation(
        navigation_node $sectionnode,
        section_info $section,
        array $activitiesdata,
    ): array {
        global $CFG, $SITE;

        $activitynodes = [];
        if (empty($activitiesdata)) {
            return $activitynodes;
        }

        foreach ($section->get_sequence_cm_infos() as $cm) {
            $activitydata = $activitiesdata[$cm->id];

            // If activity is a delegated section, load a section node instead of the activity one.
            if ($activitydata->delegatedsection) {
                $activitynodes[$activitydata->id] = $this->inner->load_section_navigation( // CHANGED.
                    parentnode: $sectionnode,
                    section: $activitydata->delegatedsection,
                    activitiesdata: $activitiesdata,
                );
                continue;
            }

            $activitynodes[$activitydata->id] = $this->load_activity_navigation($sectionnode, $activitydata);
        }

        return $activitynodes;
    }

    /**
     * Loads an activity into the navigation structure.
     *
     * This method is called from global_navigation::load_section_activities_navigation(),
     * It is not intended to be called directly.
     *
     * @param navigation_node $sectionnode
     * @param stdClass $activitydata The acitivy navigation data generated from generate_sections_and_activities
     * @return navigation_node
     */
    protected function load_activity_navigation(
        navigation_node $sectionnode,
        stdClass $activitydata,
    ): navigation_node {
        global $SITE, $CFG;

        $showactivities = ($activitydata->course != $SITE->id) || !empty($CFG->navshowfrontpagemods);

        $icon = new pix_icon(
            $activitydata->icon ?: 'monologo',
            get_string('modulename', $activitydata->modname),
            $activitydata->icon ? $activitydata->iconcomponent : $activitydata->modname,
        );

        // Prepare the default name and url for the node.
        $displaycontext = context_helper::get_navigation_filter_context(context_module::instance($activitydata->id));
        $activityname = format_string($activitydata->name, true, ['context' => $displaycontext]);

        $activitynode = $sectionnode->add(
            text: $activityname,
            action: $this->get_activity_action($activitydata, $activityname),
            type: navigation_node::TYPE_ACTIVITY,
            key: $activitydata->id,
            icon: $icon,
        );
        $activitynode->title(get_string('modulename', $activitydata->modname));
        $activitynode->hidden = $activitydata->hidden;
        $activitynode->display = $showactivities && $activitydata->display;
        $activitynode->nodetype = $activitydata->nodetype;

        return $activitynode;
    }

    /**
     * Returns the action for the activity.
     *
     * @param stdClass $activitydata The acitivy navigation data generated from generate_sections_and_activities
     * @param string $activityname
     * @return url|action_link
     */
    protected function get_activity_action(stdClass $activitydata, string $activityname): url|action_link {
        // A static counter for JS function naming.
        static $legacyonclickcounter = 0;

        $action = new url($activitydata->url);

        // Check if the onclick property is set (puke!).
        if (!empty($activity->onclick)) {
            // Increment the counter so that we have a unique number.
            $legacyonclickcounter++;
            // Generate the function name we will use.
            $functionname = 'legacy_activity_onclick_handler_' . $legacyonclickcounter;
            $propogrationhandler = '';
            // Check if we need to cancel propogation. Remember inline onclick
            // events would return false if they wanted to prevent propogation and the
            // default action.
            if (strpos($activity->onclick, 'return false')) {
                $propogrationhandler = 'e.halt();';
            }
            // Decode the onclick - it has already been encoded for display (puke).
            $onclick = htmlspecialchars_decode($activity->onclick, ENT_QUOTES);
            // Build the JS function the click event will call.
            $jscode = "function {$functionname}(e) { $propogrationhandler $onclick }";
            $this->innerpage->requires->js_amd_inline($jscode);                 // CHANGED.
            // Override the default url with the new action link.
            $action = new action_link($action, $activityname, new component_action('click', $functionname));
        }
        return $action;
    }
}
