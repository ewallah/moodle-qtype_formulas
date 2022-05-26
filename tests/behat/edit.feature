@qtype @qtype_formulas
Feature: Test editing a Formulas question
  As a teacher
  In order to be able to update my Formulas question
  I need to edit them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name                     | template   |
      | Test questions   | formulas | formulas-001 for editing | test1      |

  @javascript
  Scenario: Edit a Formulas question
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I choose "Edit question" action for "formulas-001 for editing" in the question bank
    And I set the following fields to these values:
      | Question name | |
    And I press "id_submitbutton"
    Then I should see "You must supply a value here."
    When I set the following fields to these values:
      | Question name | Edited formulas-001 name |
    And I press "id_submitbutton"
    Then I should see "Edited formulas-001 name"
    When I choose "Edit question" action for "Edited formulas-001 name" in the question bank
    And I set the following fields to these values:
      | Random variables     | v = {40:120:10}; dt = {2:6};  |
    And I press "id_submitbutton"
    Then I should see "Edited formulas-001 name"
