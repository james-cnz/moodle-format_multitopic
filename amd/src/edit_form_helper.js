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

/* eslint space-before-function-paren: 0 */

/**
 * Javascript Module to handle changes which are made to the course > edit settings
 * form as the user changes various options
 * e.g. if user deselects one item, this deselects another linked one for them
 * if the user picks an invalid option it will be detected by format_tiles::edit_form_validation (lib.php)
 * but this is to help them avoid triggering that if they have JS enabled
 *
 * @module      edit_form_helper
 * @package     course/format
 * @subpackage  multitopic
 * @copyright   2018 David Watson {@link http://evolutioncode.uk}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since       Moodle 3.3
 */

define(["jquery", "core/notification", "core/str", "core/templates"],
    function ($, Notification, str, Templates) {
        "use strict";
        return {
            init: function (pageType, courseDefaultIcon, courseId, sectionId, section, userId, allowphototiles, documentationUrl) {
                $(document).ready(function () {

                    // If we are on the course edit settings form, render a button to be added to it.
                    // Put it next to the existing drop down select box for course default tile icon.
                    // Add it to the page.

                    var selectedIconName;
                    var selectBox;
                    // alert("page type: " + pageType + "\ntest: " + pageType.endsWith("course_editsection"));
                    if (pageType.endsWith("course_editsection")) {
                        selectBox = $("#id_tileicon");
                        selectedIconName = $("#id_tileicon option:selected").text();

                        var currentIcon;
                        if (selectBox.val() === "") {
                            currentIcon = courseDefaultIcon;
                        } else {
                            currentIcon = selectBox.val();
                        }
                        Templates.render("format_multitopic/icon_picker_launch_btn", {
                            initialicon: currentIcon,
                            initialname: selectedIconName,
                            sectionId: sectionId,
                            allowphototiles: allowphototiles
                        }).done(function (html) {
                            $(html).insertAfter(selectBox);

                            // We can hide the original select box now as users will use the button instead.
                            selectBox.hide();
                            require(["format_multitopic/edit_icon_picker"], function(iconPicker) {
                                iconPicker.init(courseId, pageType, allowphototiles, documentationUrl);
                            });
                        });
                    }

                });
            }
        };
    }
);