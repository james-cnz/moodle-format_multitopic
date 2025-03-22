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
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_multitopic\output\courseformat\content\section;

use core\output\action_menu\link;
use core\output\action_menu\link_secondary;
use core\output\pix_icon;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;
use core\url;

/**
 * Base class to render a course section menu.
 *
 * @package   format_multitopic
 * @copyright 2019 onwards James Calder and Otago Polytechnic
 * @copyright based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /** @var \format_multitopic\section_info_extra Multitopic-specific section information */
    protected $fmtsectionextra;

    /** @var bool Whether we are dealing with a page section */
    protected $fmtonsectionpage;

    /** @var url Base URL */
    protected $fmtbaseurl;

    /**
     * Constructor.
     *
     * @param \format_multitopic $format the course format
     * @param \section_info $section the section info
     */
    public function __construct(\format_multitopic $format, \section_info $section) {
        parent::__construct($format, $section);
        $this->fmtsectionextra = $format->fmt_get_section_extra($section);
        $this->fmtonsectionpage = ($this->fmtsectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC); // ADDED.
        $this->fmtbaseurl = course_get_url($format->get_course(), $section, ['fmtedit' => true]);   // CHANGED.
        $this->fmtbaseurl->param('sesskey', sesskey());
    }

    /**
     * Generate the edit control items of a section.
     *
     * @return array of edit control items
     */
    public function section_control_items() {
        $controls = parent::section_control_items();

        $controls = $this->add_control_after($controls, 'movesection', 'movelevelup', $this->get_section_movelevelup_item());
        $controls = $this->add_control_after($controls, 'movelevelup', 'moveleveldown', $this->get_section_moveleveldown_item());
        $controls = $this->add_control_after($controls, 'moveleveldown', 'moveprev', $this->get_section_moveprev_item());
        $controls = $this->add_control_after($controls, 'moveprev', 'movenext', $this->get_section_movenext_item());
        $controls = $this->add_control_after($controls, 'movenext', 'movetoprevpage', $this->get_section_movetoprevpage_item());
        $controls = $this->add_control_after($controls, 'movetoprevpage',
                                            'movetonextpage', $this->get_section_movetonextpage_item());
        $controls = $this->add_control_after($controls, 'movetonextpage', 'moveup', $this->get_section_moveup_item());
        $controls = $this->add_control_after($controls, 'moveup', 'movedown', $this->get_section_movedown_item());

        return $controls;
    }

    /**
     * Retrieves the view item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_view_item(): ?link {
        return null;
    }

    /**
     * Retrieves the edit item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_edit_item(): ?link {
        $link = parent::get_section_edit_item();

        if ($link) {
            $url = new url(
                '/course/format/multitopic/_course_editsection.php',            // CHANGED.
                ['id' => $this->section->id]
            );
            $link->url = $url;
        }

        return $link;
    }

    /**
     * Retrieves the duplicate item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_duplicate_item(): ?link {
        $link = null;

        if (!$this->fmtonsectionpage) {
            $link = parent::get_section_duplicate_item();
        }

        return $link;
    }

    /**
     * Retrieves the visibility item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_visibility_item(): ?link {
        $link = parent::get_section_visibility_item();

        if ($link) {
            unset($link->attributes['data-sectionreturn']);
            if ($this->fmtonsectionpage) {
                unset($link->attributes['data-action']);
            }
            if ($this->section->visible) {
                $action = 'hide';
            } else if (!$this->fmtsectionextra->parentvisiblesan) {
                $link = null;
            } else {
                $action = 'show';
            }
        }

        if ($link) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    $action . 'id' => $this->section->id,
                    'sesskey' => sesskey(),
                ]
            );
            $link->url = $url;
        }

        return $link;
    }

    /**
     * Retrieves the movesection item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movesection_item(): ?link {
        $link = null;

        if ($this->section->section && !$this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'movesection' => $this->section->section,
                    'section' => $this->section->section,
                ]
            );
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/dragdrop', ''),
                text: get_string('move'),
                attributes: [
                    // This tool requires ajax and will appear only when the frontend state is ready.
                    'class' => 'move waitstate',
                    'data-action' => 'moveSection',
                    'data-id' => $this->section->id,
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movelevelup item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movelevelup_item(): ?link {
        $link = null;

        if ($this->section->section && $this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && has_capability('moodle/course:update', $this->coursecontext)
            && ($this->fmtsectionextra->levelsan - 1 > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destprevupid' => $this->fmtsectionextra->parentid,
                    'destlevel' => $this->fmtsectionextra->levelsan - 1,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovelevelup = get_string_manager()->string_exists('move_level_up', 'format_multitopic') ?
                                get_string('move_level_up', 'format_multitopic') : get_string('moveup');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/up', ''),
                text: $strmovelevelup,
                attributes: ['class' => 'fmtmovelevelup'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveleveldown item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveleveldown_item(): ?link {
        $link = null;

        if ($this->section->section && $this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && has_capability('moodle/course:update', $this->coursecontext)
            && ($this->fmtsectionextra->pagedepth + 1 <= FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destparentid' => $this->fmtsectionextra->prevupid,
                    'destlevel' => $this->fmtsectionextra->levelsan + 1,
                    'sesskey' => sesskey(),
                ]
            );
            $strmoveleveldown = get_string_manager()->string_exists('move_level_down', 'format_multitopic') ?
                                get_string('move_level_down', 'format_multitopic') : get_string('movedown');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/down', ''),
                text: $strmoveleveldown,
                attributes: ['class' => 'fmtmoveleveldown'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveprev item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveprev_item(): ?link {
        $link = null;

        if ($this->section->section && $this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && isset($this->fmtsectionextra->prevupid) && ($this->fmtsectionextra->prevupid != $this->format->fmtrootsectionid)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destnextupid' => $this->fmtsectionextra->prevupid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovepageprev = get_string_manager()->string_exists('move_page_prev', 'format_multitopic') ?
                                get_string('move_page_prev', 'format_multitopic') : get_string('moveleft');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('t/left', ''),
                text: $strmovepageprev,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'fmtmovepageprev whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movenext item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movenext_item(): ?link {
        $link = null;

        if ($this->section->section && $this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && isset($this->fmtsectionextra->nextupid)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destprevupid' => $this->fmtsectionextra->nextupid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovepagenext = get_string_manager()->string_exists('move_page_next', 'format_multitopic') ?
                                get_string('move_page_next', 'format_multitopic') : get_string('moveright');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('t/right', ''),
                text: $strmovepagenext,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'fmtmovepagenext whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movetoprevpage item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movetoprevpage_item(): ?link {
        $link = null;

        if ($this->section->section && !$this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtsectionextra->prevpageid
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destparentid' => $this->fmtsectionextra->prevpageid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovetoprevpage = get_string_manager()->string_exists('move_to_prev_page', 'format_multitopic') ?
                                    get_string('move_to_prev_page', 'format_multitopic') : get_string('moveleft');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('t/left', ''),
                text: $strmovetoprevpage,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'fmtmovetoprevpage whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movetonextpage item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movetonextpage_item(): ?link {
        $link = null;

        if ($this->section->section && !$this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtsectionextra->nextpageid
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destparentid' => $this->fmtsectionextra->nextpageid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovetonextpage = get_string_manager()->string_exists('move_to_next_page', 'format_multitopic') ?
                                    get_string('move_to_next_page', 'format_multitopic') : get_string('moveright');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('t/right', ''),
                text: $strmovetonextpage,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'fmtmovetonextpage whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveup item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveup_item(): ?link {
        $link = null;

        if ($this->section->section && !$this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && ($this->fmtsectionextra->prevupid != $this->fmtsectionextra->parentid)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destnextupid' => $this->fmtsectionextra->prevupid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmoveup = get_string('moveup');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/up', ''),
                text: $strmoveup,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'moveup whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movedown item for the section control menu.
     *
     * @deprecated since Moodle 5.0 MDL-83527
     * @todo Final deprecation in Moodle 6.0 MDL-83530
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movedown_item(): ?link {
        $link = null;

        if ($this->section->section && !$this->fmtonsectionpage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && ($this->fmtsectionextra->nextupid != $this->fmtsectionextra->nextpageid)
        ) {
            $url = new url(
                $this->fmtbaseurl,
                [
                    'sectionid' => $this->section->id,
                    'destprevupid' => $this->fmtsectionextra->nextupid,
                    'sesskey' => sesskey(),
                ]
            );
            $strmovedown = get_string('movedown');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/down', ''),
                text: $strmovedown,
                attributes: [
                    // This tool disappears when the state is ready whilenostate.
                    'class' => 'movedown whilenostate',
                ],
            );
        }

        return $link;
    }

    /**
     * Retrieves the permalink item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_permalink_item(): ?link {
        $link = parent::get_section_permalink_item();

        if ($link) {
            $link->url = course_get_url($this->format->get_course(), $this->section);
        }

        return $link;
    }

    /**
     * Retrieves the delete item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_delete_item(): ?link {
        $link = parent::get_section_delete_item();

        if ($link) {
            $url = new url(
                '/course/format/multitopic/_course_editsection.php',
                [
                    'id' => $this->section->id,
                    // REMOVED: section return.
                    'delete' => 1,
                    'sesskey' => sesskey(),
                ]
            );
            $link->url = $url;
            if ($this->fmtonsectionpage) {
                unset($link->attributes['data-action']);
            }
        }

        return $link;
    }
}
