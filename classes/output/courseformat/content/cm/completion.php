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

namespace format_multitopic\output\courseformat\content\cm;

use core_course\output\activity_completion;
use stdClass;
use core_completion\cm_completion_details;
use core_courseformat\output\local\content\cm\completion as completion_base;

/**
 * Base class to render course module completion.
 *
 * @package   format_multitopic
 * @copyright 2024 James Calder and Otago Polytechnic
 * @copyright based on work by 2023 Mikel Martin <mikel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion extends completion_base {

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass|null data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): ?stdClass {
        global $USER;

        $result = parent::export_for_template($output);

        if (!$this->format->show_activity_editor_options($this->mod)) {
            return null;
        }

        $course = $this->mod->get_course();

        $showcompletionconditions = $course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS;
        $completiondetails = cm_completion_details::get_instance($this->mod, $USER->id, $showcompletionconditions);

        $showcompletioninfo = $completiondetails->has_completion() &&
            ($showcompletionconditions || $completiondetails->show_manual_completion());
        if (!$showcompletioninfo) {
            return null;
        }

        $completion = new activity_completion($this->mod, $completiondetails);
        $completiondata = $completion->export_for_template($output);

        if ($completiondata->isautomatic || ($completiondata->ismanual && !$completiondata->istrackeduser)) {
            // MDL-72526 test.
            $completiondata->completiondialog = $this->get_completion_dialog($output, $completiondata);
        }

        return $result;
    }

}
