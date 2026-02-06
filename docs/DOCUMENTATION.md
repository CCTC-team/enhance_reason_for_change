# Enhance Reason for Change External Module

## Overview

**Module Name:** Enhance Reason for Change
**Version:** 2.0.0
**Namespace:** `CCTC\EnhanceReasonForChangeModule`
**Framework Version:** 14
**Documentation:** https://github.com/CCTC-team/enhance_reason_for_change

### Authors
- **Richard Hardy** - University of Cambridge - Cambridge Cancer Trials Centre (rmh54@cam.ac.uk)
- **Mintoo Xavier** - Cambridge University Hospital - Cambridge Cancer Trials Centre (mintoo.xavier1@nhs.net)

### Compatibility
| Requirement | Version Range |
|-------------|---------------|
| PHP | 8.0.27 - 8.2.29 |
| REDCap | 13.8.1 - 15.9.1 |

## Purpose

This external module enhances REDCap's built-in "Require a reason when making changes to existing records" functionality by providing:

1. **Extended Text Capacity** - Increases the reason for change text field limit from 200 to 5000 characters
2. **Standardized Dropdown Reasons** - Provides a configurable dropdown of predefined reasons for change
3. **Visual Field Highlighting** - Highlights fields that have been modified with a customizable border style

## Architecture

### Core File
- `EnhanceReasonForChangeModule.php` - Main module class extending `AbstractExternalModule`

### Key Implementation Details

The module works by **directly modifying REDCap's core file** (`DataEntry/index.php`) when enabled at the system level. This approach:

- Inserts custom HTML/JavaScript code for the dropdown functionality
- Modifies the character limit validation from 200 to 5000 characters
- All changes are automatically reverted when the module is disabled

**Important:** The inserted code is wrapped with identifiable comments:
```html
<!-- ****** inserted by Enhance Reasons for Change module ****** -->
[markup here]
<!-- ****** end of insert ****** -->
```

## Configuration Settings

### System-Level Settings (Super Users Only)

| Setting Key | Description | Type |
|-------------|-------------|------|
| `enlarge-reason-text-capacity` | Increases the character limit from 200 to 5000 characters in the reason for change text area | Checkbox |
| `sys-reason-for-change-option` | Default options for the reason for change dropdown (applies to all projects) | Text (Repeatable) |

### Project-Level Settings (Super Users Only)

| Setting Key | Description | Type |
|-------------|-------------|------|
| `provide-reasons-for-change-dropdown` | Enables the dropdown of predefined reasons for users to select | Checkbox |
| `reason-for-change-option` | Project-specific options appended to system-level dropdown options | Text (Repeatable) |
| `highlight-field-when-changed` | Enables visual highlighting of modified fields with a border | Checkbox |
| `highlight-field-when-changed-style` | CSS style for the highlight border (default: `solid 2px red`). Only visible when `highlight-field-when-changed` is enabled (branching logic) | Text |

## Hooks Implemented

### `redcap_module_system_enable($version)`
Called when the module is enabled at the system level:
- Applies character capacity changes if configured
- Inserts dropdown code with system-level reasons

### `redcap_module_system_disable($version)`
Called when the module is disabled at the system level:
- Reverts character capacity to original 200 characters
- Removes all inserted dropdown code

### `validateSettings($settings)`
Validates configuration when settings are saved:
- Ensures at least one setting is configured
- Applies text capacity and dropdown changes based on settings

### `redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)`
Called when a data entry form is displayed:
- Validates project configuration
- Injects JavaScript for field highlighting
- Adds project-specific dropdown options
- Handles various field types including autocomplete and datetime pickers

## Key Methods

### File Modification Methods

All file modification methods include error handling with logging for missing files, read failures, and write failures.

| Method | Description |
|--------|-------------|
| `replaceCharLimit(string $from, string $to)` | Safely replaces character limit code in the core file. Validates exactly one instance exists before replacement |
| `insertCode(string $searchTerm, string $insertCode)` | Inserts code after a multi-line search term |
| `removeCode(string $removeCode)` | Removes previously inserted code |
| `makeInsertCode(?array $reasonForChangeOptions): string` | Generates the dropdown HTML/JavaScript code with XSS-safe output |

### Feature Methods

| Method | Description |
|--------|-------------|
| `applyTextCapacityChange(bool $updateCapacity)` | Toggles character limit between 200 and 5000 |
| `applyDefaultReasonForChangeDropdown(?array $options, bool $empty)` | Manages dropdown code insertion/updates |
| `outputHighlightJs(string $highlightStyle)` | Outputs JavaScript for field change detection and highlighting |
| `addProjectSpecificReasonsForChange()` | Appends project-level options to dropdown |
| `handleProjectSettings()` | Manages project-level feature toggles |

### Utility Methods

| Method | Description |
|--------|-------------|
| `hasNonEmptyValues(?array $values): bool` | Checks if an array contains any non-empty values |
| `showAlert(string $message)` | Displays a sanitized JavaScript alert message |

## Field Highlighting Feature

The field highlighting feature detects changes through multiple interaction methods:

1. **Direct Input** - Standard text/number field changes via `change` event
2. **Autocomplete Fields** - Uses `blur` event on autocomplete dropdowns
3. **Missing Code Buttons** - Listens to `.set_btn` click events
4. **Date/Time Pickers** - Handles both direct input and calendar popup selections
5. **Today/Now Buttons** - Captures `.today-now-btn` click events

### Highlight Style
Default: `solid 2px red`
Customizable via project settings (e.g., `dashed 4px blue`)

## Security Considerations

The module implements several security measures:

1. **XSS Prevention** - Uses `htmlspecialchars()` with `ENT_QUOTES` and `UTF-8` encoding for all user-provided values including dropdown options (`makeInsertCode()`), highlight styles (`outputHighlightJs()`), and alert messages (`showAlert()`)
2. **Style Sanitization** - Filters highlight styles via regex to only allow safe CSS characters (`/[^a-zA-Z0-9\s\-#.,()%]/`)
3. **JSON Encoding** - Uses `json_encode()` with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` flags for safe JavaScript output of project-specific reasons
4. **Settings Validation** - Validates configuration before allowing form submission

## Automated Testing

The module includes comprehensive Cypress automated tests using the Cucumber/Gherkin framework.

### Test Files Location
`automated_tests/`

### Test Categories

| Test ID | Description |
|---------|-------------|
| E.125.100 | Module configuration tests |
| E.125.200 | Enable module on all projects |
| E.125.300 | Module discoverability |
| E.125.400 | Non-admin module enablement |
| E.125.500 | Module visibility for non-admins |
| E.125.800 | Enhance Reason For Change in Projects |
| E.125.1400-1700 | Repeating Events (Double/Single Arm, with/without DAGs) |
| E.125.1800-2100 | Non-Repeating (Double/Single Arm, with/without DAGs) |
| E.125.2200-2500 | Repeating Instruments (Double/Single Arm, with/without DAGs) |
| E.125.2600-2900 | Non-Longitudinal projects (with/without repeating instruments, DAGs) |
| E.125.3000 | Module configuration permissions |

### Custom Step Definitions

Located in `automated_tests/step_definitions/`:

- `external_module.js` - Steps for external module management
- `noncore.js` - Additional test utilities

### Key Test Steps

```gherkin
# Verify field highlighting
I should see the field labeled {string} with a {int}px {string} right border in {string} color

# Verify no highlighting
I should NOT see the field labeled {string} with a colored right border
```

## Installation Notes

1. Place the module folder in REDCap's `modules/` directory
2. Enable at system level from Control Center > External Modules
3. Configure system-level settings (optional: text capacity, default reasons)
4. Enable and configure per-project as needed

### Version Upgrade Procedure

When upgrading to a new version:
1. **Disable** the module at the system level
2. Replace module files with new version
3. **Re-enable** the module at the system level

This ensures the core file modifications are properly removed and reapplied.

## Limitations and Known Issues

1. **Core File Modification** - The module directly modifies `DataEntry/index.php`, which may be overwritten during REDCap upgrades
2. **Field-Level Tracking** - The current implementation tracks changes at form level, not individual field level
3. **Dirty Field Behavior** - Fields remain highlighted even if values are reverted to original (mirrors REDCap's native behavior)

## Future Enhancements

The following items from the Enhancement Plan (see [ENHANCEMENT_PLAN.md](ENHANCEMENT_PLAN.md)) remain for future implementation:

- **Replace Direct Core File Modification** (Plan 1.1) - Refactor to use JavaScript injection via `redcap_data_entry_form` hook instead of modifying `DataEntry/index.php`
- **Widen Compatibility Range** (Plan 2.1, partial) - Expand PHP max version beyond 8.2.29 and remove REDCap max version cap
- **Full JavaScript Externalization** (Plan 3.3, partial) - Migrate remaining inline JavaScript from PHP to external `js/` files
- **Sub-Settings for Reason Categories** (Plan 2.3) - Grouped/categorized reason options
- **Per-Field Change Reason** (Plan 4.1) - Separate reasons for each changed field
- **Audit Logging** (Plan 4.2) - Log change reasons for compliance
- **AJAX Actions** (Plan 4.3) - Dynamic reason retrieval and custom reason saving
- **Character Counter Display** (Plan 4.4) - Show remaining characters in reason text area
- **Update README Installation Section** (Plan 5.3) - Add migration notes and core file modification warnings

## File Structure

```
enhance_reason_for_change_v1.0.0/
├── EnhanceReasonForChangeModule.php    # Main module class
├── config.json                          # Module configuration
├── README.md                            # User documentation
├── DOCUMENTATION.md                     # This file
├── ENHANCEMENT_PLAN.md                  # Future development plans
├── CHANGELOG.md                         # Version history
├── js/
│   ├── reason-dropdown.js              # Dropdown functionality (for future use)
│   └── field-highlight.js              # Field highlighting (for future use)
├── css/
│   └── styles.css                      # Module styles (for future use)
└── automated_tests/
    ├── *.feature                        # Cucumber test scenarios
    ├── step_definitions/
    │   ├── external_module.js           # Module-specific steps
    │   └── noncore.js                   # Additional utilities
    ├── fixtures/
    │   ├── cdisc_files/                 # Test project XML files
    │   └── import_files/                # Test data CSV files
    └── urs/
        └── User Requirement Specification.spec
```

## Dependencies

- REDCap External Module Framework v14+
- PHP 8.0.27+
- REDCap 13.8.1+
