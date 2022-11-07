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
 * @copyright  2022 James Calder and Otago Polytechnic
 * @copyright  based on work by 2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BaseComponent from 'core_courseformat/local/content/actions';
// import ModalFactory from 'core/modal_factory';
// import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {prefetchStrings} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import {getList} from 'core/normalise';
// import * as CourseEvents from 'core_course/events';
// import Pending from 'core/pending';
import ContentTree from 'core_courseformat/local/courseeditor/contenttree';
// The jQuery module is only used for interacting with Boostrap 4. It can we removed when MDL-79179 is integrated.
import jQuery from 'jquery';

// Load global strings.
prefetchStrings('core', ['movecoursesection', 'movecoursemodule', 'confirm', 'delete']);

export default class extends BaseComponent {

    /**
     * Handle a move cm request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    async _requestMoveCm(target, event) {
        // Check we have an id.
        const cmId = target.dataset.id;
        if (!cmId) {
            return;
        }
        const cmInfo = this.reactive.get('cm', cmId);

        event.preventDefault();

        // The section edit menu to refocus on end.
        const editTools = this._getClosestActionMenuToogler(target);

        // Collect section information from the state.
        const exporter = this.reactive.getExporter();
        const data = exporter.course(this.reactive.state);

        // Add the target cm info.
        data.cmid = cmInfo.id;
        data.cmname = cmInfo.name;

        // Build the modal parameters from the event data.
        const modalParams = {
            title: getString('movecoursemodule', 'core'),
            body: Templates.render('format_multitopic/courseformat/content/movecm', data), // CHANGED.
        };

        // Create the modal.
        const modal = await this._modalBodyRenderedPromise(modalParams);

        const modalBody = getList(modal.getBody())[0];

        // Disable current element.
        let currentElement = modalBody.querySelector(`${this.selectors.CMLINK}[data-id='${cmId}']`);
        this._disableLink(currentElement);

        // Setup keyboard navigation.
        new ContentTree(
            modalBody.querySelector(this.selectors.CONTENTTREE),
            {
                SECTION: this.selectors.SECTIONNODE,
                TOGGLER: this.selectors.MODALTOGGLER,
                COLLAPSE: this.selectors.MODALTOGGLER,
                ENTER: this.selectors.SECTIONLINK,
            }
        );

        // Open the cm section node if possible (Bootstrap 4 uses jQuery to interact with collapsibles).
        // All jQuery int this code can be replaced when MDL-79179 is integrated.
        const sectionnode = currentElement.closest(this.selectors.SECTIONNODE);
        const toggler = jQuery(sectionnode).find(this.selectors.MODALTOGGLER);
        let collapsibleId = toggler.data('target') ?? toggler.attr('href');
        if (collapsibleId) {
            // We cannot be sure we have # in the id element name.
            collapsibleId = collapsibleId.replace('#', '');
            jQuery(`#${collapsibleId}`).collapse('toggle');
        }

        // Capture click.
        modalBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!target.matches('a') || target.dataset.for === undefined || target.dataset.id === undefined) {
                return;
            }
            if (target.getAttribute('aria-disabled')) {
                return;
            }
            event.preventDefault();

            // Get draggable data from cm or section to dispatch.
            let targetSectionId;
            let targetCmId;
            if (target.dataset.for == 'cm') {
                const dropData = exporter.cmDraggableData(this.reactive.state, target.dataset.id);
                targetSectionId = dropData.sectionid;
                targetCmId = dropData.nextcmid;
            } else {
                const section = this.reactive.get('section', target.dataset.id);
                targetSectionId = target.dataset.id;
                targetCmId = section?.cmlist[0];
            }

            this.reactive.dispatch('cmMove', [cmId], targetSectionId, targetCmId);
            this._destroyModal(modal, editTools);
        });
    }

}