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

import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Tab from 'format_multitopic/courseformat/contenttabs/tab';
import Templates from 'core/templates';


/**
 * Course section tabs updater.
 *
 * @module     format_multitopic/courseformat/contenttabs/tabtreecontainer
 * @class      format_multitopic/courseformat/contenttabs/tabtreecontainer
 * @copyright  2022 Jeremy FitzPatrick and Te Wānanga o Aotearoa
 * @copyright  2023 onwards James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class Component extends BaseComponent {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'contenttabs';
        // Default query selectors.
        this.selectors = {
            SECTIONL0: `ul:first-of-type li`,
            SECTIONL1: `ul:nth-child(2) li`,
            SECTION_ITEM: `a.nav-link`,
        };
        // Default classes
        this.classes = {
            ACTIVETAB: 'active'
        };
        // Objects to keep tabs on the tabs
        this.sectionsl0 = {};
        this.sectionsl1 = {};
        this.activetab = [null, null];
    }

    static init(target) {
        let element = document.querySelector(target);
        return new this({
            element,
            reactive: getCurrentCourseEditor(),
        });
    }

    /**
     * Initial state ready method.
     */
    stateReady() {
        this._indexContents();
    }

    getWatchers() {
        return [
            // Sections sorting.
            {watch: `course.sectionlist:updated`, handler: this._refreshCourseSectionlist},
        ];
    }

    /**
     * Get the main DOM element of this component or a subelement.
     *
     * @param {string|undefined} query optional subelement query
     * @param {string|undefined} dataId optional data-id value
     * @returns {element|undefined} the DOM element (if any)
     */
    getElement(query, dataId) {
        if (dataId.match(/^add\d*$/)) {
            const dataSelector = `:not([data-id])`;
            const selector = `${query ?? ''}${dataSelector}`;
            return this.element.querySelector(selector);
        }
        return super.getElement(query, dataId);
    }

    /**
     * Refresh the section tabs.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    async _refreshCourseSectionlist({element}) {

        const originalPageSectionid = this.reactive?.pageSectionId
                                        ?? document.querySelector("ul.section-list").dataset.originalsinglesectionid;
                                        // Fallback deprecated since Moodle 5.1 MDL-83857.
        const originalPageSection = this.reactive.get("section", originalPageSectionid);
        let pageSectionId;
        let pageSection;
        if (originalPageSection) {
            pageSectionId = (originalPageSection.levelsan < 2) ? originalPageSection.id : originalPageSection.pageid;
            pageSection = (pageSectionId == originalPageSection.id) ?
                            originalPageSection : this.reactive.get("section", pageSectionId);
        } else {
            pageSectionId = null;
            pageSection = null;
        }

        let newActiveTab = [null, null];
        if (pageSection) {
            newActiveTab[1] = pageSection.id;
            newActiveTab[0] = (pageSection.levelsan >= 1) ? pageSection.parentid : pageSection.id;
        }

        const tabsrow = this._getTabRows(element, newActiveTab);

        if (!tabsrow[1].length) {
            newActiveTab[1] = null;
        }

        // Remove second-level tabs, if necessary.
        let tabsSecondRowDom = this.element.querySelector('ul:nth-of-type(2)');
        if (tabsSecondRowDom && (!tabsrow[1].length || (newActiveTab[0] != this.activetab[0]))) {
            tabsSecondRowDom.remove();
            tabsSecondRowDom = null;
            this.activetab[1] = null;
        }

        // Update the tabs.
        for (let level = 0; level < 2; level++) {
            if (tabsrow[level].length) {
                let tabsDom = this.element.querySelector('ul:nth-of-type(' + (level + 1) + ')');
                // Create tab row if necessary.
                if (!tabsDom) {
                    tabsDom = document.createElement('ul');
                    this.element.append(tabsDom);
                    tabsDom.className = 'nav nav-tabs mb-3';
                }
                // Unselect old tab.
                if (this.activetab[level] && (newActiveTab[level] != this.activetab[level])) {
                    tabsDom.querySelector('div[data-itemid="' + this.activetab[level] + '"]')
                            ?.parentElement.classList.remove("active");
                    this.activetab[level] = null;
                }
                // Update tabs order.
                await this._fixOrder(tabsDom, tabsrow[level], level ? this.selectors.SECTIONL1 : this.selectors.SECTIONL0, level);
                // Select new tab.
                if (newActiveTab[level]) {
                    this.activetab[level] = newActiveTab[level];
                    tabsDom.querySelector('div[data-itemid="' + this.activetab[level] + '"]')
                            ?.parentElement.classList.add("active");
                }
            }
            this.activetab[level] = newActiveTab[level];
        }

        this._indexContents();
    }

    /**
     * Get tab rows.
     *
     * @param {Object} element
     * @param {Array} newActiveTab
     * @returns {Array}
     */
    _getTabRows(element, newActiveTab) {
        let tabsrow = [[], []];
        let depthmax = -1;
        let depthactive = -1;

        for (let sectionid of element.sectionlist) {
            const section = this.reactive.get("section", sectionid);
            if (section.component || (section.levelsan >= 2)) {
                continue;
            }
            if ((section.levelsan < 0) || (section.id == newActiveTab[section.levelsan])) {
                for (
                    let level = section.levelsan;
                    (level < 2) && ((level < 0) || (section.id == newActiveTab[level]));
                    level++
                ) {
                    depthactive = level;
                }
            } else {
                depthactive = Math.min(depthactive, section.levelsan - 1);
            }
            if ((section.levelsan >= 0) && (section.levelsan <= depthactive + 1)) {
                tabsrow[section.levelsan].push(section.id);
                depthmax = Math.max(depthmax, section.levelsan);
            }
        }

        for (let level = 0; level <= Math.min(depthmax + 1, 1); level++) {
            const parentid = (level == 0) ? element.sectionlist[0] : newActiveTab[level - 1];
            tabsrow[level].unshift(parentid);
            tabsrow[level].push("add" + parentid);
        }

        return tabsrow;
    }

    /**
     * Regenerate content indexes.
     *
     * This method is used when a legacy action refresh some content element.
     */
    _indexContents() {
        // Find unindexed tabs.
        this._scanIndex(
            this.selectors.SECTIONL0,
            this.sectionsl0,
            (item) => {
                return new Tab(item);
            },
            0
        );

        // Find unindexed child tabs.
        this._scanIndex(
            this.selectors.SECTIONL1,
            this.sectionsl1,
            (item) => {
                return new Tab(item);
            },
            1
        );
    }

    /**
     * Reindex a tab.
     *
     * This method is used internally by _indexContents.
     *
     * @param {string} selector the DOM selector to scan
     * @param {*} index the index attribute to update
     * @param {*} creationhandler method to create a new indexed element
     * @param {int} level tab level
     */
    _scanIndex(selector, index, creationhandler, level) {
        const items = this.getElements(`${selector}:not([data-indexed])`);
        items.forEach((item) => {
            if (!item?.dataset?.id) {
                return;
            }
            // Delete previous item component.
            if (index[item.dataset.id] !== undefined) {
                index[item.dataset.id].unregister();
            }
            // Create the new component.
            index[item.dataset.id] = creationhandler({
                ...this,
                element: item,
            });
            // Update selected tab
            let classes = item.querySelector("a").classList;
            if (classes.contains(this.classes.ACTIVETAB)) {
                this.activetab[level] = item.dataset.id;
            }
            // Mark as indexed.
            item.dataset.indexed = true;
        });
    }

    /**
     * Create a new section item.
     *
     * This method will append a new item in the container.
     *
     * @param {Element} container the container element (section)
     * @param {Number} sectionid the course-module ID
     * @param {int} level the tab level
     * @returns {Element} the created element
     */
    async _createSectionItem(container, sectionid, level) {
        let data;
        if (!isNaN(parseInt(sectionid))) {
            const section = this.reactive.get("section", sectionid);
            const visible = (section.visible && section.available || (section.section == 0)) && (level <= section.pagedepthdirect);
            const current = (section.currentnestedlevel != undefined) && (section.currentnestedlevel >= level);
            data = {
                "sectionid": section.id,
                "level": level,
                "active": 0,
                "inactive": 0,
                "link": [{
                    "link": section.sectionurl
                }],
                "title": section.name,
                "text": '<div class="tab_content' + (visible ? '' : ' dimmed') + (current ? ' marker' : '')
                    + '" data-itemid="' + section.id + '">' + section.title + '</div>'
            };
        } else {
            const addTab0Dom = this.element.querySelector('ul:first-of-type li:last-of-type');
            data = {
                "level": level,
                "active": false,
                "inactive": addTab0Dom.querySelector('a').classList.contains("disabled"),
                "link": [{
                    "link": addTab0Dom.querySelector('a').getAttribute('href')
                        ?.replace(/\binsertparentid=\d+\b/, "insertparentid=" + sectionid.match(/^add(\d+)$/)[1])
                        .replace(/\binsertlevel=0\b/, 'insertlevel=' + level),
                }],
                "title": addTab0Dom.querySelector('a').getAttribute('title'),
                "text": '<i class="icon fa fa-plus fa-fw" title="' + addTab0Dom.querySelector('a').getAttribute('title') + '"></i>',
            };
        }
        let newItem = document.createElement("li");
        const {html} = await Templates.renderForPromise("format_multitopic/courseformat/contenttabs/tab", data);
        newItem = Templates.replaceNode(newItem, html, "")[0];
        return newItem;
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {string} selector the element selector
     * @param {int} level the tab level
     */
    async _fixOrder(container, neworder, selector, level) {

        // Empty lists should not be visible.
        if (!neworder.length) {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        // Grant the list is visible (in case it was empty).
        container.classList.remove('hidden');

        // Move the elements in order at the beginning of the list.
        for (const [index, itemid] of Object.entries(neworder)) {
            let item = this.getElement(selector, itemid) ?? await this._createSectionItem(container, itemid, level);

            // Get the current element at that position.
            const currentitem = container.children[index];
            if (currentitem === undefined) {
                container.append(item);
            } else if (currentitem !== item) {
                container.insertBefore(item, currentitem);
            }
        }

        // Remove the remaining elements.
        while (container.children.length > neworder.length) {
            container.removeChild(container.lastChild);
        }

    }

}