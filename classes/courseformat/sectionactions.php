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

use core\exception\moodle_exception;
use core_courseformat\local\sectionactions as base_sectionactions;
use course_modinfo as modinfo;
use stdClass;

/**
 * Section course format actions.
 *
 * @package    format_multitopic
 * @copyright  2024 onwards James Calder and Otago Polytechnic
 * @copyright  based on work by 2023 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionactions extends base_sectionactions {
    /**
     * Create a course section using a record object.
     *
     * If no position is specified, the section is added to the end of the course.
     *
     * @param stdClass $fields the fields to set on the section
     * @param bool $skipcheck the position check has already been made and we know it can be used
     * @return stdClass the created section record
     */
    public function fmt_create_from_object(stdClass $fields, bool $skipcheck = false): stdClass {
        global $DB;

        $skipcheck = $skipcheck && isset($fields->section);

        // Determine the create position, and adapt fields for the move method, if necessary.
        if ($skipcheck) {
            $createnum = $fields->section;
        } else {
            $createnum = $DB->get_field_sql(
                'SELECT max(section) from {course_sections} WHERE course = ?',
                [$this->course->id]
            ) + 1;
            if (!isset($fields->section) && !isset($fields->prevupid) && !isset($fields->nextupid) && !isset($fields->parentid)) {
                $fields->nextupid = null;
            }
            if (empty($fields->component) && !isset($fields->level)) {
                $fields->level = 2;
            }
        }

        // First add section to the end.
        $sectionrecord = (object) [
            'course' => $this->course->id,
            'section' => $createnum,
            'summary' => $fields->summary ?? '',
            'summaryformat' => $fields->summaryformat ?? FORMAT_HTML,
            'sequence' => '',
            'name' => $fields->name ?? null,
            'visible' => $fields->visible ?? 1,
            'availability' => $fields->availability ?? null,
            'component' => $fields->component ?? null,
            'itemid' => $fields->itemid ?? null,
            'timemodified' => time(),
        ];
        $sectionrecord->id = $DB->insert_record("course_sections", $sectionrecord);
        if (empty($fields->component) && !($skipcheck && ($fields->section > 0))) {
            $DB->insert_record("course_format_options", [
                'courseid' => $this->course->id,
                'format' => 'multitopic',
                'sectionid' => $sectionrecord->id,
                'name' => 'level',
                'value' => 0,
            ]);
        }

        // Now move it to the specified position, if necessary.
        $skipmove = $skipcheck || !empty($fields->component) && property_exists($fields, 'nextupid') && ($fields->nextupid == null);
        if (!$skipmove) {
            try {
                rebuild_course_cache($this->course->id, true);
                $movednews = $this->fmt_move_at(
                    [$sectionrecord],
                    $fields,
                    !empty($fields->component) ? 2 : 1
                );
                $sectionrecord->section = $movednews[$sectionrecord->id]->section;
            } catch (moodle_exception $e) {
                $DB->delete_records('course_sections', ['id' => $sectionrecord->id]);
                throw $e;
            }
        }

        \core\event\course_section_created::create_from_section($sectionrecord)->trigger();

        rebuild_course_cache($this->course->id, true);
        return $sectionrecord;
    }

    /**
     * Move course sections at a given position.
     *
     * @param array $origins the sections to move.  Must specify id.
     * @param stdClass $target the position to move the section to.
     *          Must specify parentid, prevupid, or nextupid.  May specify level.
     * @param int $include 0 = regular only, 1 = also orphan, 2 = also delegated.
     * @return object[]  objects containing section numbers for the moved sections, indexed by id.
     */
    public function fmt_move_at(array $origins, stdClass $target, int $include = 1): array {
        global $DB;

        // Get all sections for this course and re-order them.
        rebuild_course_cache($this->course->id, true);
        if (!$sectionsextra = course_get_format($this->course)->fmt_get_sections_extra()) {
            throw new moodle_exception('cannotcreateorfindstructs');
        }
        foreach ($origins as $origin) {
            $originid = $origin->id;
            if (!isset($sectionsextra[$originid])) {
                throw new moodle_exception('sectionnotexist');
            }
        }

        $movedsections = $this->fmt_reorder_sections($sectionsextra, $origins, $target, $include);

        // Update all sections. Do this in 2 steps to avoid breaking database
        // uniqueness constraint.
        $transaction = $DB->start_delegated_transaction();
        foreach ($movedsections as $id => $movedsection) {
            $position = $movedsection->section;
            if ($sectionsextra[$id]->section !== $position) {
                $DB->set_field('course_sections', 'section', -$position, ['id' => $id]);
                // Invalidate the section cache by given section id.
                modinfo::purge_course_section_cache_by_id($this->course->id, $id);
            }
        }
        foreach ($movedsections as $id => $movedsection) {
            $position = $movedsection->section;
            if ($sectionsextra[$id]->section !== $position) {
                $DB->set_field('course_sections', 'section', $position, ['id' => $id]);
                // Invalidate the section cache by given section id.
                modinfo::purge_course_section_cache_by_id($this->course->id, $id);
            }
        }

        $transaction->allow_commit();
        rebuild_course_cache($this->course->id, true);

        // Set properties for moved sections.
        foreach ($movedsections as $id => $movedsection) {
            if (!empty($sectionsextra[$id]->sectionbase->component)) {
                continue;
            }
            // Find differences between original section and moved section, and store as updates.
            $updates = [];
            if ($sectionsextra[$id]->sectionbase->level !== $movedsection->level) {
                $updates['level'] = $movedsection->level;
            }
            if ($sectionsextra[$id]->sectionbase->visible != $movedsection->visible) {
                $updates['visible'] = $movedsection->visible;
            }
            // Set page-level sections to untimed.
            if (
                ($movedsection->level < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC)
                && ($sectionsextra[$id]->sectionbase->periodduration != '0 day')
            ) {
                $updates['periodduration'] = '0 day';
            }
            // Apply section updates.
            if ($updates) {
                course_update_section($this->course, $sectionsextra[$id]->sectionbase, $updates);
            }
        }

        // Provide new section numbers for moved sections.
        $movedorigins = [];
        foreach ($origins as $origin) {
            $originid = $origin->id;
            $movedorigins[$originid] = (object)['id' => $originid, 'section' => $movedsections[$originid]->section];
        }

        return $movedorigins;
    }

    /**
     * Reorder sectionsextra array by moving origins to target.
     *
     * This is a helper for fmt_move_at().
     * Reads calculated section values (levelsan, visiblesan).
     * Writes raw section values (level, visible).
     *
     * @param \format_multitopic\section_info_extra[] $sectionsextra array of sectionid=>sectionextra.
     * @param array $origins the sections to move.  Must specify id.
     * @param stdClass $target The destination to move to.
     *          Must specify parentid, prevupid, or nextupid.  May specify level.
     * @param int $include 0 = regular sections, 1 = also orphaned, 2 = also delegated
     * @return array
     */
    protected function fmt_reorder_sections(array $sectionsextra, array $origins, stdClass $target, int $include): array {
        // Ignore delegated sections if appropriate.
        $ignoredsections = [];
        if ($include < 2) {
            foreach ($sectionsextra as $id => $sectionextra) {
                if (!empty($sectionextra->sectionbase->component)) {
                    $ignoredsections[$id] = $sectionextra;
                    unset($sectionsextra[$id]);
                }
            }
        }

        $originextraarray = [];
        $originlevel = null;
        foreach ($origins as $origin) {
            // Locate origin section in sections array.
            if (!($originextra = $sectionsextra[$origin->id] ?? null)) {
                throw new moodle_exception('sectionnotexist');
            }

            // We can't move section position 0.
            if ($originextra->section < 1) {
                throw new moodle_exception('cannotcreateorfindstructs');
            }

            if (!isset($originlevel)) {
                $originlevel = $originextra->levelsan;
            } else {
                if ($originextra->levelsan != $originlevel) {
                    throw new moodle_exception('cannotcreateorfindstructs');
                }
            }

            // Extract origin sections.
            for (
                $originsubkey = $originextra->id; /* ... */
                ($originsubkey == $originextra->id)
                    || $originsubkey && ($sectionsextra[$originsubkey]->levelsan > $originextra->levelsan); /* ... */
                $originsubkey = $originextraarray[$originsubkey]->nextanyid
            ) {
                $originextraarray[$originsubkey] = $sectionsextra[$originsubkey];
                unset($sectionsextra[$originsubkey]);
            }
        }

        // Find target position and extract remaining sections.
        $target->level = $target->level ?? $originlevel;
        $parentextra = null;
        $prevextra = null;
        $found = false;
        $appendextraarray = [];
        $newposition = 0;
        foreach ($sectionsextra as $id => $sectionextra) {
            if ($found) {
                // Target position already found, extract remaining sections.
                $appendextraarray[$id] = $sectionextra;
                unset($sectionsextra[$id]);
            } else if (isset($target->parentid) && ($sectionextra->id == $target->parentid)) {
                // Reached the target parent section, remember it.
                $parentextra = $sectionextra;
                if ($target->level <= $parentextra->levelsan) {
                    // The moved section can not be a child of the specified parent.
                    throw new moodle_exception('cannotcreateorfindstructs');
                }
            } else if (isset($target->prevupid) && ($sectionextra->id == $target->prevupid)) {
                // Reached the target previous section, remember it.
                $prevextra = $sectionextra;
                if ($target->level < $prevextra->levelsan) {
                    // The moved section can not have the specified section as its previous.
                    throw new moodle_exception('cannotcreateorfindstructs');
                }
            } else if (
                isset($parentextra)
                    && (($sectionextra->levelsan < $target->level) || ($sectionextra->levelsan <= $parentextra->levelsan))
                || isset($prevextra) && ($sectionextra->levelsan <= $target->level)
                || isset($target->nextupid) && ($sectionextra->id == $target->nextupid)
                || isset($target->section) && ($newposition == $target->section)
            ) {
                // Reached the last position in a specified parent in which the moved section would be a (direct) child,
                // or the appropriate position after a specified previous section,
                // or the position before a specified next section.
                if ($sectionextra->levelsan > $target->level) {
                    // If inserted here, the moved section would absorb other sections.
                    throw new moodle_exception('cannotcreateorfindstructs');
                }
                $appendextraarray[$id] = $sectionextra;
                unset($sectionsextra[$id]);
                $found = true;
            }
            $newposition++;
        }
        if (
            isset($parentextra) || isset($prevextra)
            || property_exists($target, 'nextupid') && ($target->nextupid == null)
            || isset($target->section) && ($newposition == $target->section)
        ) {
            // If a specified parent or previous was found, but no position within the section list was appropriate,
            // the appropriate position must be the end of the section list.
            $found = true;
        }
        if (!$found) {
            throw new moodle_exception('sectionnotexist');
        }

        $sections = [];

        // Clone pre-target sections (to avoid cross-linking),
        // and check if the target location's parent is visible.
        $parentvisible = true;
        foreach ($sectionsextra as $id => $sectionextra) {
            $sections[$id] = new stdClass();
            $sections[$id]->id = $id;
            $sections[$id]->visible = $sectionextra->visiblesan;
            $sections[$id]->level = max($sectionextra->levelsan, 0);
            if ($sectionextra->levelsan < $target->level) {
                $parentvisible = $sectionextra->visiblesan;
            }
        }

        // Append moved sections.
        $levelchange = $target->level - $originlevel;
        foreach ($originextraarray as $id => $sectionextra) {
            $sections[$id] = new stdClass();
            $sections[$id]->id = $id;
            $sections[$id]->visible = $sectionextra->visiblesan && $parentvisible;
            $sections[$id]->level = (($sectionextra->levelsan >= FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC)
                                            && ($sectionextra->levelsan != $originlevel)) ?
                                        FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC       // Don't change topic level.
                                        : max($sectionextra->levelsan + $levelchange, 0);
        }

        // Append rest of array.
        foreach ($appendextraarray as $id => $sectionextra) {
            $sections[$id] = new stdClass();
            $sections[$id]->id = $id;
            $sections[$id]->visible = $sectionextra->visiblesan;
            $sections[$id]->level = max($sectionextra->levelsan, 0);
        }

        // Append ignored sections.
        foreach ($ignoredsections as $id => $sectionextra) {
            $sections[$id] = new stdClass();
            $sections[$id]->id = $id;
        }

        // Renumber positions.
        $position = 0;
        foreach ($sections as $section) {
            $section->section = $position;
            $position++;
        }

        return $sections;
    }
}
