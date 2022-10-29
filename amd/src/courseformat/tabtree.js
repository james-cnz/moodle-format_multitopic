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
import Templates from 'core/templates';


/**
 * Course section tabs updater.
 *
 * @module     format_multitopic/courseformat/tabtree
 * @class      format_multitopic/courseformat/tabtree
 * @copyright  2022 Jeremy FitzPatrick and Te Wānanga o Aotearoa
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class Component extends BaseComponent {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'coursetabs';
        // Default query selectors.
        this.selectors = {
            TAB: `ul:first-of-type li`,
            CHILDTAB: `ul:nth-child(2) li`
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

    getWatchers() {
        return [
            // Sections sorting.
            {watch: `course.sectionlist:updated`, handler: this._refreshCourseSectionTabs},
        ];
    }

    static init(target) {
        return new Component({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor()
        });
    }

    /**
     * Initial state ready method.
     *
     */
    stateReady() {
        // Get tab elements.
        const tabs = this.getElements(this.selectors.TAB);
        for (let i = 0; i < tabs.length - 1; i++) { // Don't count last "add section" tab.
            let tab = tabs.item(i);
            let id = tab.querySelector("a div").dataset.itemid;
            let classes = tab.querySelector("a").classList.value;
            this.tabs[id] = tab;
            if (classes.indexOf(this.classes.ACTIVETAB) !== -1) {
                this.activetab[0] = id;
                this.activetab[1] = id;
            }
        }

        const childtabs = this.getElements(this.selectors.CHILDTAB);
        for (let i = 0; i < childtabs.length - 1; i++) { // Don't count last "add section" tab.
            let tab = childtabs.item(i);
            let id = tab.querySelector("a div").dataset.itemid;
            let classes = tab.querySelector("a").classList.value;
            this.childtabs[id] = tab;
            if (classes.indexOf(this.classes.ACTIVETAB) !== -1) {
                this.activetab[1] = id;
            }
        }
    }

    /**
     * Refresh the section tabs.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    _refreshCourseSectionTabs({element}) {
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
        this._fixOrder(toptabs, toptabslist, this.tabs, childtabslist[this.activetab[0]].length > 1);

        // And the second row tabs match secondsectionlist.
        let childtabs = this.element.querySelector('ul:nth-of-type(2)');
        if (childtabs) {
            this._fixOrder(childtabs, childtabslist[this.activetab[0]], this.childtabs, false);
        }
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {Array} allitems the list of html elements that can be placed in the container
     * @param {boolean} hassubtree
     */
    _fixOrder(container, neworder, allitems, hassubtree) {

        // Empty lists should not be visible.
        if (!neworder.length) {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        // Grant the list is visible (in case it was empty).
        container.classList.remove('hidden');

        // Move the elements in order at the beginning of the list.
        neworder.forEach((itemid, index) => {
            const section = this.reactive.get("section", itemid);
            const visible = (section.visible && section.available || section.section == 0)
                && (neworder.length > 1 || hassubtree);
            if (allitems[itemid] === undefined) {
                // If we don't have an item, create it from the course index.
                let selecta = "[data-id='" + itemid + "']";
                let ciElement = document.querySelector(selecta);
                let ciLink = ciElement.querySelector(" a.courseindex-link");
                let data = {
                    "active": 0,
                    "inactive": 0,
                    "link": [{
                        "link": ciLink.getAttribute("href")
                    }],
                    "title": ciLink.innerHTML,
                    "text": '<div class="tab_content' + (visible ? '' : ' dimmed')
                        + '" data-itemid="' + section.id + '">' + section.title + '</div>'
                };
                let tab = document.createElement("li");
                allitems[itemid] = tab;
                container.insertBefore(tab, container.lastElementChild);
                Templates.render("format_multitopic/courseformat/tab", data).done(function(html) {
                    allitems[itemid] = Templates.replaceNode(tab, html, "")[0];
                });
            }
            const item = allitems[itemid];
            // Update visibility
            const content = item.querySelector("div.tab_content");
            if (content && content.classList.contains("dimmed") == visible) {
                if (visible) {
                    content.classList.remove("dimmed");
                } else {
                    content.classList.add("dimmed");
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
        });
        // Remove the remaining elements.
        // But we don't want the "Add" blown away.
        while (container.children.length > neworder.length + 1) {
                container.removeChild(container.lastElementChild.previousSibling);
        }

    }

}