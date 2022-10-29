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
 * Contains the default section controls output class.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2012 Dan Poltawski
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_multitopic\output\courseformat\content\section;

use context_course;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;

/**
 * Base class to render a course section menu.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2012 Dan Poltawski
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /** @var course_format the course format class */
    protected $format;

    /** @var section_info the course section class */
    protected $section;

    /**
     * Generate the edit control items of a section.
     *
     * This method must remain public until the final deprecation of section_edit_control_items.
     *
     * @return array of edit control items
     */
    public function section_control_items() {
        global $USER;

        $format = $this->format;
        $section = $format->fmt_get_section($this->section);                    // CHANGED.
        $onsectionpage = $section->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; // ADDED.
        $course = $format->get_course();
        // REMOVED sectionreturn.
        $user = $USER;

        $coursecontext = context_course::instance($course->id);
        $numsections = $format->get_last_section_number();
        $isstealth = false;                                                     // CHANGED: Don't use numsections.

        $baseurl = course_get_url($course, $section, ['fmtedit' => true]);      // CHANGED.
        $baseurl->param('sesskey', sesskey());

        $controls = [];

        if (!$isstealth && has_capability('moodle/course:update', $coursecontext, $user)) {
            if ($section->section > 0
                && get_string_manager()->string_exists('editsection', 'format_' . $format->get_format())) {
                $streditsection = get_string('editsection', 'format_' . $format->get_format());
            } else {
                $streditsection = get_string('editsection');
            }

            $controls['edit'] = [
                'url'   => new \moodle_url('/course/format/multitopic/_course_editsection.php',
                                         ['id' => $section->id]),               // CHANGED.
                'icon' => 'i/settings',
                'name' => $streditsection,
                'pixattr' => ['class' => ''],
                'attr' => ['class' => 'icon edit'],
            ];
        }

        if ($section->section) {
            $url = clone($baseurl);
            if (!$isstealth) {
                if (has_capability('moodle/course:sectionvisibility', $coursecontext, $user)) {
                    if ($section->visible) { // Show the hide/show eye.
                        $strhidefromothers = get_string_manager()->string_exists('hidefromothers', 'format_' . $course->format) ?
                                                get_string('hidefromothers', 'format_' . $course->format)
                                                : get_string('hide');           // CHANGED.
                        $url->param('hideid', $section->id);                    // CHANGED.
                        $controls['visiblity'] = [
                            'url' => $url,
                            'icon' => 'i/hide',
                            'name' => $strhidefromothers,
                            'pixattr' => ['class' => ''],
                            'attr' => [
                                'class' => 'icon editing_showhide',
                            ],  // REMOVED section return & AJAX action.
                        ];
                        // ADDED: AJAX action added back for topic-level sections only.
                        if (!$onsectionpage) {
                            $controls['visiblity']['attr']['data-action'] = 'hide';
                        }
                        // END ADDED.
                    } else if ($section->parentvisiblesan) {                    // CHANGED: Only allow unhide if parent is visible.
                        $strshowfromothers = get_string_manager()->string_exists('showfromothers', 'format_' . $course->format) ?
                                                get_string('showfromothers', 'format_' . $course->format)
                                                : get_string('show');           // CHANGED.
                        $url->param('showid',  $section->id);                   // CHANGED.
                        $controls['visiblity'] = [
                            'url' => $url,
                            'icon' => 'i/show',
                            'name' => $strshowfromothers,
                            'pixattr' => ['class' => ''],
                            'attr' => [
                                'class' => 'icon editing_showhide',
                            ],  // REMOVED section return & AJAX action.
                        ];
                        // ADDED: AJAX action added back for topic-level sections only.
                        if (!$onsectionpage) {
                            $controls['visiblity']['attr']['data-action'] = 'show';
                        }
                        // END ADDED.
                    }
                }

                // INCLUDED funct section_control_items if (!$sectionreturn) .
                if ($onsectionpage) {                                           // CHANGED.
                    if (has_capability('moodle/course:movesections', $coursecontext, $user)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext, $user)
                        && has_capability('moodle/course:update', $coursecontext, $user)) {
                        $url = clone($baseurl);
                        if ($section->levelsan - 1 > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT) { // Raise section. // CHANGED.
                            // CHANGED.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->parentid);
                            $url->param('destlevel', $section->levelsan - 1);
                            $strmovelevelup = get_string_manager()->string_exists('move_level_up', 'format_multitopic') ?
                                                get_string('move_level_up', 'format_multitopic') : get_string('moveup');
                            // END CHANGED.
                            $controls['movelevelup'] = [                        // CHANGED.
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmovelevelup,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmovelevelup'],   // CHANGED.
                            ];
                        }

                        $url = clone($baseurl);
                        if ($section->pagedepth + 1 <= FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE) { // Lower section. CHANGED.
                            // CHANGED.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->prevupid);
                            $url->param('destlevel', $section->levelsan + 1);
                            $strmoveleveldown = get_string_manager()->string_exists('move_level_down', 'format_multitopic') ?
                                                get_string('move_level_down', 'format_multitopic') : get_string('movedown');
                            // END CHANGED.
                            $controls['moveleveldown'] = [                      // CHANGED.
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmoveleveldown,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmoveleveldown'], // CHANGED.
                            ];
                        }
                    }
                    if (has_capability('moodle/course:movesections', $coursecontext, $user)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext, $user)) {
                        $url = clone($baseurl);
                        // CHANGED: Replaced up with previous.
                        if (isset($section->prevupid) && $section->prevupid != course_get_format($course)->fmtrootsectionid) {
                                // Add a arrow to move section back.
                            $url->param('sectionid', $section->id);
                            $url->param('destnextupid', $section->prevupid);
                            $strmovepageprev = get_string_manager()->string_exists('move_page_prev', 'format_multitopic') ?
                                                get_string('move_page_prev', 'format_multitopic') : get_string('moveleft');
                            $controls['moveprev'] = [
                                'url' => $url,
                                'icon' => 't/left',
                                'name' => $strmovepageprev,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmovepageprev'],
                            ];
                        }
                        // END CHANGED.

                        $url = clone($baseurl);
                        // CHANGED: Replaced down with next.
                        if (isset($section->nextupid)) { // Add a arrow to move section forward.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->nextupid);
                            $strmovepagenext = get_string_manager()->string_exists('move_page_next', 'format_multitopic') ?
                                                get_string('move_page_next', 'format_multitopic') : get_string('moveright');
                            $controls['movenext'] = [
                                'url' => $url,
                                'icon' => 't/right',
                                'name' => $strmovepagenext,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmovepagenext'],
                            ];
                        }
                        // END CHANGED.
                    }
                } else { // END INCLUDED.
                    // Move sections left and right.
                    if (has_capability('moodle/course:movesections', $coursecontext, $user)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext, $user)) {
                        $url = clone($baseurl);
                        // CHANGED: Replaced up with to previous page.
                        if ($section->prevpageid) { // Add a arrow to move section to previous page.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->prevpageid);
                            $strmovetoprevpage = get_string_manager()->string_exists('move_to_prev_page', 'format_multitopic') ?
                                                 get_string('move_to_prev_page', 'format_multitopic') : get_string('moveleft');
                            $controls['movetoprevpage'] = [
                                'url' => $url,
                                'icon' => 't/left',
                                'name' => $strmovetoprevpage,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmovetoprevpage'],
                            ];
                        }
                        // END CHANGED.

                        $url = clone($baseurl);
                        // CHANGED: Replaced down with to next page.
                        if ($section->nextpageid) { // Add a arrow to move section to next page.
                            $url->param('sectionid', $section->id);
                            $url->param('destparentid', $section->nextpageid);
                            $strmovetonextpage = get_string_manager()->string_exists('move_to_next_page', 'format_multitopic') ?
                                                 get_string('move_to_next_page', 'format_multitopic') : get_string('moveright');
                            $controls['movetonextpage'] = [
                                'url' => $url,
                                'icon' => 't/right',
                                'name' => $strmovetonextpage,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon fmtmovetonextpage'],
                            ];
                        }
                        // END CHANGED.

                    }
                    if (has_capability('moodle/course:movesections', $coursecontext, $user)
                        && has_capability('moodle/course:sectionvisibility', $coursecontext, $user)) {
                        $url = clone($baseurl);
                        if ($section->prevupid != $section->parentid) { // Add a arrow to move section up.
                            $url->param('sectionid', $section->id);
                            $url->param('destnextupid', $section->prevupid);
                            $strmoveup = get_string('moveup');
                            $controls['moveup'] = [
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmoveup,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon moveup whilenostate'],
                            ];
                        }

                        $url = clone($baseurl);
                        if ($section->nextupid != $section->nextpageid) { // Add a arrow to move section down.
                            $url->param('sectionid', $section->id);
                            $url->param('destprevupid', $section->nextupid);
                            $strmovedown = get_string('movedown');
                            $controls['movedown'] = [
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmovedown,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'icon movedown whilenostate'],
                            ];
                        }
                    }
                }
            }

            if (\format_multitopic_course_can_delete_section($course, $section)) {
                if (get_string_manager()->string_exists('deletesection', 'format_' . $course->format)) {
                    $strdelete = get_string('deletesection', 'format_' . $course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new \moodle_url(
                    '/course/format/multitopic/_course_editsection.php',
                    [
                        'id' => $section->id,
                        // REMOVED: section return.
                        'delete' => 1,
                        'sesskey' => sesskey()
                    ]
                );
                $controls['delete'] = [
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'icon editing_delete'
                    ],  // REMOVED: AJAX action.
                ];
                // TODO: Add AJAX action added back for topic-level sections?
            }
        }

        return $controls;
    }
}
