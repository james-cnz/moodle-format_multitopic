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
 * Contains the default activity title.
 *
 * This class is usually rendered inside the cmname inplace editable.
 *
 * @package   format_multitopic
 * @copyright 2024 James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_multitopic\output\courseformat\content\cm;

use cm_info;
use core_courseformat\base as course_format;
use section_info;
use core_courseformat\output\local\content\cm\title as title_base;

/**
 * Base class to render a course module title inside a course format.
 *
 * @package   format_multitopic
 * @copyright 2024 James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class title extends title_base {

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @param cm_info $mod the course module ionfo
     * @param array $displayoptions optional extra display options
     * @param bool|null $editable force editable value
     */
    public function __construct(
        course_format $format,
        section_info $section,
        cm_info $mod,
        array $displayoptions = [],
        ?bool $editable = null
    ) {
        parent::__construct($format, $section, $mod, $displayoptions, $editable);
        $section = $this->section;  // MDL-72526 test.
    }

}
