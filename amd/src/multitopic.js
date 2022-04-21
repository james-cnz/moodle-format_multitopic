import jQuery from 'jquery';

export const init = () => {
    let tabcontent = jQuery("#adaptable-course-tab-content");
    tabcontent.on('updated', function(e) {
        let sectionid = e.target.dataset.itemid;
        let newname = e.target.dataset.value;
        jQuery(".nav-link .tab_content[data-itemid=" + sectionid + "]").html(newname);
    });
};
