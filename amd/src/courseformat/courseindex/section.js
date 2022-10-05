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
 * Course index section component.
 *
 * This component is used to control specific course section interactions like drag and drop.
 *
 * @module     format_multitopic/courseformat/courseindex/section
 * @class      format_multitopic/courseformat/courseindex/section
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ComponentBase from 'core_courseformat/local/courseindex/section';

export default class Component extends ComponentBase {

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
     static init(target, selectors) {
        return new Component({
            element: document.getElementById(target),
            selectors,
        });
    }

    /**
     * Validate if the drop data can be dropped over the component.
     *
     * @param {Object} dropdata the exported drop data.
     * @returns {boolean}
     */
    validateDropData(dropdata) {
        // We accept any course module.
        if (dropdata?.type === 'cm') {
            return true;
        }
        // We accept a section that fits
        if (dropdata?.type === 'section') {
            const sectionzeroid = this.course.sectionlist[0];
            const state = this.reactive.stateManager.state;
            const origin = state.section.get(dropdata.id);
            let target = this.section;
            return target.levelsan <= origin.levelsan && origin.id != sectionzeroid && target.id != sectionzeroid;
        }
        return false;
    }


    /**
     * Display the component dropzone.
     *
     * @param {Object} dropdata the accepted drop data
     */
    showDropZone(dropdata) {
        if (dropdata.type == 'cm') {
            this.getLastCm()?.classList.add(this.classes.DROPDOWN);
        }
        if (dropdata.type == 'section') {
            const state = this.reactive.stateManager.state;
            const origin = state.section.get(dropdata.id);
            let target = this.section;
            while (target.levelsan > origin.levelsan) {
                target = state.section.get(this.course.sectionlist[target.number - 1]);
            }
            const moveDirection = Math.sign(target.number - origin.number);
            let targetShow = target;
            let targetShowBorder = moveDirection;
            if (moveDirection > 0 && !target.indexcollapsed) {
                let targetChild = target;
                while (this.course.sectionlist.length > targetChild.number + 1
                && state.section.get(this.course.sectionlist[targetChild.number + 1]).levelsan > target.levelsan) {
                    targetChild = state.section.get(this.course.sectionlist[targetChild.number + 1]);
                    if (targetChild.levelsan <= origin.levelsan) {
                        targetShow = targetChild;
                        targetShowBorder = -1;
                        break;
                    }
                }
            }
            const targetHTML = document.querySelector(
                ".courseindex-section[data-id='" + targetShow.id + "']");
            // The relative move of section depends on the section number.
            if (targetShowBorder > 0) {
                targetHTML.classList.add(this.classes.DROPDOWN);
            } else if (targetShowBorder < 0) {
                targetHTML.classList.add(this.classes.DROPUP);
            }
        }
    }

    /**
     * Hide the component dropzone.
     *
     * @param {Object} dropdata the accepted drop data
     */
    hideDropZone(dropdata) {
        if (dropdata.type == 'cm') {
            this.getLastCm()?.classList.remove(this.classes.DROPDOWN);
        }
        if (dropdata.type == 'section') {
            const state = this.reactive.stateManager.state;
            const origin = state.section.get(dropdata.id);
            let target = this.section;
            while (target.levelsan > origin.levelsan) {
                target = state.section.get(this.course.sectionlist[target.number - 1]);
            }
            const moveDirection = Math.sign(target.number - origin.number);
            let targetShow = target;
            let targetShowBorder = moveDirection;
            if (moveDirection > 0 && !target.indexcollapsed) {
                let targetChild = target;
                while (this.course.sectionlist.length > targetChild.number + 1
                && state.section.get(this.course.sectionlist[targetChild.number + 1]).levelsan > target.levelsan) {
                    targetChild = state.section.get(this.course.sectionlist[targetChild.number + 1]);
                    if (targetChild.levelsan <= origin.levelsan) {
                        targetShow = targetChild;
                        targetShowBorder = -1;
                        break;
                    }
                }
            }
            const targetHTML = document.querySelector(
                ".courseindex-section[data-id='" + targetShow.id + "']");
            if (targetShowBorder > 0) {
                targetHTML.classList.remove(this.classes.DROPDOWN);
            } else if (targetShowBorder < 0) {
                targetHTML.classList.remove(this.classes.DROPUP);
            }
        }
    }

    /**
     * Drop event handler.
     *
     * @param {Object} dropdata the accepted drop data
     */
    drop(dropdata) {
        // Call the move mutation.
        if (dropdata.type == 'cm') {
            this.reactive.dispatch('cmMove', [dropdata.id], this.id);
        }
        if (dropdata.type == 'section') {
            const state = this.reactive.stateManager.state;
            const origin = state.section.get(dropdata.id);
            let target = this.section;
            while (target.levelsan > origin.levelsan) {
                target = state.section.get(this.course.sectionlist[target.number - 1]);
            }
            const moveDirection = Math.sign(target.number - origin.number);
            let targetCall = target;
            if (moveDirection > 0 && target.indexcollapsed) {
                let targetChild = target;
                while (this.course.sectionlist.length > targetChild.number + 1
                && state.section.get(this.course.sectionlist[targetChild.number + 1]).levelsan > target.levelsan) {
                    targetChild = state.section.get(this.course.sectionlist[targetChild.number + 1]);
                    if (targetChild.levelsan <= origin.levelsan) {
                        targetCall = targetChild;
                    }
                }
            }
            if (moveDirection != 0) {
                this.reactive.dispatch('fmtSectionMove', origin, targetCall);
            }
        }
    }

    /**
     * Update a course index section using the state information.
     *
     * @param {Object} param details the update details.
     * @param {Object} param.element the section element
     */
    _refreshSection({element}) {
        super._refreshSection({element});
        const linkHTML = this.element.querySelector("a.courseindex-link");
        const link = element.sectionurl.replace("&amp;", "&");
        if (linkHTML.href != link) {
            linkHTML.href = link;
        }
    }

}