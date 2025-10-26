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
 * Contains the section course format output class.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_multitopic\output\courseformat\content;

use core_courseformat\output\local\content\addsection as addsection_base;
use core_courseformat\base as course_format;
use section_info;
use stdClass;

/**
 * Class to render a course add section button.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addsection extends addsection_base {
    /**
     * @var section_info|null the target section information
     * Redeclaration deprecated since Moodle 5.1, see MDL-85284.
     */
    protected ?section_info $targetsection;

    /**
     * Constructor.
     *
     * Redeclaration deprecated since Moodle 5.1, see MDL-85284.
     *
     * @param course_format $format the course format
     * @param section_info|null $targetsection the target targetsection information
     */
    public function __construct(course_format $format, ?section_info $targetsection = null) {
        parent::__construct($format);
        $this->targetsection = $targetsection;
    }

    /**
     * Get the add section button data.
     *
     * Current course format does not have 'numsections' option but it has multiple sections support.
     * Display the "Add section" link that will insert a section in the end.
     * Note to course format developers: inserting sections in the other positions should check both
     * capabilities 'moodle/course:update' and 'moodle/course:movesections'.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @param int $lastsection the last section number
     * @param int $maxsections the maximum number of sections (deprecated since Moodle 5.1)
     * @return stdClass data context for a mustache template
     */
    protected function get_add_section_data(\renderer_base $output, int $lastsection, int $maxsections = 0): stdClass {
        global $CFG;
        $format = $this->format;
        $course = $format->get_course();
        if ($CFG->version < 2025082900) {
            $data = parent::get_add_section_data($output, $lastsection, $maxsections);
        } else {
            $data = new stdClass();
        }

        if (get_string_manager()->string_exists('addsectiontopic', 'format_' . $course->format)) {
            $addstring = get_string('addsectiontopic', 'format_' . $course->format);
        } else {
            $addstring = get_string('addsections');
        }

        if ($CFG->version < 2025082900) {
            $params = [
                'courseid' => $course->id, // CHANGED.
                'insertlevel' => FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC,
                'sesskey' => sesskey(),
                'returnurl' => new \moodle_url(
                    "/course/view.php?id={$course->id}"
                    . (($format->get_sectionid() != $format->fmtrootsectionid) ?
                        "&sectionid={$format->get_sectionid()}" : "")
                ),
            ];
            if ($this->targetsection) {
                $params['insertprevupid'] = $this->targetsection->id;
            } else {
                $params['insertparentid'] = $format->get_sectionid();
            }

            $data->addsections->url = new \moodle_url('/course/format/multitopic/_course_changenumsections.php', $params);
            $data->addsections->title = $addstring;
        } else {
            if ($this->targetsection) {
                $action = 'section_add';
                $targetsectionid = $this->targetsection->id;
                $returnsection = $this->targetsection;
            } else {
                $action = 'fmt_section_add_into';
                $targetsectionid = $format->get_sectionid();
                $sectionsextra = $format->fmt_get_sections_extra();
                $foundparent = false;
                $lastchildid = null;
                foreach ($sectionsextra as $sectionextra) {
                    if ($sectionextra->id == $targetsectionid) {
                        $foundparent = true;
                    } else if ($foundparent && $sectionextra->levelsan < 2) {
                        break;
                    }
                    if ($foundparent) {
                        $lastchildid = $sectionextra->id;   
                    }
                }
                $returnsection = $format->get_modinfo()->get_section_info_by_id($lastchildid);
            }
            $data->addsections = (object) [
                'url' => $this->format->get_update_url(
                    action: $action,
                    targetsectionid: $targetsectionid,
                    targetcmid: 2, // Level.
                    returnurl: $format->get_view_url($returnsection),
                ),
                'title' => $addstring,
                'newsection' => $lastsection + 1,
                'canaddsection' => true,
            ];
        }

        return $data;
    }
}
