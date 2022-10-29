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
 * Mutation manager
 *
 * @module     format_multitopic/courseformat/courseeditor/mutations
 * @class      format_multitopic/courseformat/courseeditor/mutations
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class {

    /**
     * Move course modules to specific course location.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {object} origin the section to move
     * @param {object} targetSection the target section
     */
     async fmtSectionMove(stateManager, origin, targetSection) { // CHANGED.
        if (!targetSection || !targetSection.id) { // CHANGED.
            throw new Error(`Mutation sectionMove requires targetSectionId`);
        }
        const course = stateManager.get('course');
        // ADDED.
        const originList = [origin.id];
        let originChild = origin;
        while (course.sectionlist.length > originChild.number + 1
                && stateManager.get("section", course.sectionlist[originChild.number + 1]).levelsan > origin.levelsan) {
            originChild = stateManager.get("section", course.sectionlist[originChild.number + 1]);
            originList.push(originChild.id);
        }
        // END ADDED.
        this.sectionLock(stateManager, originList, true);
        const updates = await this._callEditWebservice('fmt_section_move', course.id, originList, targetSection.id);
        // CHANGED LINE ABOVE.
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, originList, false);
    }

}