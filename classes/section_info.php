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
 * Section info
 *
 * @package   format_multitopic
 * @copyright 2023 James Calder and Otago Polytechnic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_multitopic;

/**
 * Section info
 *
 * Data about a single section on a course. This contains the fields from the
 * course_sections table, plus additional data when required.
 * This class isn't actually used, it's just for static checking.
 *
 * @property-read int $level Section level
 * @property-read string $periodduration Time length of the topic
 * @property-read string $collapsible Whether the topic is collapsible
 */
class section_info extends \section_info {

    /** @var int Sanatised section level */
    public $levelsan;

    /** @var ?int ID of section's parent (previous section at a higher level) */
    public $parentid;

    /** @var ?int ID of the previous section at the same or higher level */
    public $prevupid;

    /** @var ?int ID of the previous section above topic level */
    public $prevpageid;

    /** @var ?int ID of the previous section at any level */
    public $prevanyid;

    /** @var ?int ID of the next section at the same or higher level */
    public $nextupid;

    /** @var ?int ID of the next section above topic level */
    public $nextpageid;

    /** @var ?int ID of the next section at any level */
    public $nextanyid;

    /** @var bool Whether this section has subsections */
    public $hassubsections;

    /** @var int The lowest level of subpages */
    public $pagedepth;

    /** @var int The lowest level of shown direct subpages */
    public $pagedepthdirect;

    /** @var bool Sanatised parent's visibility */
    public $parentvisiblesan;

    /** @var bool Sanatised visibility */
    public $visiblesan;

    /** @var ?int Section's start date */
    public $datestart;

    /** @var ?int Section's end date */
    public $dateend;

    /** @var int The level down to which this section contains the current section.
     *          A page-level section may be represented as multiple levels of tabs,
     *          and higher levels may contain the current section, while lower levels don't. */
    public $currentnestedlevel;

    /** @var bool Flag to indicate the presence of calculated properties */
    public $fmtdata;

}
