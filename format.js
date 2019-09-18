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
M.course.format.get_config = function () {
    return {
        container_node: 'ul',
        container_class: 'sections',
        section_node: 'li',
        section_class: 'section'
    };
};

/**
 * Swap section
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 * @return {NodeList} section list
 */
M.course.format.swap_sections = function (Y, node1, node2) {
    var CSS = {
        COURSECONTENT: 'course-content',
        SECTIONADDMENUS: 'section_add_menus'
    };

    var sectionlist = Y.Node.all('.' + CSS.COURSECONTENT + ' ' + M.course.format.get_section_selector(Y));
    // Swap menus.
    // REMOVED: Custom section add menus now use section IDs instead of section numbers, so shouldn't be swapped?

};

/**
 * Process sections after ajax response
 *
 * @param {YUI} Y YUI3 instance
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 * @return void
 */
M.course.format.process_sections = function (Y, sectionlist, response, sectionfrom, sectionto) {
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

            if (sectionlist.item(i).hasClass("section-topic-timed")) {
                M.course.format.fmt_collapse_icon_yui(sectionlist.item(i));
            }

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
 * @param {HTMLLIElement} section_dom The collapsible section
 * @return void
 */
M.course.format.fmt_collapse_icon = function (section_dom) {
    show = !section_dom.classList.contains("section-collapsed");
    var icon_dom = section_dom.querySelector("h3.sectionname i.icon");
    if (!icon_dom) { return; }
    icon_dom.setAttribute("class", show ? "icon fa fa-caret-down fa-fw" : "icon fa fa-caret-right fa-fw");
}

/**
 * Set the appropriate expand/collapse icon for a collapsible section specified as a YUI node
 *
 * @param {YUI} section_yui The collapsible section
 * @return void
 */
M.course.format.fmt_collapse_icon_yui = function (section_yui) {
    show = !section_yui.hasClass("section-collapsed");
    var icon_yui = section_yui.one("h3.sectionname i.icon");
    if (!icon_yui) { return; }
    icon_yui.setAttribute("class", show ? "icon fa fa-caret-down fa-fw" : "icon fa fa-caret-right fa-fw");
}

/**
 * Set or toggle the expand/collapse state for a specified collapsible section
 *
 * @param {HTMLLIElement} section_dom The collapsible section
 * @param {boolean?} show Whether the section should be shown, or undefined to toggle
 * @return void
 */
M.course.format.fmt_collapse_set = function (section_dom, show) {

    if (show === undefined) {
        show = section_dom.classList.contains("section-collapsed");
    }

    if (show) {
        section_dom.classList.remove("section-collapsed");
        section_dom.classList.add("section-expanded");
    } else {
        section_dom.classList.remove("section-expanded");
        section_dom.classList.add("section-collapsed");
    }

    M.course.format.fmt_collapse_icon(section_dom);

};

/**
 * Toggle section expand/collapse state, as and where appropriate, for a given click event.
 *
 * @param {MouseEvent} event The mouse click
 * @return void
 */
M.course.format.fmt_collapse_onclick = function (event) {

    // Find the clicked link anchor element (we may instead have been given the section's icon, from inside the anchor element).
    var event_target = event.target;
    if (event_target && event_target.tagName && event_target.tagName != "A") {
        event_target = event_target.parentElement;
    }

    // Find the linked section, and check that the link is the one on the section's heading, otherwise return to normal event handling.
    if (!event_target.hash) {
        return;
    }
    var anchor = event_target.hash.substr(1);
    var sel_section_dom = anchor ? document.querySelector("body.format-multitopic .course-content ul.sections li.section.section-topic." + anchor + ","
                                                        + "body.format-multitopic .course-content ul.sections li.section.section-topic#" + anchor) : null;
    if (!sel_section_dom || sel_section_dom.querySelector(".content h3 a") != event_target) {
        return;
    }

    // If this is a collapsible section, toggle its collapse state.
    if (sel_section_dom.classList.contains("section-topic-timed") && !sel_section_dom.classList.contains("section-userhidden")) {
        M.course.format.fmt_collapse_set(sel_section_dom);
    }

    // If a section anchor is specified in the URL bar, clear it, since it may no longer be relevant.
    if (window.location.hash && window.location.hash != "#") {
        history.pushState(null, document.title, window.location.href.substr(0, window.location.href.length - window.location.hash.length));
    }

    // Override normal event handling.
    event.preventDefault();

};

/**
 * Expand and scroll to the section specified in the URL bar, and collapse other sections.
 *
 * @param {HashChangeEvent?} _event The triggering event, if any
 * @return void
 */
M.course.format.fmt_collapse_onhashchange = function (_event) {

    // Find the specified section.
    var anchor = window.location.hash.substr(1);
    var sel_section_dom = anchor ? document.querySelector("body.format-multitopic .course-content ul.sections li.section.section-topic." + anchor + ","
                                                        + "body.format-multitopic .course-content ul.sections li.section.section-topic#" + anchor) : null;

    // Set the appropriate collapse state for all collapsible sections.
    var sections_dom = document.querySelectorAll("body.format-multitopic .course-content ul.sections li.section.section-topic-timed");
    for (var section_count = 0; section_count < sections_dom.length; section_count++) {
        var section_dom = sections_dom[section_count];
        M.course.format.fmt_collapse_set(section_dom, section_dom == sel_section_dom && !section_dom.classList.contains("section-userhidden"));
    }

    // Scroll to the specified section.
    if (sel_section_dom) {
        sel_section_dom.scrollIntoView();
    }

};

/**
 * Initialise: Set the initial state of collapsible sections, and watch for user input.
 *
 * @return void
 */
M.course.format.fmt_collapse_init = function () {

    // Don't run unless the document is loaded.
    if (document.readyState != "complete") {
        return;
    }

    // Capture possible clicks on course section headings.
    document.querySelector("body.format-multitopic .course-content ul.sections").addEventListener("click", M.course.format.fmt_collapse_onclick);

    // Capture clicks on any other course section links.
    window.addEventListener("hashchange", M.course.format.fmt_collapse_onhashchange);

    // Set the initial state of collapsible sections.
    M.course.format.fmt_collapse_onhashchange();
};

// Run initialisation when the page is loaded, or now, if the page is already loaded.
document.addEventListener("readystatechange", M.course.format.fmt_collapse_init);
M.course.format.fmt_collapse_init();
