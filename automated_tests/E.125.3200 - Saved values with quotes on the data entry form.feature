Feature: E.125.3200 - The system shall keep change highlighting accurate, shall not run saved values as script, and shall limit source file changes to users permitted to save system settings, in the Enhance Reason For Change external module.

  As a REDCap end user
  I want change highlighting to work whatever a saved value contains
  So that the content of a value cannot change how the data entry form behaves

  Scenario: Enable external module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Enhance reason for change - v1.1.2"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Enhance reason for change"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Enhance reason for change - v1.1.2"

  Scenario: Enable external module in project with change highlighting
    Given I create a new project named "E.125.3200" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/E1253200.xml", and clicking the "Create Project" button
    And I click on the second link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Enhance reason for change - v1.1.2"
    Then I should see "Enhance reason for change - v1.1.2"
    When I click on the button labeled "Configure"
    Then I should see "Configure Module"
    And I check the checkbox labeled "When checked, any change to a field will highlight its row's right border"
    Then I click on the button labeled "Save"
    And I should see "Enhance reason for change - v1.1.2"

    # Test_User1 can open the project but cannot save system settings
    Given I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

  Scenario: A saved value written as script does not run
    Given I click on the link labeled "Add / Edit Records"
    When I click on the button labeled "Add new record for the arm selected above"
    And I click the bubble to add a record for the "Data Types" longitudinal instrument on event "Event 1"
    And I enter "');window.erfcInjected=1;//" into the data entry form field labeled "Text Box"
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then I should see "successfully"
    And the text field labeled "Text Box" should have the value "');window.erfcInjected=1;//"
    And the saved value should not have run as script

    When I clear field and enter "x\"</script><script>window.erfcInjected=2;</script>" into the input field labeled "Text Box"
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then I should see "successfully"
    And the saved value should not have run as script

  Scenario: Highlighting still works when a saved value contains an apostrophe
    When I clear field and enter "O'Brien" into the input field labeled "Text Box"
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then I should see "successfully"
    When I change the text field labeled "Text2" to "ABC" without saving
    Then I should see the field labeled "Text2" with a 2px "solid" right border in "red" color

  Scenario: An autocomplete label containing an apostrophe is only highlighted when changed
    Given I change the autocomplete field "multiple_dropdown_auto" to "O'Brien" without saving
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then I should see "successfully"
    And the autocomplete field "multiple_dropdown_auto" should show "O'Brien"
    When I leave the autocomplete field "multiple_dropdown_auto" unchanged
    Then the field "multiple_dropdown_auto" should have no right border
    When I change the autocomplete field "multiple_dropdown_auto" to "Plain" without saving
    Then I should see the field labeled "Multiple Choice Dropdown Auto" with a 2px "solid" right border in "red" color

  Scenario Outline: An unchanged autocomplete field is not highlighted whatever its label contains
    Given I change the autocomplete field "multiple_dropdown_auto" to "<shown label>" without saving
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then I should see "successfully"
    And the autocomplete field "multiple_dropdown_auto" should show "<shown label>"
    When I leave the autocomplete field "multiple_dropdown_auto" unchanged
    Then the field "multiple_dropdown_auto" should have no right border

    Examples:
      | shown label |
      | T1 < = 3 cm |
      | Bold label  |
      | A & B       |

  Scenario: Clearing an autocomplete field highlights it
    When I clear the autocomplete field "multiple_dropdown_auto" without saving
    Then I should see the field labeled "Multiple Choice Dropdown Auto" with a 2px "solid" right border in "red" color
    And I select the submit option labeled "Save & Stay" on the Data Collection Instrument
    Then the field "multiple_dropdown_auto" should have no right border

  Scenario: Setting a missing data code on an autocomplete field highlights it
    When I set the missing data code "NA" on the field "multiple_dropdown_auto"
    Then I should see the field labeled "Multiple Choice Dropdown Auto" with a 2px "solid" right border in "red" color
    And I click on the button labeled "Save & Exit Form"

  Scenario: A system settings save is refused for a user without permission
    Given I click on the link labeled "My Projects"
    And I logout
    And I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.125.3200"
    Then I should see "Project Home and Design"
    And a system settings save by the current user should be refused and leave the source files unchanged
    And I logout
    And I login to REDCap with the user "Test_Admin"

  Scenario: Disable external module
    Given I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Enhance reason for change - v1.1.2"
