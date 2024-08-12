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
import DispatchActions from 'format_multitopic/courseformat/content/actions';
import * as CourseEvents from 'core_course/events';

export default class Component extends BaseComponent {

    /**
     * Constructor hook.
     *
     * @param {Object} descriptor the component descriptor
     */
    create(descriptor) {
        super.create(descriptor);
        this.version = descriptor.version;
        this.originalsinglesectionid = this.element.querySelector("ul.sections").dataset.originalsinglesectionid;
    }

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @param {number} sectionReturn the content section return
     * @param {number} version Moodle version number
     * @return {Component}
     */
    static init(target, selectors, sectionReturn, version) {
        return new this({ // CHANGED.
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            selectors,
            sectionReturn,
            version,
        });
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the state data
     */
    stateReady(state) {
        this._indexContents();
        // Activate section togglers.
        this.addEventListener(this.element, 'click', this._sectionTogglers);

        // Collapse/Expand all sections button.
        const toogleAll = this.getElement(this.selectors.TOGGLEALL);
        if (toogleAll) {

            // Ensure collapse menu button adds aria-controls attribute referring to each collapsible element.
            const collapseElements = this.getElements(this.selectors.COLLAPSE);
            const collapseElementIds = [...collapseElements].map(element => element.id);
            toogleAll.setAttribute('aria-controls', collapseElementIds.join(' '));

            this.addEventListener(toogleAll, 'click', this._allSectionToggler);
            this.addEventListener(toogleAll, 'keydown', e => {
                // Collapse/expand all sections when Space key is pressed on the toggle button.
                if (e.key === ' ') {
                    this._allSectionToggler(e);
                }
            });
            this._refreshAllSectionsToggler(state);
        }

        if (this.reactive.supportComponents) {
            // Actions are only available in edit mode.
            if (this.reactive.isEditing) {
                new DispatchActions(this); // CHANGED.
            }

            // Mark content as state ready.
            this.element.classList.add(this.classes.STATEDREADY);
        }

        // Capture completion events.
        this.addEventListener(
            this.element,
            CourseEvents.manualCompletionToggled,
            this._completionHandler
        );

        // Capture page scroll to update page item.
        this.addEventListener(
            (this.version >= 2023081800) ? document : document.querySelector(this.selectors.PAGE),
            "scroll",
            this._scrollHandler
        );

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
        let anchor = window.location.hash;
        if (!anchor.match(/^#sectionid-\d+(?:-title)?$/)) {
            return;
        }
        let oldStyle = false;
        if (anchor.match(/^#sectionid-\d+$/)) {
            anchor = anchor + "-title";
            oldStyle = true;
            history.replaceState(history.state, "", anchor);
        }
        const selSectionHeaderDom =
            document.querySelector(".course-content ul.sections li.section.section-topic .sectionname" + anchor);

        // Exit if there is no recognised section.
        if (!selSectionHeaderDom) {
            return;
        }

        const selSectionDom = selSectionHeaderDom.closest("li.section.section-topic");
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
        if (oldStyle) {
            selSectionDom.scrollIntoView();
        }

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
     * @param {Object} param.element details the update details (Moodle <4.4).
     * @param {Object} param.state the full state object (Moodle >=4.4).
     */
    _refreshCourseSectionlist(param) {
        super._refreshCourseSectionlist(param);
        const originalSingleSection = this.reactive.get("section", this.originalsinglesectionid);
        const singleSectionId = originalSingleSection ?
                                (originalSingleSection.levelsan < 2 ? originalSingleSection.id : originalSingleSection.pageid)
                                : null;
        const sectionsDom = this.element.querySelectorAll(this.selectors.SECTION);
        for (let sdi = 0; sdi < sectionsDom.length; sdi++) {
            const sectionDom = sectionsDom[sdi];
            const section = this.reactive.get("section", sectionDom.dataset.id);
            if (!section) {
                continue;
            }
            let refreshCms = false;
            const pageSectionDisplay = (section.pageid == singleSectionId);
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
                    if (badgeDom) {
                        badgeDom.classList.add("d-none");
                    }
                } else {
                    sectionDom.classList.add("hidden");
                    if (badgeDom) {
                        badgeDom.classList.remove("d-none");
                    }
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

        // Update Add section button if necessary.
        const addSectionDom = document.querySelector("div#course-addsection > a");
        if (addSectionDom.dataset.intoId != singleSectionId) {
            addSectionDom.dataset.intoId = singleSectionId;
            addSectionDom.href = addSectionDom.href.replace(/\binsertparentid=\d+\b/, "insertparentid=" + singleSectionId);
        }

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