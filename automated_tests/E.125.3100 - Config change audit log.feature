Feature: E.125.3100 - The system shall record configuration changes for the Enhance reason for change external module (who, when, old->new) to the module's View Logs page.

  As a REDCap administrator
  I want every configuration change to be written to the module's External Module Logs
  So that there is an audit trail of who changed which setting, when, and from what value to what.

  Scenario: Enable external module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Enhance reason for change - v1.1.1"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Enhance reason for change"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Enhance reason for change - v1.1.1"

  Scenario: First system configuration save logs the initial values
    # This module carries system-scope settings as well as project-scope ones, so
    # the audit trail is verified at both scopes. System settings are configured
    # from the Control Center and log under "Configuration changed (system)".
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Enhance reason for change - v1.1.1"

    Given I click on the button labeled "Configure"
    Then I should see "Configure Module"
    When I check the checkbox labeled "When checked, the text capacity in the reason for change free text box changes"
    And I enter "Sys Option 1" into the input field labeled "1. Provides a default option for the reason for change dropdown"
    And I click on the button labeled "Save"
    Then I should see "Enhance reason for change - v1.1.1"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module                    | Message                        | UserName   |
      | enhance_reason_for_change | Configuration changed (system) | Test_Admin |

    # The hook logs one entry per changed key in config.json order
    # (enlarge-reason-text-capacity then sys-reason-for-change-option), and View
    # Logs shows newest first, so the FIRST button is sys-reason-for-change-option
    # and the SECOND is enlarge-reason-text-capacity. A repeatable setting is
    # stored and logged as a JSON array of its values.
    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                       |
      | setting   | sys-reason-for-change-option |
      | old_value | (empty)                     |
      | new_value | ["Sys Option 1"]            |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    When I click on the second button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                        |
      | setting   | enlarge-reason-text-capacity |
      | old_value | (empty)                      |
      | new_value | 1                            |

  Scenario: A repeatable left blank is not logged as a change
    # Regression guard for the phantom-diff bug: REDCap returns a repeatable the
    # admin never filled as an array of empty entries ([null]), not as null, so a
    # naive comparison logged "(empty) -> [null]" for a setting nobody touched.
    # reason-for-change-option is an optional repeatable, so it can reach the save
    # handler blank -- this module is directly exposed.
    #
    # A fresh project is required: the phantom only appears on the FIRST save,
    # when there is no prior snapshot to diff against.
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I create a new project named "E.125.3100.100" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Enhance reason for change - v1.1.1"
    Then I should see "Enhance reason for change - v1.1.1"

    # Tick ONLY the checkbox, the FIRST key in config.json order. Leave the
    # repeatable option list (key 2) and the remaining settings untouched.
    Given I click on the button labeled "Configure"
    Then I should see "Configure Module"
    When I check the checkbox labeled "When checked, a dropdown of reasons for change will be available for the user to select from"
    And I click on the button labeled "Save"
    Then I should see "Enhance reason for change - v1.1.1"

    #VERIFY - exactly one setting was logged, and it is the checkbox.
    # This assertion is order-based: the hook logs in config.json order and View
    # Logs shows newest first, so the blank repeatable (key 2) would be NEWER than
    # the checkbox (key 1). If the phantom ever returns, the first button here
    # becomes reason-for-change-option and this scenario fails.
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see 1 row in the external modules logs table
    And I should see a table header and row containing the following values in a table:
      | Module                    | Message                         | UserName   |
      | enhance_reason_for_change | Configuration changed (project) | Test_Admin |

    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                               |
      | setting   | provide-reasons-for-change-dropdown |
      | old_value | (empty)                             |
      | new_value | 1                                   |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

  Scenario: First project configuration save logs the initial values
    Given I click on the link labeled "My Projects"
    And I create a new project named "E.125.3100.200" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Enhance reason for change - v1.1.1"
    Then I should see "Enhance reason for change - v1.1.1"

    Given I click on the button labeled "Configure"
    Then I should see "Configure Module"
    When I check the checkbox labeled "When checked, a dropdown of reasons for change will be available for the user to select from"
    And I enter "Option A" into the input field labeled "1. Provide an option for the reason for change dropdown"
    And I click on the button labeled "Save"
    Then I should see "Enhance reason for change - v1.1.1"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module                    | Message                         | UserName   |
      | enhance_reason_for_change | Configuration changed (project) | Test_Admin |

    # config.json order is provide-reasons-for-change-dropdown then
    # reason-for-change-option, so newest-first the FIRST button is the option
    # list and the SECOND is the dropdown checkbox.
    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                    |
      | setting   | reason-for-change-option |
      | old_value | (empty)                  |
      | new_value | ["Option A"]             |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    When I click on the second button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                               |
      | setting   | provide-reasons-for-change-dropdown |
      | old_value | (empty)                             |
      | new_value | 1                                   |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

  Scenario: Changing a setting logs an old->new audit entry
    # rctf starts each scenario from a clean browser page, so re-navigate to the
    # project fresh (same pattern as the other continuation scenarios).
    Given I click on the link labeled "My Projects"
    And I click on the link labeled "E.125.3100.200"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Enhance reason for change - v1.1.1"

    When I click on the button labeled "Configure"
    Then I should see "Configure Module"
    And I clear field and enter "Option B" into the input field labeled "1. Provide an option for the reason for change dropdown"
    Then I click on the button labeled "Save"
    And I should see "Enhance reason for change - v1.1.1"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module                    | Message                         | UserName   |
      | enhance_reason_for_change | Configuration changed (project) | Test_Admin |

    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                    |
      | setting   | reason-for-change-option |
      | old_value | ["Option A"]             |
      | new_value | ["Option B"]             |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    # Disable the external module from the Control Center
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Enhance reason for change - v1.1.1"

  Scenario: Verify no exceptions are thrown in the system
    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - enhance_reason_for_change"
