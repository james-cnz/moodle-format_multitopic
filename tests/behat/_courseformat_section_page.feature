@format @format_multitopic
Feature: Section course page (Multitopic format)
  In order to improve the course page
  As a user
  I need to be able to see a section in a single page

  Background:
    Given the following "course" exists:
      | fullname         | Course 1   |
      | shortname        | C1         |
      | category         | 0          |
      | format           | multitopic |
      | numsections      | 3          |
      | initsections     | 1          |
    And the following "activities" exist:
      | activity | name                | course | idnumber | section |
      | assign   | Activity sample 0.1 | C1     | sample1  | 0       |
      | assign   | Activity sample 1.1 | C1     | sample1  | 1       |
      | assign   | Activity sample 1.2 | C1     | sample2  | 1       |
      | assign   | Activity sample 1.3 | C1     | sample3  | 1       |
      | assign   | Activity sample 2.1 | C1     | sample3  | 2       |
      | assign   | Activity sample 2.2 | C1     | sample3  | 2       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I edit the section "1" and I fill the form with:
      | Level                      | 0         |
    And I edit the section "2" and I fill the form with:
      | Level                      | 0         |
    And I edit the section "3" and I fill the form with:
      | Level                      | 0         |
    And I am on the "C1" "Course" page logged in as "teacher1"

  @javascript
  Scenario: The section page is always expanded
    Given I should not see "Activity sample 1.1" in the "region-main" "region"
    When I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    Then I should see "Activity sample 1.1"
    And I should see "Activity sample 1.2"
    And I should see "Activity sample 1.3"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"

  Scenario: General section is not displayed in the section page
    When I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    Then I should not see "General" in the "#section-1" "css_element"
    And I should not see "Activity sample 0.1" in the "region-main" "region"
    And I should see "Activity sample 1.1"
    And I should see "Activity sample 1.2"
    And I should see "Activity sample 1.3"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"

  @javascript
  Scenario: The tab for sections displays the section page
    Given I turn editing mode on
    And I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    Then I should not see "General" in the "#section-1" "css_element"
    And I should not see "Activity sample 0.1" in the "region-main" "region"
    And I should see "Activity sample 1.1"
    And I should see "Activity sample 1.2"
    And I should see "Activity sample 1.3"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I am on "Course 1" course homepage
    And I click on "Section 2" "link" in the ".course-section-tabs" "css_element"
    And I should not see "General" in the "#section-2" "css_element"
    And I should not see "Activity sample 0.1" in the "region-main" "region"
    And I should not see "Activity sample 1.1"
    And I should not see "Activity sample 1.2"
    And I should not see "Activity sample 1.3"
    And I should see "Activity sample 2.1" in the "region-main" "region"
    And I should see "Activity sample 2.1" in the "region-main" "region"
    # The General section is also displayed in isolation.
    But I am on "Course 1" course homepage
    And I click on "General" "link" in the ".course-section-tabs" "css_element"
    And I should see "General" in the "page" "region"
    And I should see "Activity sample 0.1" in the "region-main" "region"
    And I should not see "Activity sample 1.1" in the "region-main" "region"
    And I should not see "Activity sample 1.2" in the "region-main" "region"
    And I should not see "Activity sample 1.3" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    # The course viewed has been trigered.
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Live logs" in current page administration
    And I should see "Course viewed"

  Scenario: The add topic button is displayed in the section page
    Given I turn editing mode on
    When I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    Then "Add topic" "link" should exist in the "region-main" "region"

  @javascript
  Scenario: Change the section name inline
    # The course index is hidden by default in small devices.
    Given I change window size to "large"
    And I turn editing mode on
    And I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    When I set the field "Edit section name" in the "Section 1" "section" to "Custom section name"

  @javascript
  Scenario: Copy section page permalink URL to clipboard
    Given I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    And I turn editing mode on
    When I choose the "Permalink" item in the "Edit" action menu of the "Section 1" "section"
    And I click on "Copy to clipboard" "link" in the "Permalink" "dialogue"
    Then I should see "Text copied to clipboard"

  Scenario: Blocks are displayed in section page too
    Given I log out
    And the following "blocks" exist:
      | blockname    | contextlevel | reference | pagetypepattern | defaultregion |
      | online_users | Course       | C1        | course-view-*   | site-pre      |
    When I am on the "C1" "Course" page logged in as "teacher1"
    Then I should see "Online users"
    And I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    And I should see "Online users"

  @javascript
  Scenario: Delete a section from the section page redirects to the previous page
    Given I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    And I turn editing mode on
    When I choose the "Delete" item in the "Edit" action menu of the "Section 1" "section"
    And I click on "Delete" "button" in the "Delete section?" "dialogue"
    # Section 1 should be removed.
    Then I should not see "Section 1"
    # The user should be redirected to the course page.
    And I should see "General" in the "page" "region"

  @javascript
  Scenario: When I edit a section from the section page, after saving I stay in the section page
    Given I click on "Section 1" "link" in the ".course-section-tabs" "css_element"
    And I turn editing mode on
    And I choose the "Edit settings" item in the "Edit" action menu of the "Section 1" "section"
    When I set the field "Section name" to "New name for section 1"
    And I press "Save changes"
    Then I should see "New name for section 1" in the "region-main" "region"
    And I should see "Activity sample 1.1"
    And I should see "Activity sample 1.2"
    And I should see "Activity sample 1.3"
    And I should not see "Activity sample 2.1" in the "region-main" "region"
    And I should not see "Activity sample 2.1" in the "region-main" "region"

  @javascript
  Scenario: When I edit a section from the course page, after saving I stay in the course page
    Given I turn editing mode on
    And I open section "0" edit menu
    And I choose "Edit settings" in the open action menu
    When I set the field "Section name" to "New name for section 0"
    And I press "Save changes"
    Then I should see "Course 1" in the "page-header" "region"
    And I should see "New name for section 0" in the "region-main" "region"
