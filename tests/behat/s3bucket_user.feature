@repository @repository_s3bucket @javascript
Feature: S3 bucket repository can be used in user context

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student  | student   | 1        |
      | teacher  | teacher   | 1        |
      | editor   | editor    | 1        |
      | manager  | manager   | 1        |
    And the following "role assigns" exist:
      | user    | role           | contextlevel | reference |
      | teacher | editingteacher | System       | |
      | editor  | editingteacher | System       | |
      | manager | manager        | System       | |
    And the following "blocks" exist:
      | blockname     | contextlevel | reference | pagetypepattern | defaultregion |
      | private_files | System       | 1         | my-index        | side-post     |
    And I enable repository "s3bucket"

  Scenario: A teacher cannot browse a private s3 bucket even with a legacy instance
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I follow "Dashboard"
    And I follow "Manage private files..."
    When I click on "Add..." "button" in the "Files" "form_row"
    Then I should not see "teacherbucket"

  Scenario: An admin does not have access to a private s3 bucket repository of a teacher
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I log out
    And I log in as "admin"
    And I follow "Dashboard"
    And I follow "Manage private files..."
    When I click on "Add..." "button" in the "Files" "form_row"
    Then I should not see "teacherbucket"
    And I click on "Close" "button" in the "File picker" "dialogue"

  Scenario: An admin does not have access to a private s3 bucket repository in user context
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I log out
    When I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I follow "teacher 1"
    # And I click on "Preferences" "link" in the ".profile_tree" "css_element"
    # And I follow "Manage instances" Throws an error.
    # Then I should see "You can not view/edit repository instances of another user"

  Scenario: A manager does not have access to a private s3 bucket repository in user context
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I log out
    And I log in as "manager"
    And I follow "Dashboard"
    When I follow "Manage private files..."
    And I click on "Add..." "button" in the "Files" "form_row"
    Then I should not see "teacherbucket"

  Scenario: Another teacher does not has access to a private s3 bucket repository in user context
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I log out
    And I log in as "editor"
    And I follow "Dashboard"
    And I follow "Manage private files..."
    When I click on "Add..." "button" in the "Files" "form_row"
    Then I should not see "teacherbucket"

  Scenario: A student cannot add files from the s3 bucket repository in user context
    Given I log in as "teacher"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | teacherbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I log out
    When I log in as "student"
    And I follow "Preferences" in the user menu
    Then I should see "Repositories"
    And I follow "Manage instances"
    But I should not see "Amazon S3 bucket"

  Scenario: A manager cannot browse a private s3 bucket even with a legacy instance
    Given I log in as "manager"
    And the following "repository_s3bucket > s3buckets" exist:
      | name          | bucket_name | access_key | secret_key | endpoint              | contextlevel |
      | managerbucket | testbucket  | test       | test       | http://localhost:4566 | User         |
    And I follow "Dashboard"
    And I follow "Manage private files..."
    When I click on "Add..." "button" in the "Files" "form_row"
    Then I should not see "managerbucket"
