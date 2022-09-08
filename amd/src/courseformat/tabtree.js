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
        this.activetab = 0;
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
                this.activetab = i;
            }
        }

        const childtabs = this.getElements(this.selectors.CHILDTAB);
        for (let i = 0; i < childtabs.length - 1; i++) { // Don't count last "add section" tab.
            let tab = childtabs.item(i);
            let id = tab.querySelector("a div").dataset.itemid;
            this.childtabs[id] = tab;
        }
    }

    /**
     * Refresh the section tabs.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    _refreshCourseSectionTabs({element}) {
        // Do things that make the first row tabs match firstsectionlist.
        const toptabslist = element.firstsectionlist ?? [];
        let toptabs = this.element.querySelector('ul:first-of-type');
        this._fixOrder(toptabs, toptabslist, this.tabs);

        // And the second row tabs match secondsectionlist.
        const childtabslist = element.secondsectionlist ?? [];
        let childtabs = this.element.querySelector('ul:nth-of-type(2)');
        this._fixOrder(childtabs, childtabslist[this.activetab], this.childtabs);
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {Array} allitems the list of html elements that can be placed in the container
     */
    _fixOrder(container, neworder, allitems) {

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
            const item = allitems[itemid];
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
        // Probably not necessary as we are not removing anything. And we don't want the "Add" blown away.
        /*
        while (container.children.length > neworder.length) {
            container.removeChild(container.lastChild);
        }
         */
    }

}