@format @format_multitopic
Feature: Sections can be moved in Multitopic format
  In order to rearrange my course contents
  As a teacher
  I need to move sections

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

  Scenario: Move first-level page forward and back
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    Then I should see "Section A" in the "#section-1" "css_element"
    And I should see "Move page right" in the "#section-1" "css_element"
    And I should not see "Move page left" in the "#section-1" "css_element"
    And I click on "Move page right" "link" in the "#section-1" "css_element"
    And I should see "Section A" in the "#section-3" "css_element"
    And I should see "Move page left" in the "#section-3" "css_element"
    And I should not see "Move page right" in the "#section-3" "css_element"
    And I click on "Move page left" "link" in the "#section-3" "css_element"
    And I should see "Section A" in the "#section-1" "css_element"

  Scenario: Move second-level page forward and back
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    And I click on "Section B" "link" in the ".course-content .nav ~ .nav" "css_element"
    Then I should see "Section B" in the "#section-2" "css_element"
    And I should see "Move page left" in the "#section-2" "css_element"
    And I should see "Move page right" in the "#section-2" "css_element"
    And I click on "Move page right" "link" in the "#section-2" "css_element"
    And I should see "Section B" in the "#section-4" "css_element"
    And I should see "Move page left" in the "#section-4" "css_element"
    And I should not see "Move page right" in the "#section-4" "css_element"
    And I click on "Move page left" "link" in the "#section-4" "css_element"
    And I should see "Section B" in the "#section-2" "css_element"

  Scenario: Raise and lower page level
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    And I click on "Section B" "link" in the ".course-content .nav ~ .nav" "css_element"
    Then I should see "Section B" in the "#section-2" "css_element"
    And I should see "Raise page level" in the "#section-2" "css_element"
    And I should not see "Lower page level" in the "#section-2" "css_element"
    And I click on "Raise page level" "link" in the "#section-2" "css_element"
    And I should see "Section B" in the "#section-2" "css_element"
    And I should see "Lower page level" in the "#section-2" "css_element"
    And I should not see "Raise page level" in the "#section-2" "css_element"
    And I click on "Lower page level" "link" in the "#section-2" "css_element"
    And I should see "Section B" in the "#section-2" "css_element"

  Scenario: Move topic between pages
    Given I click on "Section A" "link" in the ".course-content .nav" "css_element"
    And I click on "Section B" "link" in the ".course-content .nav ~ .nav" "css_element"
    Then I should see "Section C" in the "#section-3" "css_element"
    And I should see "Move to previous page" in the "#section-3" "css_element"
    And I should see "Move to next page" in the "#section-3" "css_element"
    And I click on "Move to next page" "link" in the "#section-3" "css_element"
    And I should see "Section C" in the "#section-5" "css_element"
    And I should see "Move to previous page" in the "#section-5" "css_element"
    And I should not see "Move to next page" in the "#section-5" "css_element"
    And I click on "Move to previous page" "link" in the "#section-5" "css_element"
    And I should see "Section C" in the "#section-3" "css_element"
