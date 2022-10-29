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

namespace format_multitopic\courseformat;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../locallib.php');

use format_multitopic;

/**
 * Contains the core course state actions.
 *
 * The methods from this class should be executed via "core_courseformat_edit" web service.
 *
 * Each format plugin could extend this class to provide new actions to the editor.
 * Extended classes should be locate in "format_XXX\course" namespace and
 * extends core_courseformat\stateactions.
 *
 * @package    format_multitopic
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateactions extends \core_courseformat\stateactions {

    /**
     * Move course sections to another location in the same course.
     *
     * @param \core_courseformat\stateupdates $updates the affected course elements track
     * @param \stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id
     * @param int $targetcmid optional target cm id
     */
    public function fmt_section_move(
        \core_courseformat\stateupdates $updates,
        \stdClass $course,
        array $ids,
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        // Validate target elements.
        if (!$targetsectionid) {
            throw new \moodle_exception("Action cm_move requires targetsectionid");
        }

        // ADDED.
        $format = course_get_format($course);
        $sections = $format->fmt_get_sections();
        $origin = $sections[$ids[0]];
        $ids = [ $origin->id ];
        $originsub = $origin;
        while ($sections[$originsub->nextanyid] && $sections[$originsub->nextanyid]->levelsan > $origin->levelsan) {
            $originsub = $sections[$originsub->nextanyid];
            $ids[] = $originsub->id;
        }
        $targetsection = $sections[$targetsectionid];
        if ($targetsection->section > $origin->section) {
            $destination = (object)['prevupid' => $targetsectionid];
        } else {
            $destination = (object)['nextupid' => $targetsectionid];
        }
        // END ADDED.

        $this->validate_sections($course, $ids, __FUNCTION__);

        $coursecontext = \context_course::instance($course->id);
        require_capability('moodle/course:movesections', $coursecontext);

        $modinfo = get_fast_modinfo($course);

        // Target section.
        $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
        $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);

        $affectedsections = [$targetsection->section => true];

        $sections = $this->get_section_info($modinfo, $ids);
        foreach ($sections as $section) {
            $affectedsections[$section->section] = true;
        }
        format_multitopic_move_section_to($course, $origin, $destination);      // CHANGED.

        // Use section_state to return the section and activities updated state.
        $this->section_state($updates, $course, $ids, $targetsectionid);

        // All course sections can be renamed because of the resort.
        $allsections = $modinfo->get_section_info_all();
        foreach ($allsections as $section) {
            // Ignore the affected sections because they are already in the updates.
            if (isset($affectedsections[$section->section])) {
                continue;
            }
            $updates->add_section_put($section->id);
        }
        // The section order is at a course level.
        $updates->add_course_put();
    }

}
