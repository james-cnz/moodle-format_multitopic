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
 * Course state actions dispatcher.
 *
 * This module captures all data-dispatch links in the course content and dispatch the proper
 * state mutation, including any confirmation and modal required.
 *
 * @module     format_multitopic/courseformat/content/actions
 * @class      format_multitopic/courseformat/content/actions
 * @copyright  2024 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/content/actions';

export default class extends BaseComponent {

    /**
     * Handle a create section request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    async _requestAddSection(target, event) {
        event.preventDefault();
        if (target.dataset.intoId) {
            this.reactive.dispatch('fmtAddSectionInto', target.dataset.intoId ?? 0, target.dataset.level ?? 2);
        } else {
            this.reactive.dispatch('addSection', target.dataset.id ?? 0);
        }
    }

    /**
     * Disable all add sections actions.
     *
     * @param {boolean} locked the new locked value.
     */
    _setAddSectionLocked(locked) {
        super._setAddSectionLocked(locked);
        const targets = this.getElements(`[data-region='tab-addsection']`);
        targets.forEach(element => {
            element.classList.toggle(this.classes.DISABLED, locked);
            const addSectionElement = element.querySelector(`a.nav-link`);
            addSectionElement.classList.toggle(this.classes.DISABLED, locked);
            this.setElementLocked(addSectionElement, locked);
        });
    }

}
