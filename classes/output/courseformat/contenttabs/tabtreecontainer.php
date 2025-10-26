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

namespace format_multitopic\output\courseformat\contenttabs;

use core\output\named_templatable;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use renderable;
use stdClass;

/**
 * Class to render tabs.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabtreecontainer implements named_templatable, renderable {
    use courseformat_named_templatable;

    /** @var course_format the course format */
    protected $format;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
    }

    /**
     * Get tabs data.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return array tabs data, selected tab, and inactive tabs
     */
    public function get_tabs_data_etc(\renderer_base $output): array {
        global $CFG;
        $sectionsextra = $this->format->fmt_get_sections_extra();
        $displaysectionextra = $sectionsextra[$this->format->get_sectionid()];
        $format = $this->format;
        $course = $format->get_course();
        $maxsections = $format->get_max_sections();
        $canaddmore = $maxsections > $format->get_last_section_number();

        // INCLUDED list of sections parts
        // and /course/format/onetopic/renderer.php function print_single_section_page tabs parts CHANGED.

        // Init custom tabs.
        $tabs = [];
        $inactivetabs = [];

        $tabln = array_fill(
            FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1,
            FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT - 1,
            null
        );
        $sectionextraatlevel = array_fill(
            FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
            FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT,
            null
        );

        foreach ($sectionsextra as $thissectionextra) {
            $thissection = $thissectionextra->sectionbase;

            if (!empty($thissection->component)) {
                continue;
            }

            for ($level = $thissectionextra->levelsan; $level < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; $level++) {
                $sectionextraatlevel[$level] = $thissectionextra;
            }

            // Make and add tabs for visible pages.
            if (
                $thissectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC
                && $format->is_section_visible($thissection)
            ) {
                $sectionname = get_section_name($course, $thissection);

                $url = course_get_url($course, $thissection);

                // REMOVED: marker.

                // Include main tab, and index tabs for pages with sub-pages.
                for (
                    $level = max(FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1, $thissectionextra->levelsan); /* ... */
                    $level <= $thissectionextra->pagedepthdirect
                                + ($format->show_editor()
                                    && $thissectionextra->pagedepthdirect < FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE ?
                                        1 : 0); /* ... */
                    $level++
                ) {
                    // Make tab.
                    $newtab = new tab(
                        "tab_id_{$thissection->id}_l{$level}",
                        $url,
                        \html_writer::tag('div', $sectionname, ['class' =>
                            'tab_content'
                            . ($thissectionextra->currentnestedlevel >= $level ? ' marker' : '')
                            . ((!$thissection->visible || !$thissection->available) && ($thissection->section != 0)
                               || $level > $thissectionextra->pagedepthdirect ? ' dimmed' : ''),
                            'data-itemid' => $thissection->id,
                        ]),
                        $sectionname,
                        true
                    );
                    $newtab->level = $level - FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT;

                    if ($thissection->id == $displaysectionextra->id) {
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
                if (!(($thissection->section == 0) || $thissection->uservisible)) {
                    $inactivetabs[] = "tab_id_{$thissection->id}_l{$thissectionextra->levelsan}";
                }
            }

            // Include "add" sub-tabs if editing.
            if (
                $thissectionextra->nextanyid == $thissectionextra->nextpageid
                && $format->show_editor()
            ) {
                // Include "add" sub-tabs for each level of page finished.
                $nextsectionlevel = $thissectionextra->nextpageid ?
                                    $sectionsextra[$thissectionextra->nextpageid]->levelsan : FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT;
                for (
                    $level = min(
                        $sectionextraatlevel[FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC - 1]->pagedepthdirect + 1,
                        FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE
                    ); /* ... */
                    $level >= $nextsectionlevel + 1; /* ... */
                    $level--
                ) {
                    $parent = $sectionextraatlevel[$level - 1]->sectionbase;
                    if (!(($parent->section == 0) || $parent->uservisible && $format->is_section_visible($parent))) {
                        continue;
                    }

                    // Make "add" tab.
                    $straddsection = get_string_manager()->string_exists('addsectionpage', 'format_' . $course->format) ?
                                        get_string('addsectionpage', 'format_' . $course->format) : get_string('addsections');
                    if ($CFG->version < 2025082900) {
                        $params = [
                            'courseid' => $course->id,
                            'increase' => true,
                            'sesskey' => sesskey(),
                            'insertparentid' => $sectionextraatlevel[$level - 1]->id,
                            'insertlevel' => $level,
                            'returnurl' => new \moodle_url("/course/view.php?id={$course->id}"
                                . (($format->get_sectionid() != $format->fmtrootsectionid) ?
                                "&sectionid={$format->get_sectionid()}" : "")),
                        ];
                        $url = new \moodle_url('/course/format/multitopic/_course_changenumsections.php', $params);
                    } else {
                        $url = $format->get_update_url(
                            action: 'fmt_section_add_into',
                            targetsectionid: $sectionextraatlevel[$level - 1]->id,
                            targetcmid: $level,
                            returnurl: new \moodle_url("/course/view.php?id={$course->id}"
                                . (($format->get_sectionid() != $format->fmtrootsectionid) ?
                                "&sectionid={$format->get_sectionid()}" : "")),
                        );
                    }
                    $icon = $output->pix_icon('t/switch_plus', $straddsection, 'moodle');
                    $newtab = new tab(
                        "tab_id_{$sectionextraatlevel[$level - 1]->id}_l{$level}_add",
                        $url,
                        $icon,
                        s($straddsection)
                    );

                    // Add "add" tab.
                    if ($level <= FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT + 1) {
                        $tabs[] = $newtab;
                    } else {
                        $tabln[$level - 1]->subtree[] = $newtab;
                    }
                    $tabln[$level] = null;

                    // Disable "add" tab if can't add more sections.
                    if (!$canaddmore) {
                        $inactivetabs[] = "tab_id_{$sectionextraatlevel[$level - 1]->id}_l{$level}_add";
                    }
                }
            }
        }

        $selectedtab = "tab_id_{$displaysectionextra->id}_l{$displaysectionextra->pagedepthdirect}";

        // END INCLUDED.

        return ['tabs' => $tabs, 'selected' => $selectedtab, 'inactive' => $inactivetabs];
    }

    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        return (new \tabtree(...$this->get_tabs_data_etc($output)))->export_for_template($output);
    }
}
