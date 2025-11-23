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
 * @copyright  2022 onwards James Calder and Otago Polytechnic
 * @copyright  based on work by 2020 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/content';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import inplaceeditable from 'core/inplace_editable';
import Section from 'format_multitopic/courseformat/content/section';
import CmItem from 'core_courseformat/local/content/section/cmitem';

export default class Component extends BaseComponent {

    /**
     * Constructor hook.
     *
     * @deprecated redeclaration since Moodle 5.1 MDL-83857
     * @param {Object} descriptor the component descriptor
     */
    create(descriptor) {
        super.create(descriptor);
        this.pageSectionId = descriptor?.pageSectionId
                            ?? this.element.querySelector("ul.section-list").dataset.originalsinglesectionid;
    }

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @param {number} sectionReturn the section number of the displayed page
     * @param {number} pageSectionId the section ID of the displayed page
     * @return {Component}
     */
    static init(target, selectors, sectionReturn, pageSectionId) {
        let element = document.querySelector(target);
        return new this({ // CHANGED.
            element,
            reactive: getCurrentCourseEditor(),
            selectors,
            sectionReturn,
            pageSectionId,
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
            document.querySelector(".course-content ul.section-list li.section.section-topic .sectionname" + anchor);

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
        const togglerlistDom = this.element.querySelectorAll(
            ".course-section " +
            this.selectors.SECTION_ITEM + " " + this.selectors.COLLAPSE
        );
        for (let togglerDom of togglerlistDom) {
            sectionlist.push(togglerDom.closest(".course-section").dataset.id);
        }
        // END CHANGED.

        this.reactive.dispatch(
            'sectionContentCollapsed',
            sectionlist, // CHANGED.
            !isAllCollapsed
        );
    }

    /**
     * Return the component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return super.getWatchers().concat([
            {watch: `course.sectionlevellist:updated`, handler: this._refreshCourseSectionlist},
            {watch: `section.fmtispage:updated`, handler: this._reloadSection},
            {watch: `section.collapsible:updated`, handler: this._reloadSection},
            {watch: `section.parentvisiblesan:updated`, handler: this._reloadSection},
        ]);
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
        const togglerlistDom = this.element.querySelectorAll(
            ".course-section " +
            this.selectors.SECTION_ITEM + " " + this.selectors.COLLAPSE
        );
        for (let togglerDom of togglerlistDom) {
            sectionCollapsible[togglerDom.closest(".course-section").dataset.id] = true;
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
        target.style.visibility = (allexpanded && allcollapsed) ? "hidden" : "visible"; // ADDED.
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
     * Update a course section name on the whole page.
     *
     * @param {object} param
     * @param {Object} param.element details the update details.
     */
    _refreshSectionTitle(param) {
        super._refreshSectionTitle(param);
        const element = param.element;

        // Find the element.
        const target = this.getElement(this.selectors.SECTION, element.id);
        if (!target) {
            // Job done. Nothing to refresh.
            return;
        }

        // Update title and title inplace editable, if any.
        const inplace = inplaceeditable.getInplaceEditable(target.querySelector(this.selectors.SECTION_ITEM));
        if (inplace) {
            // The course content HTML can be modified at any moment, so the function need to do some checkings
            // to make sure the inplace editable still represents the same itemid.
            const currentitemid = inplace.getItemId();
            if (currentitemid == element.id) { // CHANGED.
                inplace.setValue(element.rawtitle);
            }
        }

        if (element.component) {
            return;
        }

        // Update subtitle.
        target.querySelector(".section_subtitle").textContent = element.subtitle;
    }

    /**
     * Refresh the section list.
     *
     * @param {Object} param
     * @param {Object} param.element details the update details (Moodle <4.4).
     * @param {Object} param.state the full state object (Moodle >=4.4).
     */
    _refreshCourseSectionlist(param) {
        const state = param.state;

        const originalPageSection = this.reactive.get("section", this.pageSectionId);
        if (originalPageSection && originalPageSection.component) {
            return;
        }
        let pageSectionId;
        if (originalPageSection) {
            pageSectionId = (originalPageSection.levelsan < 2) ? originalPageSection.id : originalPageSection.pageid;
        } else {
            pageSectionId = null;
        }

        let sectionlist = this.reactive.getExporter().listedSectionIds(state);
        sectionlist = sectionlist.filter((sectionId) => (this.reactive.get("section", sectionId).pageid == pageSectionId));
        // ADDED LINE ABOVE.
        const listparent = this.getElement(this.selectors.COURSE_SECTIONLIST);
        // For now section cannot be created at a frontend level.
        const createSection = this._createSectionItem.bind(this);
        if (listparent) {
            this._fixOrder(listparent, sectionlist, this.selectors.SECTION, this.dettachedSections, createSection);
        }

        this._refreshAllSectionsToggler(this.reactive.stateManager.state);

        // Update Add section button.
        const addSectionDom = document.querySelector("div#fmt-course-addsection > a");
        addSectionDom.href = addSectionDom.href.replace(/\binsertparentid=\d+\b/, "insertparentid=" + pageSectionId);

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
                return new CmItem(item);
            }
        );

        this._refreshAllSectionsToggler(this.reactive.stateManager.state); // ADDED.
    }

    /**
     * Reload a course section contents.
     *
     * Section HTML is still strongly backend dependant.
     * Some changes require to get a new version of the section.
     *
     * @param {details} param0 the watcher details
     */
    _reloadSection(param0) {
        const sectionDom = this.getElement(this.selectors.SECTION, param0.element.id);
        if (!sectionDom || sectionDom.dataset?.fmtReloading) {
            return;
        }
        sectionDom.dataset.fmtReloading = 1;
        super._reloadSection(param0);
    }

}