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
 * Contains the main course format out class.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @copyright based on work by 2012 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_multitopic\output\courseformat;

use core_courseformat\output\local\content as content_base;
use renderer_base;

/**
 * Base class to render a course format.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @copyright based on work by 2012 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG;
        global $PAGE;

        $format = $this->format;

        $sectionsextra = $this->format->fmt_get_sections_extra();
        $displaysectionextra = $sectionsextra[$this->format->get_sectionid()];
        if (!empty($displaysectionextra->sectionbase->component)) {
            $data = parent::export_for_template($output);
            $data->displayonesection = true;
            $data->originalsinglesectionid = $format->originalsinglesectionid;
            return $data;
        }

        $PAGE->requires->js_call_amd('format_multitopic/courseformat/courseeditor/mutations', 'init');

        // ADDED.
        $course = $format->get_course();
        $activesectionids = [];
        for (
            $activesectionextra = $displaysectionextra; /* ... */
            $activesectionextra; /* ... */
            $activesectionextra = (($activesectionextra->parentid
                && ($activesectionextra->levelsan > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1
                    || $activesectionextra->parentid != $format->fmtrootsectionid))
                ? $sectionsextra[$activesectionextra->parentid] : null)
        ) {
            $activesectionids[$activesectionextra->id] = true;
        }
        $sectionpreferencesarray = $format->get_sections_preferences();
        $indexcollapsed = [];
        $indexcollapsedchanged = false;
        foreach ($sectionpreferencesarray as $sectionid => $sectionpreferences) {
            if (!empty($sectionpreferences->indexcollapsed)) {
                if (!isset($activesectionids[$sectionid])) {
                    $indexcollapsed[] = $sectionid;
                } else {
                    $indexcollapsedchanged = true;
                }
            }
        }
        if ($indexcollapsedchanged) {
            $format->set_sections_preference('indexcollapsed', $indexcollapsed);
        }
        // END ADDED.

        // INCLUDED from course/format/classes/output/section_renderer.php print_single_section_page() .
        // Can we view the section in question?
        if (
            !(
                ($sectioninfo = $displaysectionextra->sectionbase)
                && ($sectioninfo->section == 0 || $sectioninfo->uservisible && $format->is_section_visible($sectioninfo))
            )
        ) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            throw new \moodle_exception(
                'unknowncoursesection',
                'error',
                course_get_url($course),
                format_string($course->fullname)
            );
        }
        // END INCLUDED.

        $tabs = new \format_multitopic\output\courseformat\contenttabs\tabtreecontainer($format);
        $tabseft = $tabs->export_for_template($output);

        // Most formats uses section 0 as a separate section so we remove from the list.
        $sectionseft = $this->export_sections($output);
        $initialsection = null;
        if (!empty($sectionseft)) {
            $initialsection = array_shift($sectionseft);
        }

        $data = (object)[
            'title' => $format->page_title(), // This method should be in the course_format class.
            'tabs' => $tabseft, // ADDED.
            'initialsection' => $initialsection,
            'sections' => $sectionseft,
            'format' => $format->get_format(),
            'originalsinglesectionid' => $format->originalsinglesectionid,
            'fmthavemaxsections' => ($CFG->version < 2025060500),
        ];

        // REMOVED navigation.

        $addsection = new $this->addsectionclass($format);
        $data->numsections = $addsection->export_for_template($output);

        // Allow next and back navigation between pages.
        $sectionnav = new \format_multitopic\output\courseformat\content\sectionnavigation(
            $format,
            $displaysectionextra->sectionbase
        );
        $data->sectionnavigation = $sectionnav->export_for_template($output);

        if ($format->show_editor()) {
            $bulkedittools = new $this->bulkedittoolsclass($format);
            $data->bulkedittools = $bulkedittools->export_for_template($output);
        }

        return $data;
    }

    /**
     * Return an array of sections to display.
     *
     * @param \course_modinfo $modinfo the current course modinfo object
     * @return \section_info[] an array of section_info to display
     */
    protected function get_sections_to_display(\course_modinfo $modinfo): array {
        $format = $this->format;
        $sectionsextra = $format->fmt_get_sections_extra();
        $displaysectionextra = $sectionsextra[$format->get_sectionid()];

        if (!empty($displaysectionextra->sectionbase->component)) {
            return [
                $displaysectionextra->sectionbase,
            ];
        }

        $sectionstodisplay = [];
        foreach ($sectionsextra as $thissectionextra) {
            if (!empty($thissectionextra->sectionbase->component)) {
                continue;
            }
            $pageid = ($thissectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) ?
                        $thissectionextra->id : $thissectionextra->parentid;
            $onpage = ($pageid == $format->get_sectionid());
            if ($onpage) {
                $sectionstodisplay[] = $thissectionextra->sectionbase;
            }
        }
        return $sectionstodisplay;
    }
}
