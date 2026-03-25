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
    protected $fmtispage;

    /** @var url Return URL */
    protected $fmtreturnurl;

    /**
     * Constructor.
     *
     * @param \format_multitopic $format the course format
     * @param \section_info $section the section info
     */
    public function __construct(\format_multitopic $format, \section_info $section) {
        parent::__construct($format, $section);
        $this->fmtsectionextra = $format->fmt_get_section_extra($section);
        $this->fmtispage = ($this->fmtsectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC); // ADDED.
        $pagesection = $this->fmtispage ?
                        $section
                        : $format->get_modinfo()->get_section_info_by_id($this->fmtsectionextra->parentid);
        $this->baseurl = $format->get_view_url($pagesection);
        $this->fmtreturnurl = $format->get_view_url($section);
    }

    /**
     * Generate the edit control items of a section.
     *
     * @return array of edit control items
     */
    #[\Override]
    public function section_control_items() {
        $controls = parent::section_control_items();

        // There's a separate class for delegated control menus, so we probably don't need this, but just in case.
        if ($this->section->component) {
            return $controls;
        }

        $controls = $this->add_control_after($controls, 'movesection', 'movelevelup', $this->get_section_movelevelup_item());
        $controls = $this->add_control_after($controls, 'movelevelup', 'moveleveldown', $this->get_section_moveleveldown_item());
        $controls = $this->add_control_after($controls, 'moveleveldown', 'moveprev', $this->get_section_moveprev_item());
        $controls = $this->add_control_after($controls, 'moveprev', 'movenext', $this->get_section_movenext_item());
        $controls = $this->add_control_after($controls, 'movenext', 'movetoprevpage', $this->get_section_movetoprevpage_item());
        $controls = $this->add_control_after(
            $controls,
            'movetoprevpage',
            'movetonextpage',
            $this->get_section_movetonextpage_item()
        );
        $controls = $this->add_control_after($controls, 'movetonextpage', 'moveup', $this->get_section_moveup_item());
        $controls = $this->add_control_after($controls, 'moveup', 'movedown', $this->get_section_movedown_item());

        return $controls;
    }

    /**
     * Retrieves the view item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    #[\Override]
    protected function get_section_view_item(): ?link {
        return null;
    }

    /**
     * Retrieves the edit item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    #[\Override]
    protected function get_section_edit_item(): ?link {
        $link = parent::get_section_edit_item();

        if ($link) {
            $link->url->remove_params('sr');
            $link->url->remove_params('returnurl');
        }

        return $link;
    }

    /**
     * Retrieves the duplicate item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    #[\Override]
    protected function get_section_duplicate_item(): ?link {
        global $CFG;
        $link = null;

        if (!$this->fmtispage) {
            $link = parent::get_section_duplicate_item();
            if ($link && ($CFG->version >= 2025053000)) {
                $link->url->param('returnurl', $this->fmtreturnurl);
            }
        }

        return $link;
    }

    /**
     * Retrieves the visibility item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    #[\Override]
    protected function get_section_visibility_item(): ?link {
        $link = parent::get_section_visibility_item();

        if ($link) {
            unset($link->attributes['data-sectionreturn']);
            if ($this->section->visible) {
                $stateaction = 'section_hide';
            } else if (!$this->fmtsectionextra->parentvisiblesan) {
                $link = null;
            } else {
                $stateaction = 'section_show';
            }
        }

        if ($link) {
            $link->url->param('returnurl', $this->fmtreturnurl);
        }

        return $link;
    }

    /**
     * Retrieves the movesection item for the section control menu.
     *
     * @return link|null The menu item if applicable, otherwise null.
     */
    #[\Override]
    protected function get_section_movesection_item(): ?link {
        $link = null;

        if (
            $this->section->section && !$this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
        ) {
            $url = new url(
                $this->baseurl,
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

        if (
            $this->section->section && $this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && has_capability('moodle/course:update', $this->coursecontext)
            && ($this->fmtsectionextra->levelsan - 1 > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT)
        ) {
            $stateaction = 'section_move_after';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->parentid,
                targetcmid: $this->fmtsectionextra->levelsan - 1, // Target level.
                returnurl: $this->fmtreturnurl,
            );
            $strmovelevelup = get_string_manager()->string_exists('move_level_up', 'format_multitopic') ?
                                get_string('move_level_up', 'format_multitopic') : get_string('moveup');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/up', ''),
                text: $strmovelevelup,
                attributes: [
                    'class' => 'fmtmovelevelup',
                    'data-action' => 'fmtPageRaise',
                    'data-id' => $this->section->id,
                ],
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

        if (
            $this->section->section && $this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && has_capability('moodle/course:update', $this->coursecontext)
            && ($this->fmtsectionextra->pagedepth + 1 <= FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE)
        ) {
            $stateaction = 'fmt_section_move_into';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->prevupid,
                targetcmid: $this->fmtsectionextra->levelsan + 1, // Target level.
                returnurl: $this->fmtreturnurl,
            );
            $strmoveleveldown = get_string_manager()->string_exists('move_level_down', 'format_multitopic') ?
                                get_string('move_level_down', 'format_multitopic') : get_string('movedown');
            $link = new link_secondary(
                url: $url,
                icon: new pix_icon('i/down', ''),
                text: $strmoveleveldown,
                attributes: [
                    'class' => 'fmtmoveleveldown',
                    'data-action' => 'fmtPageLower',
                    'data-id' => $this->section->id,
                ],
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

        if (
            $this->section->section && $this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && isset($this->fmtsectionextra->prevupid) && ($this->fmtsectionextra->prevupid != $this->format->fmtrootsectionid)
        ) {
            $stateaction = 'fmt_section_move_before';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->prevupid,
                returnurl: $this->fmtreturnurl,
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

        if (
            $this->section->section && $this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && isset($this->fmtsectionextra->nextupid)
        ) {
            $stateaction = 'section_move_after';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->nextupid,
                returnurl: $this->fmtreturnurl,
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

        if (
            $this->section->section && !$this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtsectionextra->prevpageid
        ) {
            $stateaction = 'fmt_section_move_into';
            $returnurl = course_get_url(
                $this->format->get_course(),
                $this->format->fmt_get_section_extra((object)['id' => $this->fmtsectionextra->prevpageid])->sectionbase
            );
            $returnurl->set_anchor(explode('#', $this->fmtreturnurl)[1]);
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->prevpageid,
                returnurl: $returnurl,
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

        if (
            $this->section->section && !$this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtsectionextra->nextpageid
        ) {
            $stateaction = 'fmt_section_move_into';
            $returnurl = course_get_url(
                $this->format->get_course(),
                $this->format->fmt_get_section_extra((object)['id' => $this->fmtsectionextra->nextpageid])->sectionbase
            );
            $returnurl->set_anchor(explode('#', $this->fmtreturnurl)[1]);
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->nextpageid,
                returnurl: $returnurl,
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

        if (
            $this->section->section && !$this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && ($this->fmtsectionextra->prevupid != $this->fmtsectionextra->parentid)
        ) {
            $stateaction = 'fmt_section_move_before';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->prevupid,
                returnurl: $this->fmtreturnurl,
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

        if (
            $this->section->section && !$this->fmtispage
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && ($this->fmtsectionextra->nextupid != $this->fmtsectionextra->nextpageid)
        ) {
            $stateaction = 'section_move_after';
            $url = $this->format->get_update_url(
                action: $stateaction,
                ids: [$this->section->id],
                targetsectionid: $this->fmtsectionextra->nextupid,
                returnurl: $this->fmtreturnurl,
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
    #[\Override]
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
    #[\Override]
    protected function get_section_delete_item(): ?link {
        $link = parent::get_section_delete_item();

        if ($link) {
            $link->url->param(
                'returnurl',
                course_get_url(
                    $this->format->get_course(),
                    $this->format->fmt_get_section_extra((object)['id' => $this->fmtsectionextra->prevupid])->sectionbase
                )
            );
            if ($this->fmtispage) {
                unset($link->attributes['data-action']);
            }
        }

        return $link;
    }
}
