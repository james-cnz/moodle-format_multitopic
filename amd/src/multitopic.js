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
 * Additional scripts for Multitopic course format.
 *
 * @module     format/multitopic
 * @copyright  Te Wānanga o Aotearoa
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import jQuery from 'jquery';

/**
 * Set up the Multitopic course page with eventlistener
 *  for updating section names.
 */
export const init = () => {
    let tabcontent = jQuery(".course-content");
    tabcontent.on('updated', function(e) {
        let sectionid = e.target.dataset.itemid;
        let newname = e.target.dataset.value;
        jQuery(".nav-link .tab_content[data-itemid=" + sectionid + "]").html(newname);
    });
    return true;
};
