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
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import SectionBase from 'core_courseformat/local/content/section';

export default class extends SectionBase {

    /**
     * Validate if the drop data can be dropped over the component.
     *
     * @param {Object} dropdata the exported drop data.
     * @returns {boolean}
     */
    validateDropData(dropdata) {
        if (dropdata?.type === 'section') {
            const state = this.reactive.stateManager.state;
            const origin = state.section.get(dropdata.id);
            if (origin.id == this.section.id || origin.levelsan < 2 || this.section.levelsan < 2) {
                return false;
            }
        }
        return super.validateDropData(dropdata);
    }

    /**
     * Update a course index section using the state information.
     *
     * @param {object} param
     * @param {Object} param.element details the update details.
     */
    _refreshSection({element}) {
        super._refreshSection({element});
        const pageSectionHTML = document.querySelector(".course-section[data-id='" + element.pageid + "']");
        const pageSectionDisplay = pageSectionHTML.dataset.fmtonpage;
        if (this.element.dataset.fmtonpage != pageSectionDisplay) {
            this.element.dataset.fmtonpage = pageSectionDisplay;
            this.element.style.display = (pageSectionDisplay == "1") ? "block" : "none";
            if (pageSectionDisplay == "1" && this.section.cmlist.length > 0) {
                // TODO: Refresh cm list.
            }
        }
    }

}