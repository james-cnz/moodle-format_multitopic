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
 * Course section format component.
 *
 * @module     format_multitopic/courseformat/content/section
 * @class      format_multitopic/courseformat/content/section
 * @copyright  2022 onwards James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Header from 'format_multitopic/courseformat/content/section/header';
import SectionBase from 'core_courseformat/local/content/section';
import Templates from 'core/templates';

export default class extends SectionBase {

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configState(state);
        // Drag and drop is only available for components compatible course formats.
        if (this.reactive.isEditing && this.reactive.supportComponents) {
            // Section zero and other formats sections may not have a title to drag.
            const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
            if (sectionItem) {
                // Init the inner dragable element.
                const headerComponent = new Header({ // CHANGED.
                    ...this,
                    element: sectionItem,
                    fullregion: this.element,
                });
                this.configDragDrop(headerComponent);
            }
        }
    }

    /**
     * Validate if the drop data can be dropped over the component.
     *
     * @param {Object} dropdata the exported drop data.
     * @returns {boolean}
     */
    validateDropData(dropdata) {
        if (dropdata?.type === 'section') {
            // Sections controlled by a plugin cannot accept sections.
            if (this.section.component !== null) {
                return false;
            }
            // We accept sections that fit on this one's page.
            const origin = this.reactive.get("section", dropdata.id);
            return origin.id != this.section.id && origin.levelsan >= 2;
        }
        return super.validateDropData(dropdata);
    }

    /**
     * Update a section action menus.
     *
     * @param {object} section the section state.
     */
    async _updateActionsMenu(section) {
        if (section.component) {
            await super._updateActionsMenu(section);
            return;
        }
        const menuDom = this.element.querySelector(".course-section-header .section_action_menu");
        const {html} = await Templates.renderForPromise("core_courseformat/local/content/section/controlmenu", section.controlmenu);
        Templates.replaceNode(menuDom, html, "");
    }

}