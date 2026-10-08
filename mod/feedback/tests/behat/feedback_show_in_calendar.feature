@mod @mod_feedback
Feature: Control whether a feedback's open and close dates appear in the calendar
  In order to keep the calendar uncluttered
  As a teacher
  I need to decide whether a date bound feedback is listed in the calendar

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "blocks" exist:
      | blockname      | contextlevel | reference | pagetypepattern | defaultregion |
      | calendar_month | Course       | C1        | course-view-*   | site-post     |

  Scenario: A date bound feedback is not shown in the calendar by default
    Given the following "activities" exist:
      | activity | name               | course | idnumber  | timeopen                         | timeclose                                  | showincalendar |
      | feedback | Test feedback name | C1     | feedback1 | ##first day of this month noon## | ##first day of this month noon +24 hours## | 0              |
    When I am on the "C1" Course page logged in as student1
    And I hover over day "1" of this month in the mini-calendar block
    Then I should not see "Test feedback name opens"

  Scenario: A date bound feedback is shown in the calendar when the setting is enabled
    Given the following "activities" exist:
      | activity | name               | course | idnumber  | timeopen                         | timeclose                                  | showincalendar |
      | feedback | Test feedback name | C1     | feedback1 | ##first day of this month noon## | ##first day of this month noon +24 hours## | 1              |
    When I am on the "C1" Course page logged in as student1
    And I hover over day "1" of this month in the mini-calendar block
    Then I should see "Test feedback name opens"

  Scenario: Enabling and then disabling the setting adds and removes the calendar events
    Given the following "activities" exist:
      | activity | name               | course | idnumber  | timeopen                         | timeclose                                  | showincalendar |
      | feedback | Test feedback name | C1     | feedback1 | ##first day of this month noon## | ##first day of this month noon +24 hours## | 0              |
    And I am on the "Test feedback name" "feedback activity editing" page logged in as teacher1
    When I set the field "Show in calendar" to "1"
    And I press "Save and return to course"
    And I hover over day "1" of this month in the mini-calendar block
    Then I should see "Test feedback name opens"
    And I am on the "Test feedback name" "feedback activity editing" page
    And I set the field "Show in calendar" to "0"
    And I press "Save and return to course"
    And I hover over day "1" of this month in the mini-calendar block
    Then I should not see "Test feedback name opens"
