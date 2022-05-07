@format @format_multitopic
Feature: Show/hide course sections in Multitopic format
  In order to delay sections availability
  As a teacher
  I need to show or hide sections

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | coursedisplay | numsections |
      | Course 1 | C1 | multitopic | 0 | 5 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I edit the section "1" and I fill the form with:
      | Custom                     | 1         |
      | New value for Section name | Section A |
      | Level                      | 0         |
    And I edit the section "2" and I fill the form with:
      | Custom                     | 1         |
      | New value for Section name | Section B |
      | Level                      | 1         |
    And I edit the section "3" and I fill the form with:
      | Custom                     | 1         |
      | New value for Section name | Section C |
      | Level                      | 2         |
    And I edit the section "4" and I fill the form with:
      | Custom                     | 1         |
      | New value for Section name | Section D |
      | Level                      | 0         |
    And I edit the section "5" and I fill the form with:
      | Custom                     | 1         |
      | New value for Section name | Section E |
      | Level                      | 2         |
    And I click on "General" "link" in the ".course-content .nav" "css_element"

  Scenario: Hide and show page
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    Then I should see "Section A" in the "#section-1" "css_element"
    And I hide section "1"
    And section "1" should be hidden
    And I click on "Section B" "link" in the ".course-content .nav ~ .nav" "css_element"
    And I should see "Section B" in the "#section-2" "css_element"
    And section "2" should be hidden
    And I should see "Section C" in the "#section-3" "css_element"
    And section "3" should be hidden
    And I click on "Section D" "link" in the ".course-content .nav" "css_element"
    And I should see "Section D" in the "#section-4" "css_element"
    And section "4" should be visible
    And I click on "Section A" "link" in the ".course-content .nav" "css_element"
    And I should see "Section A" in the "#section-1" "css_element"
    And I show section "1"
    And section "1" should be visible

  Scenario: Hide and show topic
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    And I click on "Section B" "link" in the ".course-content .nav ~ .nav" "css_element"
    Then I should see "Section B" in the "#section-2" "css_element"
    And I should see "Section C" in the "#section-3" "css_element"
    And section "3" should be visible
    And I hide section "3"
    And section "3" should be hidden
    And section "2" should be visible
    And I show section "3"
    And section "3" should be visible
