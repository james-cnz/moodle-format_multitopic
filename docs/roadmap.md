# Multitopic: Roadmap ⭐


### Known issues

* Setting access restrictions on pages does not effectively prevent access to activities in contained subpages or topics.
* Changing section name isn't immediately reflected in tabs.
* Reorganising sections or moving activities doesn't change activity dates.
* Sections don't drag and drop immediately after non-AJAX move.
* When max sections is reached, add page tabs are still shown, but add topic links aren't (inconsistency).


### Code

* Improve comments.
* Write more unit tests.
* Prefer IDs over section numbers in AJAX.
* Standardise use of new lang_string() vs get_string().
* Remove unused code since commit e605b0b73f164c1c2c1436730386712b3b0e9090:
  classes/course_renderer_wrapper.php (plus include in renderer.php), _course_jumpto.php .
* Check use of section_info type annotations.
* Banner display vs banner preview, ensure consistent handling of non-image files.
* Update copyright notices (add "onward").


### UX/UI

* Improve section image user experience.
