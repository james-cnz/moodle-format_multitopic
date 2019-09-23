/**
 * Preview banner slice changes.
 *
 * On the course edit page, when banner details are changed, show a live preview.
 * This script is included in the banner, because I couldn't see a way to include it on the course edit page.
 */

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Change the banner slice to match user input.
 *
 * @return void
 */
M.course.format.fmt_banner_preview_slice = function () {

    // Fetch banner slice user input.
    var bannerslice_dom = document.querySelector("body.format-multitopic div#page select#id_bannerslice");
    var bannerslice     = bannerslice_dom.options[bannerslice_dom.selectedIndex].value;

    // Locate banner.
    var header_dom      = document.querySelector("body.format-multitopic div#course-header, body.format-multitopic div#page-mast");
    var banner_dom      = header_dom.querySelector("div#course-header-banner");

    // Update banner CSS style for new banner slice.
    var banner_style_old = banner_dom.getAttribute("style");
    // This expression should reflect the style code in renderer.php function render_format_multitopic_courseheader .
    banner_style        = banner_style_old.replace(/\b(background-position: [a-z0-9% ]*, [a-z0-9%]+ )([0-9]+%)(;?)/, "$1" + bannerslice + "%" + "$3");
    banner_dom.setAttribute("style", banner_style);

};

/**
 * Change the banner image to match the course image thumbnail.
 *
 * @return void
 */
M.course.format.fmt_banner_preview_image = function () {

    // Fetch course image user input.
    var image_filemanager_dom = document.querySelector("body.format-multitopic div#page #id_descriptionhdr .filemanager");
    var image_file_dom  = image_filemanager_dom.classList.contains("fm-nofiles") ? null :
                            image_filemanager_dom.querySelector(".fp-content .fp-file.fp-hascontextmenu, "
                                                                + ".fp-content .fp-filename-icon.fp-hascontextmenu");
    var image_thumb_dom = image_file_dom ? image_file_dom.querySelector(".fp-thumbnail img.realpreview, .fp-icon img.realpreview") : null;
    var image_url       = image_file_dom ? image_thumb_dom.getAttribute("src").split("?")[0] : "";
    var image_filename  = image_file_dom ? image_file_dom.querySelector(".fp-filename").textContent : "";
    var image_name      = (image_filename.lastIndexOf(".") > -1) ? image_filename.substr(0, image_filename.lastIndexOf(".")) : image_filename;

    // Locate banner.
    var header_dom      = document.querySelector("body.format-multitopic div#course-header, body.format-multitopic div#page-mast");
    var banner_dom      = header_dom.querySelector("div#course-header-banner");
    var banner_attribution_dom = header_dom.querySelector("p#course-header-banner_attribution");

    // Update banner CSS style and attribution for new image.
    var banner_style_old = banner_dom.getAttribute("style");
    // This expression should reflect the style code in renderer.php function render_format_multitopic_courseheader .
    var banner_style    = banner_style_old.replace(/\b(background-image: [a-z-]+\((?:[a-z- ,]|\([0-9.% ,]+\))+\), )(url\('[^']*'\))(;?)/, "$1" + "url('" + image_url + "')" + "$3");
    banner_dom.setAttribute("style", banner_style);
    banner_attribution_dom.textContent = banner_attribution_dom.textContent.replace(/([^:]*: )(.*)/, "$1" + image_name + " ...");
    banner_attribution_dom.setAttribute("style", "visibility: visible;");
    // TODO: Hide when no image?

};

/**
 * Initialise: Watch for user input.
 *
 * @return void
 */
M.course.format.fmt_banner_preview_init = function () {

    // Don't run unless the document is loaded.
    if (document.readyState != "complete") {
        return;
    }

    // Watch for banner slice user input.
    var bannerslice_dom = document.querySelector("body.format-multitopic div#page select#id_bannerslice");
    if (bannerslice_dom) {
        bannerslice_dom.addEventListener("change", M.course.format.fmt_banner_preview_slice);
    }

    // Watch for course image user input.
    var image_filemanager_dom = document.querySelector("body.format-multitopic div#page #id_descriptionhdr .filemanager");
    if (image_filemanager_dom) {
        const config = { attributes: true, childList: true, subtree: true };
        const observer = new MutationObserver(M.course.format.fmt_banner_preview_image);
        observer.observe(image_filemanager_dom, config);
    }

};

// Run initialisation when the page is loaded, or now, if the page is already loaded.
document.addEventListener("readystatechange", M.course.format.fmt_banner_preview_init);
M.course.format.fmt_banner_preview_init(null);
