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
 * @module     format_multitopic/courseformat/courseindex/courseindex
 * @class      format_multitopic/courseformat/courseindex/courseindex
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  2022 Jeremy FitzPatrick and Te Wānanga o Aotearoa
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/courseindex/courseindex';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

export default class Component extends BaseComponent {

    create() {
        super.create();
        this.selectors.TOPSECTION = `[data-for='section'][data-indent='0']`;
        this.selectors.SECONDSECTIONS = `[data-for='section'][data-indent='1']`;
        this.selectors.THIRDSECTIONS = `[data-for='section'][data-indent='2']`;
        this.topsections = {};
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the state data
     */
    stateReady(state) {
        super.stateReady(state);
        // Get cms and sections elements.
        const topsections = this.getElements(this.selectors.TOPSECTION);
        topsections.forEach((section) => {
            this.topsections[section.dataset.id] = section;
        });
    }

    /**
     * Static method to create a component instance form the mustache template.
     *
     * @param {element|string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
     static init(target, selectors) {
        return new this({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            selectors,
        });
    }

    /**
     * Refresh the section list.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    _refreshCourseSectionlist({element}) {
        const topsectionslist = element.firstsectionlist ?? [];
        this._fixOrder(this.element, topsectionslist, this.topsections);

        for (let p in element.secondsectionlist) {
            let secondorder = Array.from(element.secondsectionlist[p]);
            secondorder.shift(); // The first item is the parent to match the tabs.
            let container = this.getElement("[data-id='" + p + "'] > .collapse > .subsections");
            let secondsections = container.querySelectorAll(this.selectors.SECONDSECTIONS);
            let secondsectionobj = {};
            secondsections.forEach((section) => {
                secondsectionobj[section.dataset.id] = section;
            });
            // First check we have all the subsections. If not move it here.
            for (let j = 0; j < secondorder.length; j++) {
                let itemid = secondorder[j];
                if (secondsectionobj[itemid] === undefined) {
                    secondsectionobj[itemid] = this.sections[itemid];
                }
            }
            this._fixOrder(container, secondorder, secondsectionobj);
        }

        for (let p in element.thirdsectionlist) {
            let thirdorder = element.thirdsectionlist[p];
            let container = this.getElement("[data-id='" + p + "'] > .collapse > .topics");
            let thirdsections = container.querySelectorAll(this.selectors.THIRDSECTIONS);
            let thirdsectionsobj = {};
            thirdsections.forEach((section) => {
                thirdsectionsobj[section.dataset.id] = section;
            });
            // First check we have all the topics. If not move it here.
            for (let j = 0; j < thirdorder.length; j++) {
                let itemid = thirdorder[j];
                if (thirdsectionsobj[itemid] === undefined) {
                    thirdsectionsobj[itemid] = this.sections[itemid];
                }
            }
            this._fixOrder(container, thirdorder, thirdsectionsobj);
        }

        // Update URLs.
        const sectionsDom = this.element.querySelectorAll(this.selectors.SECTION);
        for (let sdi = 0; sdi < sectionsDom.length; sdi++) {
            const sectionDom = sectionsDom[sdi];
            const section = this.reactive.get("section", sectionDom.dataset.id);
            if (!section) {
                continue;
            }
            const linkDom = sectionDom.querySelector("a.courseindex-link");
            const link = section.sectionurl.replace("&amp;", "&");
            if (linkDom.href != link) {
                linkDom.href = link;
            }
        }
    }

    /**
     * Create a new section instance.
     *
     * @param {Object} details the update details.
     * @param {Object} details.state the state data.
     * @param {Object} details.element the element data.
     */
    async _createSection({state, element}) {
        // Create a fake node while the component is loading.
        const fakeelement = document.createElement('div');
        fakeelement.classList.add('bg-pulse-grey', 'w-100');
        fakeelement.innerHTML = '&nbsp;';
        this.sections[element.id] = fakeelement;
        // Place the fake node on the correct position.
        this._refreshCourseSectionlist({
            state,
            element: state.course,
        });
        // Collect render data.
        const exporter = this.reactive.getExporter();
        const data = exporter.section(state, element);
        // Create the new content.
        const newcomponent = await this.renderComponent(fakeelement, 'format_multitopic/courseformat/courseindex/section', data);
        // CHANGED LINE ABOVE.
        // Replace the fake node with the real content.
        const newelement = newcomponent.getElement();
        this.sections[element.id] = newelement;
        fakeelement.parentNode.replaceChild(newelement, fakeelement);
    }

}