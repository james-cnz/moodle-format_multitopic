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

namespace format_multitopic\output\courseformat\content\section;

use core_courseformat\output\local\content\section\visibility as visibility_base;
use section_info;

/**
 * Class to render a section visibility inside a course format.
 *
 * @package   format_multitopic
 * @copyright 2025 James Calder and Otago Polytechnic
 * @copyright based on work by 2024 Laurent David <laurent.david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility extends visibility_base {
    /** @var \format_multitopic\section_info_extra Multitopic-specific section information */
    protected $fmtsectionextra;

    /**
     * Constructor.
     *
     * @param \format_multitopic $format the course format
     * @param \section_info $section the section info
     */
    public function __construct(\format_multitopic $format, section_info $section) {
        parent::__construct($format, $section);
        $this->fmtsectionextra = $format->fmt_get_section_extra($section);
    }

    /**
     * Check if the section visibility is editable.
     *
     * @return bool
     */
    #[\Override]
    protected function is_section_visibility_editable(): bool {
        $result = parent::is_section_visibility_editable();
        if ($this->section->component) {
            return $result;
        }
        return $this->fmtsectionextra->parentvisiblesan && $result;
    }
}
