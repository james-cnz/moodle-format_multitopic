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
 * @file       Javascript functions for Multitopic course format.
 * @copyright  2019 James Calder and Otago Polytechnic
 * @author     James Calder
 * @author     Jeremy FitzPatrick
 * @author     Kuslan Kabalin and others.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format.
 *
 * The section structure is:
 * <ul class="sections">
 *  <li class="section">...</li>
 *  <li class="section">...</li>
 *   ...
 * </ul>
 *
 * @return {object} section list configuration
 */
/* eslint-disable camelcase */
M.course.format.get_config = function() {
    return {
        container_node: 'ul',
        container_class: 'sections', // CHANGED.
        section_node: 'li',
        section_class: 'section'
    };
};
/* eslint-enable camelcase */

/**
 * Swap section.
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 */
/* eslint-disable camelcase */
M.course.format.swap_sections = function(Y, node1, node2) {
    /* eslint-enable camelcase */
    var CSS = {
        COURSECONTENT: 'course-content',
        SECTIONADDMENUS: 'section_add_menus'
    };

    // Reinstated this, since not using course renderer wrapper.
    var sectionlist = Y.Node.all('.' + CSS.COURSECONTENT + ' ' + M.course.format.get_section_selector(Y));
    // Swap the non-ajax menus, noting these are not always present (depends on theme and user prefs).
    if (sectionlist.item(node1).one('.' + CSS.SECTIONADDMENUS)) {
        sectionlist.item(node1).one('.' + CSS.SECTIONADDMENUS).swap(sectionlist.item(node2).one('.' + CSS.SECTIONADDMENUS));
    }
};

/**
 * Process sections after ajax response.
 *
 * @param {YUI} Y YUI3 instance
 * @param {NodeList} sectionlist of sections
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 */
/* eslint-disable camelcase */
M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    /* eslint-enable camelcase */
    var CSS = {
        SECTIONNAME: 'sectionname'
    },
    SELECTORS = {
        SECTIONLEFTSIDE: '.left .section-handle .icon'
    };

    if (response.action == 'move') {
        // If moving up swap around 'sectionfrom' and 'sectionto' so the that loop operates.
        if (sectionfrom > sectionto) {
            var temp = sectionto;
            sectionto = sectionfrom;
            sectionfrom = temp;
        }

        // Update titles and move icons in all affected sections.
        var ele, str, stridx, newstr;

        for (var i = sectionfrom; i <= sectionto; i++) {
            // Update section title.
            var content = Y.Node.create('<span>' + response.sectiontitles[i] + '</span>');
            sectionlist.item(i).all('.' + CSS.SECTIONNAME).setHTML(content);
            // Update the drag handle.
            ele = sectionlist.item(i).one(SELECTORS.SECTIONLEFTSIDE).ancestor('.section-handle');
            str = ele.getAttribute('title');
            stridx = str.lastIndexOf(' ');
            newstr = str.substr(0, stridx + 1) + i;
            ele.setAttribute('title', newstr);
            // Update the aria-label for the section.
            sectionlist.item(i).setAttribute('aria-label', content.get('innerText').trim()); // For Sharing Cart.

            // ADDED: Restore collapse icon.
            if (sectionlist.item(i).hasClass("section-topic-collapsible")) {
                M.course.format.fmtCollapseIconYui(sectionlist.item(i));
            }
            // END ADDED.

            // INCLUDED /course/format/weeks/format.js M.course.format.process_sections part.
            // Remove the current class as section has been moved.
            sectionlist.item(i).removeClass('current');
            // END INCLUDED.

        }
        // INCLUDED /course/format/weeks/format.js M.course.format.process_sections part.
        // If there is a current section, apply corresponding class in order to highlight it.
        if (response.current !== -1) {
            // Add current class to the required section.
            sectionlist.item(response.current).addClass('current');
        }
        // END INCLUDED.
    }
};

// REMAINDER ADDED.

/**
 * Set the appropriate expand/collapse icon for a specified collapsible section
 *
 * @param {HTMLLIElement} sectionDom The collapsible section
 */
M.course.format.fmtCollapseIcon = function(sectionDom) {
    var show = !sectionDom.classList.contains("section-collapsed");
    var iconDom = sectionDom.querySelector("h3.sectionname i.icon");
    if (!iconDom) {
        return;
    }
    iconDom.setAttribute("class", show ? "icon fa fa-caret-down fa-fw" : "icon fa fa-caret-right fa-fw");
};

/**
 * Set the appropriate expand/collapse icon for a collapsible section specified as a YUI node
 *
 * @param {YUI} sectionYui The collapsible section
 */
M.course.format.fmtCollapseIconYui = function(sectionYui) {
    var show = !sectionYui.hasClass("section-collapsed");
    var iconYui = sectionYui.one("h3.sectionname i.icon");
    if (!iconYui) {
        return;
    }
    iconYui.setAttribute("class", show ? "icon fa fa-caret-down fa-fw" : "icon fa fa-caret-right fa-fw");
};

/**
 * Set or toggle the expand/collapse state for a specified collapsible section
 *
 * @param {HTMLLIElement} sectionDom The collapsible section
 * @param {boolean?} show Whether the section should be shown, or undefined to toggle
 */
M.course.format.fmtCollapseSet = function(sectionDom, show) {

    if (show === undefined) {
        show = sectionDom.classList.contains("section-collapsed");
    }

    if (show) {
        sectionDom.classList.remove("section-collapsed");
        sectionDom.classList.add("section-expanded");
    } else {
        sectionDom.classList.remove("section-expanded");
        sectionDom.classList.add("section-collapsed");
    }

    M.course.format.fmtCollapseIcon(sectionDom);

};

/**
 * Toggle section expand/collapse state, where applicable, for a given click event.
 *
 * @param {MouseEvent} event The mouse click
 */
M.course.format.fmtCollapseOnClick = function(event) {

    // Find the clicked link anchor element (we may instead have been given the section's icon, from inside the anchor element).
    var eventTarget = event.target;
    if (eventTarget && eventTarget.tagName && eventTarget.tagName != "A") {
        eventTarget = eventTarget.parentElement;
    }

    // Find the linked section, and check that the link is the one on the section's heading,
    // otherwise return to normal event handling.
    var sectionId;
    if (eventTarget.hash) {
        sectionId = eventTarget.hash.substr(1);
    } else if (eventTarget.search && (eventTarget.search.indexOf("&sectionid=") >= 0)) {
        var sectionIdStart = eventTarget.search.indexOf("&sectionid=") + 11;
        var sectionIdEnd = eventTarget.search.indexOf("&", sectionIdStart);
        if (sectionIdEnd < 0) {
            sectionIdEnd = eventTarget.search.length;
        }
        sectionId = (sectionIdEnd > sectionIdStart) ?
                    "sectionid-" + eventTarget.search.substring(sectionIdStart, sectionIdEnd) : "";
    } else {
        return;
    }
    var selSectionDom = sectionId ?
                    document.querySelector("body.format-multitopic .course-content ul.sections li.section." + sectionId)
                    : null;
    if (!selSectionDom || selSectionDom.querySelector(".content h3 a") != eventTarget) {
        return;
    }

    // If this is a collapsible section, toggle its collapse state.
    if (selSectionDom.classList.contains("section-topic-collapsible") && !selSectionDom.classList.contains("section-userhidden")) {
        M.course.format.fmtCollapseSet(selSectionDom);
    }

    // If a section anchor is specified in the URL bar, clear it, since it may no longer be relevant.
    if (window.location.hash && window.location.hash != "#") {
        history.pushState(null, document.title,
                          window.location.href.substr(0, window.location.href.length - window.location.hash.length));
    }

    M.course.format.fmtCollapseAllControlsUpdate();

    // Override normal event handling.
    event.preventDefault();

};

/**
 * Expand, and scroll to, the section specified in the URL bar, and collapse other sections.
 *
 * @param {HashChangeEvent?} event The triggering event, if any
 */
M.course.format.fmtCollapseOnHashChange = function(event) {

    // Find the specified section.
    var anchor = window.location.hash.substr(1);
    var selSectionDom = anchor ?
                    document.querySelector("body.format-multitopic .course-content ul.sections li.section.section-topic." + anchor)
                    : null;

    // Exit if there is an event, but no recognised section.
    if (event && !selSectionDom) {
        return;
    }

    // Set the appropriate collapse state for all collapsible sections.
    var sectionsDom = document
                    .querySelectorAll("body.format-multitopic .course-content ul.sections li.section.section-topic-collapsible");
    for (var sectionCount = 0; sectionCount < sectionsDom.length; sectionCount++) {
        var sectionDom = sectionsDom[sectionCount];
        M.course.format.fmtCollapseSet(sectionDom,
                                       sectionDom == selSectionDom && !sectionDom.classList.contains("section-userhidden"));
    }

    M.course.format.fmtCollapseAllControlsUpdate();

    // Scroll to the specified section.
    if (selSectionDom) {
        selSectionDom.scrollIntoView();
    }

};

/**
 * Expand/collapse all sections.
 *
 * @param {MouseEvent} event The mouse click
 */
M.course.format.fmtCollapseAllOnClick = function(event) {

    // Find the clicked link anchor element.
    var eventTarget = event.target;

    // Is it expand or collapse?
    var expand = !eventTarget.classList.contains('collapse-all');

    // Set the appropriate collapse state for all collapsible sections.
    var sectionsDom = document
                    .querySelectorAll("body.format-multitopic .course-content ul.sections li.section.section-topic-collapsible");
    for (var sectionCount = 0; sectionCount < sectionsDom.length; sectionCount++) {
        var sectionDom = sectionsDom[sectionCount];
        M.course.format.fmtCollapseSet(sectionDom, expand && !sectionDom.classList.contains("section-userhidden"));
    }

    M.course.format.fmtCollapseAllControlsUpdate();

    // Override normal event handling.
    event.preventDefault();

};

/**
 * Update expand/collapse all controls.
 */
M.course.format.fmtCollapseAllControlsUpdate = function() {
    var collapsedNum = 0;
    var sectionsDom = document
                    .querySelectorAll("body.format-multitopic .course-content ul.sections li.section.section-topic-collapsible");
    for (var sectionCount = 0; sectionCount < sectionsDom.length; sectionCount++) {
        var sectionDom = sectionsDom[sectionCount];
        if (sectionDom.offsetWidth > 0 && sectionDom.offsetHeight > 0 && !sectionDom.classList.contains("section-userhidden")) {
            if (sectionDom.classList.contains('section-collapsed')) {
                collapsedNum++;
            }
        }
    }
    document.querySelector("body.format-multitopic .collapsible-actions .expand-all")
        .setAttribute("style", (collapsedNum) ? "" : "display: none;");
    document.querySelector("body.format-multitopic .collapsible-actions .collapse-all")
        .setAttribute("style", (!collapsedNum) ? "" : "display: none;");
};

/**
 * Update the First and Second level tabs.
 * @param {event} e
 */
M.course.format.fmtChangeName = function(e) {
    if (e.target.dataset.itemtype === 'sectionname') {
        var sectionid = e.target.dataset.itemid;
        var newname = e.target.dataset.value;
        var tabs = document.querySelectorAll(".nav-tabs .tab_content[data-itemid='" + sectionid + "']");
        for (var ti = 0; ti < tabs.length; ti++) {
            tabs[ti].innerHTML = newname;
        }
    }
};

/**
 * Show notice dialog when trying to add sections and maximum has been reached.
 * @param {event} e
 * @return {boolean}
 */
M.course.format.fmtWarnMaxsections = function(e) {
    var cantaddlink = e.target.matches('.cantadd');

    if (cantaddlink === false && e.target.firstElementChild !== null) {
        // Maybe we clicked on the parent <a>.
        cantaddlink = e.target.firstElementChild.matches('.cantadd');
    }
    if (cantaddlink) {
        e.preventDefault();
        require(['core/notification'], function(notification) {
             notification.addNotification({
                message: M.course.format.fmtMaxsections,
                type: 'warning'
            });
        });
        window.scroll({
            top: 0,
            left: 0,
            behavior: 'smooth'
        });
    }

    return true;
};

/**
 * Initialise: Set the initial state of collapsible sections, and watch for user input.
 * @param {YUI} Y
 * @param {string} max
 */
M.course.format.fmtInit = function(Y, max) {
    M.course.format.fmtMaxsections = max;
    // Set the initial state of collapsible sections.
    M.course.format.fmtCollapseOnHashChange();

    // Capture possible clicks on course section headings.
    document.querySelector("body.format-multitopic .course-content ul.sections")
        .addEventListener("click", M.course.format.fmtCollapseOnClick);

    // Capture clicks on any other course section links.
    window.addEventListener("hashchange", M.course.format.fmtCollapseOnHashChange);

    // Capture clicks on expand/collapse all sections.
    document.querySelector("body.format-multitopic .collapsible-actions")
        .addEventListener("click", M.course.format.fmtCollapseAllOnClick);

    // Add listener for section name inplace edited.
    require(['jquery'], function($) {
        var tabcontent = document.querySelector(".course-content ul.sections");
        $(tabcontent).on('updated', M.course.format.fmtChangeName);
    });
    // Capture clicks on add section links.
    document.querySelector(".course-content")
        .addEventListener('click', M.course.format.fmtWarnMaxsections);
};
