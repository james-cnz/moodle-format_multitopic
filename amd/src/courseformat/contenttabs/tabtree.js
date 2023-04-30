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
 * @module     format_multitopic/courseformat/contenttabs/tabtree
 * @class      format_multitopic/courseformat/contenttabs/tabtree
 * @copyright  2022 Jeremy FitzPatrick and Te Wānanga o Aotearoa
 * @copyright  2023 James Calder and Otago Polytechnic
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
            TAB: `ul:first-of-type li`,
            CHILDTAB: `ul:nth-child(2) li`,
            SECTION_ITEM: `a.nav-link`,
        };
        // Default classes
        this.classes = {
            ACTIVETAB: 'active'
        };
        // Objects to keep tabs on the tabs
        this.tabs = {};
        this.childtabs = {};
        this.activetab = [null, null];
    }

    static init(target) {
        return new this({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
        });
    }

    /**
     * Initial state ready method.
     *
     */
    stateReady() {
        this._indexContents();
    }

    getWatchers() {
        return [
            // Sections sorting.
            {watch: `course.sectionlist:updated`, handler: this._refreshCourseSectionTabs},
        ];
    }

    /**
     * Refresh the section tabs.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    async _refreshCourseSectionTabs({element}) {
        // Change the active top-level tab, if necessary.
        const activeTab1 = this.reactive.get('section', this.activetab[1]);
        let newActiveTab0id = (activeTab1.levelsan >= 1) ? activeTab1.parentid : activeTab1.id;
        if (newActiveTab0id != this.activetab[0]) {
            let section = this.reactive.get("section", this.activetab[0]);
            let anchor = this.element.querySelector('ul:first-of-type div[data-itemid="' + this.activetab[0] + '"]').parentElement;
            anchor.classList.remove("active");
            anchor.href = section.sectionurl.replace("&amp;", "&");
            this.activetab[0] = newActiveTab0id;
            anchor = this.element.querySelector('ul:first-of-type div[data-itemid="' + this.activetab[0] + '"]').parentElement;
            anchor.classList.add("active");
            anchor.removeAttribute("href");
            const addAnchor = this.element.querySelector('ul:nth-of-type(2) li:last-of-type a');
            const addLink = addAnchor.href.replace(/&insertparentid=\d+/, "&insertparentid=" + this.activetab[0]);
            addAnchor.setAttribute("href", addLink);
        }

        // Do things that make the first row tabs match firstsectionlist.
        const toptabslist = element.firstsectionlist ?? [];
        const childtabslist = element.secondsectionlist ?? [];
        let toptabs = this.element.querySelector('ul:first-of-type');
        await this._fixOrder(toptabs, toptabslist, this.selectors.TAB, 0, childtabslist[this.activetab[0]].length > 1);

        // And the second row tabs match secondsectionlist.
        let childtabs = this.element.querySelector('ul:nth-of-type(2)');
        if (childtabs) {
            await this._fixOrder(childtabs, childtabslist[this.activetab[0]], this.selectors.CHILDTAB, 1, false);
        }

        this._indexContents();
    }

    /**
     * Regenerate content indexes.
     *
     * This method is used when a legacy action refresh some content element.
     */
    _indexContents() {
        // Find unindexed tabs.
        this._scanIndex(
            this.selectors.TAB,
            this.tabs,
            (item) => {
                return new Tab(item);
            },
            0
        );

        // Find unindexed child tabs.
        this._scanIndex(
            this.selectors.CHILDTAB,
            this.childtabs,
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
            let classes = item.querySelector("a").classList.value;
            if (classes.indexOf(this.classes.ACTIVETAB) !== -1) {
                if (level <= 0) {
                    this.activetab[0] = item.dataset.id;
                }
                this.activetab[1] = item.dataset.id;
            }
            // Mark as indexed.
            item.dataset.indexed = true;
        });
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {string} selector the element selector
     * @param {int} level the tab level
     * @param {boolean} hassubtree
     */
    async _fixOrder(container, neworder, selector, level, hassubtree) {

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
            const section = this.reactive.get("section", itemid);
            const visible = (section.visible && section.available || section.section == 0)
                && (neworder.length > 1 || hassubtree);
            const current = (section.currentnestedlevel != undefined && section.currentnestedlevel >= level);
            let item = this.getElement(selector, itemid);
            if (item === null) {
                // If we don't have an item, create it.
                let data = {
                    "sectionid": itemid,
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
                item = document.createElement("li");
                container.insertBefore(item, container.lastElementChild);
                let html = await Templates.render("format_multitopic/courseformat/contenttabs/tab", data);
                item = Templates.replaceNode(item, html, "")[0];
            }

            // Update visibility & current marker
            const content = item.querySelector("div.tab_content");
            if (content && content.classList.contains("dimmed") == visible) {
                if (visible) {
                    content.classList.remove("dimmed");
                } else {
                    content.classList.add("dimmed");
                }
            }
            if (content && content.classList.contains("marker") != current) {
                if (current) {
                    content.classList.add("marker");
                } else {
                    content.classList.remove("marker");
                }
            }

            // Get the current element at that position.
            const currentitem = container.children[index];
            if (currentitem === undefined) {
                container.append(item);
                return;
            }
            if (currentitem !== item) {
                container.insertBefore(item, currentitem);
            }
        }
        // Remove the remaining elements.
        // But we don't want the "Add" blown away.
        while (container.children.length > neworder.length + 1) {
                container.removeChild(container.lastElementChild.previousSibling);
        }

    }

}