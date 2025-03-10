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

use core\context\course as context_course;
use core\output\action_menu\link_secondary as action_menu_link_secondary;
use core\output\pix_icon;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;
use core\url;

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

    /** @var \format_multitopic\section_info_extra Multitopic-specific section information */
    protected $fmtsectionextra;

    /** @var bool Whether we are on a section page */
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
        $this->fmtonsectionpage = $this->fmtsectionextra->levelsan < FORMAT_MULTITOPIC_SECTION_LEVEL_TOPIC; // ADDED.
        $this->fmtbaseurl = course_get_url($format->get_course(), $section, ['fmtedit' => true]);      // CHANGED.
        $this->fmtbaseurl->param('sesskey', sesskey());
    }

    /**
     * Generate the edit control items of a section.
     *
     * This method must remain public until the final deprecation of section_edit_control_items.
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
        $controls = $this->add_control_after($controls, 'movetoprevpage', 'movetonextpage', $this->get_section_movetonextpage_item());

        return $controls;
    }

    // view

    /**
     * Retrieves the view item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_view_item(): action_menu_link_secondary|null {
        return null;
    }

    /**
     * Retrieves the edit item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_edit_item(): action_menu_link_secondary|null {
        $link = parent::get_section_edit_item();

        if ($link) {
            $url = new url('/course/format/multitopic/_course_editsection.php',
                    ['id' => $this->section->id]);                                    // CHANGED.
            $link->url = $url;
        }

        return $link;
    }

    /**
     * Retrieves the duplicate item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_duplicate_item(): action_menu_link_secondary|null {
        $link = parent::get_section_duplicate_item();

        if ($link) {
            if ($this->fmtonsectionpage) {
                $link = null;
            }
        }

        return $link;
    }

    /**
     * Retrieves the visibility item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_visibility_item(): action_menu_link_secondary|null {
        $link = parent::get_section_visibility_item();

        if ($link) {
            $url = clone($this->fmtbaseurl);
            $strhidefromothers = get_string('hide');                            // CHANGED.
            $strshowfromothers = get_string('show');                            // CHANGED.
            if ($this->section->visible) { // Show the hide/show eye.
                $url->param('hideid',  $this->section->id);                     // CHANGED.
                $link->url = $url;
                $link->text = $strhidefromothers;
                unset($link->attributes['data-sectionreturn']);
                if ($this->fmtonsectionpage) {
                    unset($link->attributes['data-action']);
                }
                $link->attributes['data-swapname'] = $strshowfromothers;
            } else if (!$this->fmtsectionextra->parentvisiblesan) {
                $link = null;
            } else {
                $url->param('showid',  $this->section->id);                     // CHANGED.
                $link->url = $url;
                $link->text = $strshowfromothers;
                unset($link->attributes['data-sectionreturn']);
                if ($this->fmtonsectionpage) {
                    unset($link->attributes['data-action']);
                }
                $link->attributes['data-swapname'] = $strhidefromothers;
            }
        }

        return $link;
    }

    /**
     * Retrieves the movesection item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movesection_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && !$this->fmtonsectionpage
        ) {
            // This tool will appear only when the state is ready.
            $url = clone ($this->fmtbaseurl);
            $url->param('movesection', $this->section->section);
            $url->param('section', $this->section->section);
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('i/dragdrop', ''),
                text: get_string('move', 'moodle'),
                attributes: [
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
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movelevelup_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtonsectionpage
            && has_capability('moodle/course:update', $this->coursecontext)
            && $this->fmtsectionextra->levelsan - 1 > FORMAT_MULTITOPIC_SECTION_LEVEL_ROOT
        ) { // Raise section.
            $url = clone($this->fmtbaseurl);
            // CHANGED.
            $url->param('sectionid', $this->section->id);
            $url->param('destprevupid', $this->fmtsectionextra->parentid);
            $url->param('destlevel', $this->fmtsectionextra->levelsan - 1);
            $strmovelevelup = get_string_manager()->string_exists('move_level_up', 'format_multitopic') ?
                                get_string('move_level_up', 'format_multitopic') : get_string('moveup');
            // END CHANGED.
            $link = new action_menu_link_secondary(                             // CHANGED.
                url: $url,
                icon: new pix_icon('i/up', ''),
                text: $strmovelevelup,
                attributes: ['class' => 'fmtmovelevelup'],                      // CHANGED.
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveleveldown item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveleveldown_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtonsectionpage
            && has_capability('moodle/course:update', $this->coursecontext)
            && $this->fmtsectionextra->pagedepth + 1 <= FORMAT_MULTITOPIC_SECTION_LEVEL_PAGE_USE
        ) { // Lower section. CHANGED.
            // CHANGED.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destparentid', $this->fmtsectionextra->prevupid);
            $url->param('destlevel', $this->fmtsectionextra->levelsan + 1);
            $strmoveleveldown = get_string_manager()->string_exists('move_level_down', 'format_multitopic') ?
                                get_string('move_level_down', 'format_multitopic') : get_string('movedown');
            // END CHANGED.
            $link = new action_menu_link_secondary(                             // CHANGED.
                url: $url,
                icon: new pix_icon('i/down', ''),
                text: $strmoveleveldown,
                attributes: ['class' => 'fmtmoveleveldown'],                    // CHANGED.
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveprev item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveprev_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtonsectionpage
            && isset($this->fmtsectionextra->prevupid) && $this->fmtsectionextra->prevupid != $this->format->fmtrootsectionid
        ) {
            // Add a arrow to move section back.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destnextupid', $this->fmtsectionextra->prevupid);
            $strmovepageprev = get_string_manager()->string_exists('move_page_prev', 'format_multitopic') ?
                                get_string('move_page_prev', 'format_multitopic') : get_string('moveleft');
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('t/left', ''),
                text: $strmovepageprev,
                attributes: ['class' => 'fmtmovepageprev'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movenext item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movenext_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && $this->fmtonsectionpage
            && isset($this->fmtsectionextra->nextupid)) { // Add a arrow to move section forward.
                $url = clone($this->fmtbaseurl);
                $url->param('sectionid', $this->section->id);
                $url->param('destprevupid', $this->fmtsectionextra->nextupid);
                $strmovepagenext = get_string_manager()->string_exists('move_page_next', 'format_multitopic') ?
                                    get_string('move_page_next', 'format_multitopic') : get_string('moveright');
                $link = new action_menu_link_secondary(
                    url: $url,
                    icon: new pix_icon('t/right', ''),
                    text: $strmovepagenext,
                    attributes: ['class' => 'fmtmovepagenext'],
                );
            }

        return $link;
    }

    /**
     * Retrieves the movetoprevpage item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movetoprevpage_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && !$this->fmtonsectionpage
            && $this->fmtsectionextra->prevpageid
        ) { // Add a arrow to move section to previous page.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destparentid', $this->fmtsectionextra->prevpageid);
            $strmovetoprevpage = get_string_manager()->string_exists('move_to_prev_page', 'format_multitopic') ?
                                    get_string('move_to_prev_page', 'format_multitopic') : get_string('moveleft');
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('t/left', ''),
                text: $strmovetoprevpage,
                attributes: ['class' => 'fmtmovetoprevpage'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movetonextpage item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movetonextpage_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && !$this->fmtonsectionpage
            && $this->fmtsectionextra->nextpageid
        ) { // Add a arrow to move section to next page.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destparentid', $this->fmtsectionextra->nextpageid);
            $strmovetonextpage = get_string_manager()->string_exists('move_to_next_page', 'format_multitopic') ?
                                    get_string('move_to_next_page', 'format_multitopic') : get_string('moveright');
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('t/right', ''),
                text: $strmovetonextpage,
                attributes: ['class' => 'fmtmovetonextpage'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the moveup item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_moveup_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && !$this->fmtonsectionpage
            && $this->fmtsectionextra->prevupid != $this->fmtsectionextra->parentid
        ) { // Add a arrow to move section up.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destnextupid', $this->fmtsectionextra->prevupid);
            $strmoveup = get_string('moveup');
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('i/up', ''),
                text: $strmoveup,
                attributes: ['class' => 'moveup whilenostate'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the movedown item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_movedown_item(): action_menu_link_secondary|null {
        $link = null;

        if ($this->section->section
            && has_capability('moodle/course:movesections', $this->coursecontext)
            && has_capability('moodle/course:sectionvisibility', $this->coursecontext)
            && !$this->fmtonsectionpage
            && $this->fmtsectionextra->nextupid != $this->fmtsectionextra->nextpageid
        ) { // Add a arrow to move section down.
            $url = clone($this->fmtbaseurl);
            $url->param('sectionid', $this->section->id);
            $url->param('destprevupid', $this->fmtsectionextra->nextupid);
            $strmovedown = get_string('movedown');
            $link = new action_menu_link_secondary(
                url: $url,
                icon: new pix_icon('i/down', ''),
                text: $strmovedown,
                attributes: ['class' => 'movedown whilenostate'],
            );
        }

        return $link;
    }

    /**
     * Retrieves the permalink item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_permalink_item(): action_menu_link_secondary|null {
        $link = parent::get_section_permalink_item();

        if ($link) {
            $sectionlink = course_get_url($this->format->get_course(), $this->section);
            $link->url = $sectionlink;
        }

        return $link;
    }

    /**
     * Retrieves the delete item for the section control menu.
     *
     * @return action_menu_link|null The menu item if applicable, otherwise null.
     */
    protected function get_section_delete_item(): action_menu_link_secondary|null {
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
