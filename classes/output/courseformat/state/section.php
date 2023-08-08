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

namespace format_multitopic\output\courseformat\state;

use core_courseformat\output\local\state\section as base_section;

use core_availability\info_section;
use core_courseformat\base as course_format;
use section_info;
use renderable;
use stdClass;
use context_course;

/**
 * Contains the ajax update section structure.
 *
 * @package   format_multitopic
 * @copyright 2022 James Calder and Otago Polytechnic
 *            based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends base_section {

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        $data = parent::export_for_template($output);
        $section = $this->section;
        if (!isset($section->fmtdata)) {
            $section = $this->format->fmt_get_section($section);
        }
        $data->levelsan = $section->levelsan;
        $data->indent = ($section->section == 0) ? 0 : $section->levelsan;
        $data->pageid = ($section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC) ? $section->id : $section->parentid;
        $data->timed = $section->dateend && ($section->datestart < $section->dateend);
        $data->parentid = $section->parentid;
        $data->available = $section->available;
        $data->currentnestedlevel = $section->currentnestedlevel;
        $controlmenuclass = $this->format->get_output_classname('content\\section\\controlmenu');
        $controlmenu = new $controlmenuclass($this->format, $section);
        $data->controlmenu = $controlmenu->export_for_template($output);
        return $data;
    }

    /**
     * Return if the section can be selected for bulk editing.
     * @return bool if the section can be edited in bulk
     */
    protected function is_bulk_editable(): bool {
        $section = $this->section;
        return ($section->section != 0); // Should be levelsan >= 2, this but doesn't work.
    }

}
