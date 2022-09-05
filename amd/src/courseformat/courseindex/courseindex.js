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
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/courseindex/courseindex';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Mutations from 'format_multitopic/courseformat/courseeditor/mutations';

export default class Component extends BaseComponent {

    /**
     * Static method to create a component instance form the mustache template.
     *
     * @param {element|string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
     static init(target, selectors) {
        const reactive = getCurrentCourseEditor();
        reactive.addMutations({fmtSectionMove: Mutations.prototype.fmtSectionMove.bind(reactive.mutations)});
        return new Component({
            element: document.getElementById(target),
            reactive: reactive,
            selectors,
        });
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
        // Replace the fake node with the real content.
        const newelement = newcomponent.getElement();
        this.sections[element.id] = newelement;
        fakeelement.parentNode.replaceChild(newelement, fakeelement);
    }

}