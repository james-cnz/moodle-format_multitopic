// Javascript functions for Multitopic course format.

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format
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
        container_class: 'sections',
        section_node: 'li',
        section_class: 'section'
    };
};
/* eslint-enable camelcase */

/**
 * Swap section
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 */
/* eslint-disable camelcase, no-unused-vars */
M.course.format.swap_sections = function(Y, node1, node2) {
    /* eslint-enable camelcase, no-unused-vars */
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
 * Process sections after ajax response
 *
 * @param {YUI} Y YUI3 instance
 * @param {NodeList} sectionlist
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
            // Update move icon.
            ele = sectionlist.item(i).one(SELECTORS.SECTIONLEFTSIDE);
            str = ele.getAttribute('alt');
            stridx = str.lastIndexOf(' ');
            newstr = str.substr(0, stridx + 1) + i;
            ele.setAttribute('alt', newstr);
            ele.setAttribute('title', newstr); // For FireFox as 'alt' is not refreshed.

            // ADDED: Restore collapse icon.
            if (sectionlist.item(i).hasClass("section-topic-timed")) {
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
    if (!eventTarget.hash) {
        return;
    }
    var anchor = eventTarget.hash.substr(1);
    var selSectionDom = anchor ?
                    document.querySelector("body.format-multitopic .course-content ul.sections li.section.section-topic." + anchor)
                    : null;
    if (!selSectionDom || selSectionDom.querySelector(".content h3 a") != eventTarget) {
        return;
    }

    // If this is a collapsible section, toggle its collapse state.
    if (selSectionDom.classList.contains("section-topic-timed") && !selSectionDom.classList.contains("section-userhidden")) {
        M.course.format.fmtCollapseSet(selSectionDom);
    }

    // If a section anchor is specified in the URL bar, clear it, since it may no longer be relevant.
    if (window.location.hash && window.location.hash != "#") {
        history.pushState(null, document.title,
                          window.location.href.substr(0, window.location.href.length - window.location.hash.length));
    }

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
                        .querySelectorAll("body.format-multitopic .course-content ul.sections li.section.section-topic-timed");
    for (var sectionCount = 0; sectionCount < sectionsDom.length; sectionCount++) {
        var sectionDom = sectionsDom[sectionCount];
        M.course.format.fmtCollapseSet(sectionDom,
                                       sectionDom == selSectionDom && !sectionDom.classList.contains("section-userhidden"));
    }

    // Scroll to the specified section.
    if (selSectionDom) {
        selSectionDom.scrollIntoView();
    }

};

/**
 * Initialise: Set the initial state of collapsible sections, and watch for user input.
 */
M.course.format.fmtCollapseInit = function() {

    // Don't run unless the document is loaded.
    if (document.readyState != "complete") {
        return;
    }

    // Set the initial state of collapsible sections.
    M.course.format.fmtCollapseOnHashChange();

    // Capture possible clicks on course section headings.
    document.querySelector("body.format-multitopic .course-content ul.sections")
        .addEventListener("click", M.course.format.fmtCollapseOnClick);

    // Capture clicks on any other course section links.
    window.addEventListener("hashchange", M.course.format.fmtCollapseOnHashChange);

};

// Run initialisation when the page is loaded, or now, if the page is already loaded.
document.addEventListener("readystatechange", M.course.format.fmtCollapseInit);
M.course.format.fmtCollapseInit();
