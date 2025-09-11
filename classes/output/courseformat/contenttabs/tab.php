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

use core\output\tabobject as tab_base;

/**
 * Stores one tab
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_multitopic
 * @copyright 2025 James Calder and Otago Polytechnic
 * @copyright based on work by Marina Glancy
 */
class tab extends tab_base {
    /**
     * Export for template.
     *
     * @param \renderer_base $output Renderer.
     * @return object
     */
    public function export_for_template(\renderer_base $output): object {
        $eft = parent::export_for_template($output);

        if ($this->inactive) {
            $link = null;
        } else {
            $link = $this->link;
        }

        preg_match('/^tab_id_(\d+)_l(\d+)(_add)?$/', $this->id, $matches);
        $sectionid = !isset($matches[3]) ? $matches[1] : null;
        $level = $matches[2];

        $eft->link = is_object($link) ? $link->out(false) : $link;
        $eft->level = $level;
        $eft->sectionid = $sectionid;
        return $eft;
    }
}
