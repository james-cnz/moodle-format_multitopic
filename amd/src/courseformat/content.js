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
 * Course index main component.
 *
 * @module     format_multitopic/courseformat/content
 * @class      format_multitopic/courseformat/content
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/content';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import inplaceeditable from 'core/inplace_editable';
import Section from 'format_multitopic/courseformat/content/section';
import CmItem from 'format_multitopic/courseformat/content/section/cmitem';
import Templates from 'core/templates';

export default class Component extends BaseComponent {

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @param {number} sectionReturn the content section return
     * @return {Component}
     */
    static init(target, selectors, sectionReturn) {
        return new this({ // CHANGED.
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            selectors,
            sectionReturn,
        });
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the state data
     */
    stateReady(state) {
        super.stateReady(state);

        // Set the initial state of collapsible sections.
        this.fmtCollapseOnHashChange();

        // Capture clicks on course section links.
        window.addEventListener("hashchange", this.fmtCollapseOnHashChange.bind(this));

    }

    /**
     * Expand, and scroll to, the section specified in the URL bar.
     *
     * @param {HashChangeEvent?} event The triggering event, if any
     */
    /* eslint-disable no-unused-vars */
    fmtCollapseOnHashChange(event) {
            /* eslint-enable no-unused-vars */

        // Find the specified section.
        const anchor = window.location.hash.substr(1);
        const selSectionDom = anchor ?
            document.querySelector("body.format-multitopic .course-content ul.sections li.section.section-topic." + anchor)
            : null;

        // Exit if there is no recognised section.
        if (!selSectionDom) {
            return;
        }

        const sectionId = selSectionDom.getAttribute('data-id');
        const section = this.reactive.get('section', sectionId);

        // Expand, if appropriate.
        if (selSectionDom.matches(".section-topic-collapsible")
                && (selSectionDom.querySelector(".course-section-header .icons-collapse-expand.collapsed")
                    || section.contentcollapsed)) {
            this.reactive.dispatch(
                'sectionContentCollapsed',
                [sectionId],
                false
            );
        }

        // Scroll to the specified section.
        selSectionDom.scrollIntoView();

    }

    /**
     * Handle the collapse/expand all sections button.
     *
     * Toggler click is delegated to the main course content element because new sections can
     * appear at any moment and this way we prevent accidental double bindings.
     *
     * @param {Event} event the triggered event
     */
    _allSectionToggler(event) {
        event.preventDefault();

        const target = event.target.closest(this.selectors.TOGGLEALL);
        const isAllCollapsed = target.classList.contains(this.classes.COLLAPSED);

        // CHANGED.
        let sectionlist = [];
        const sectionlistDom = this.element.querySelectorAll(".course-section.section-topic-collapsible[data-fmtonpage='1']");
        for (let sectionCount = 0; sectionCount < sectionlistDom.length; sectionCount++) {
            sectionlist.push(sectionlistDom[sectionCount].dataset.id);
        }
        // END CHANGED.

        this.reactive.dispatch(
            'sectionContentCollapsed',
            sectionlist, // CHANGED.
            !isAllCollapsed
        );
    }

    /**
     * Refresh the collapse/expand all sections element.
     *
     * @param {Object} state The state data
     */
    _refreshAllSectionsToggler(state) {
        const target = this.getElement(this.selectors.TOGGLEALL);
        if (!target) {
            return;
        }
        // Check if we have all sections collapsed/expanded.
        let allcollapsed = true;
        let allexpanded = true;
        // ADDED.
        let sectionCollapsible = {};
        const sectionlistDom = this.element.querySelectorAll(".course-section.section-topic-collapsible[data-fmtonpage='1']");
        for (let sectionCount = 0; sectionCount < sectionlistDom.length; sectionCount++) {
            sectionCollapsible[sectionlistDom[sectionCount].dataset.id] = true;
        }
        // END ADDED.
        state.section.forEach(
            section => {
                if (sectionCollapsible[section.id]) { // ADDED.
                    allcollapsed = allcollapsed && section.contentcollapsed;
                    allexpanded = allexpanded && !section.contentcollapsed;
                }
            }
        );
        target.style.display = (allexpanded && allcollapsed) ? "none" : "block"; // ADDED.
        if (allcollapsed) {
            target.classList.add(this.classes.COLLAPSED);
            target.setAttribute('aria-expanded', false);
        }
        if (allexpanded) {
            target.classList.remove(this.classes.COLLAPSED);
            target.setAttribute('aria-expanded', true);
        }
    }

    /**
     * Update a course section when the section number changes.
     *
     * The courseActions module used for most course section tools still depends on css classes and
     * section numbers (not id). To prevent inconsistencies when a section is moved, we need to refresh
     * the
     *
     * Course formats can override the section title rendering so the frontend depends heavily on backend
     * rendering. Luckily in edit mode we can trigger a title update using the inplace_editable module.
     *
     * @param {Object} param
     * @param {Object} param.element details the update details.
     */
    _refreshSectionNumber({element}) {
        // Find the element.
        const target = this.getElement(this.selectors.SECTION, element.id);
        if (!target) {
            // Job done. Nothing to refresh.
            return;
        }
        // Update section numbers in all data, css and YUI attributes.
        target.id = `section-${element.number}`;
        // YUI uses section number as section id in data-sectionid, in principle if a format use components
        // don't need this sectionid attribute anymore, but we keep the compatibility in case some plugin
        // use it for legacy purposes.
        target.dataset.sectionid = element.number;
        // The data-number is the attribute used by components to store the section number.
        target.dataset.number = element.number;

        // Update title and title inplace editable, if any.
        const inplace = inplaceeditable.getInplaceEditable(target.querySelector(this.selectors.SECTION_ITEM));
        if (inplace) {
            // The course content HTML can be modified at any moment, so the function need to do some checkings
            // to make sure the inplace editable still represents the same itemid.
            const currentvalue = inplace.getValue();
            const currentitemid = inplace.getItemId();
            // Unnamed sections must be recalculated.
            if (inplace.getValue() === '' || element.timed) { // CHANGED.
                // The value to send can be an empty value if it is a default name.
                if (currentitemid == element.id
                    && (currentvalue != element.rawtitle || element.rawtitle == '' || element.timed)) { // CHANGED.
                    inplace.setValue(element.rawtitle);
                }
            }
        }
    }

    /**
     * Refresh the section list.
     *
     * @param {Object} param
     * @param {Object} param.element details the update details.
     */
    _refreshCourseSectionlist({element}) {
        super._refreshCourseSectionlist({element});
        const sectionsDom = this.element.querySelectorAll(this.selectors.SECTION);
        for (let sdi = 0; sdi < sectionsDom.length; sdi++) {
            const sectionDom = sectionsDom[sdi];
            const section = this.reactive.get("section", sectionDom.dataset.id);
            if (!section) {
                continue;
            }
            let refreshCms = false;
            const pageSectionDom = this.element.querySelector(".course-section[data-id='" + section.pageid + "']");
            const pageSectionDisplay = pageSectionDom.dataset.fmtonpage;
            if (sectionDom.dataset.fmtonpage != pageSectionDisplay) {
                sectionDom.dataset.fmtonpage = pageSectionDisplay;
                sectionDom.style.display = (pageSectionDisplay == "1") ? "block" : "none";
                if (pageSectionDisplay == "1") {
                    refreshCms = true;
                }
            }
            if (section.visible == sectionDom.classList.contains("hidden")) {
                const badgeDom = sectionDom.querySelector("span.badge[data-type='hiddenfromstudents']");
                if (section.visible) {
                    sectionDom.classList.remove("hidden");
                    badgeDom.classList.add("d-none");
                } else {
                    sectionDom.classList.add("hidden");
                    badgeDom.classList.remove("d-none");
                }
                if (sectionDom.dataset.fmtonpage == "1") {
                    refreshCms = true;
                }
            }
            if (refreshCms) {
                // Note: Visibility state doesn't get updated for CMs already rendered.
                this._refreshSectionCmlist({element: section});
            }
            const menuDom = sectionDom.querySelector(".course-section-header .section_action_menu");
            Templates.render("core_courseformat/local/content/section/controlmenu", section.controlmenu).done(function(html) {
                Templates.replaceNode(menuDom, html, "");
            });
        }
        this._refreshAllSectionsToggler(this.reactive.stateManager.state);
    }

    /**
     * Regenerate content indexes.
     *
     * This method is used when a legacy action refresh some content element.
     */
    _indexContents() {
        // Find unindexed sections.
        this._scanIndex(
            this.selectors.SECTION,
            this.sections,
            (item) => {
                return new Section(item); // CHANGED.
            }
        );

        // Find unindexed cms.
        this._scanIndex(
            this.selectors.CM,
            this.cms,
            (item) => {
                return new CmItem(item); // CHANGED.
            }
        );
    }

}