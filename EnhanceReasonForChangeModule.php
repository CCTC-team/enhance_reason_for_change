<?php

namespace CCTC\EnhanceReasonForChangeModule;

use ExternalModules\AbstractExternalModule;
use REDCap;

/**
 * Enhance Reason for Change External Module
 *
 * Provides enhanced functionality for REDCap's built-in "Require a reason when making changes" feature:
 * - Extended text capacity (200 to 5000 characters)
 * - Standardized dropdown reasons (system and project level)
 * - Visual field highlighting for modified fields
 */
class EnhanceReasonForChangeModule extends AbstractExternalModule
{
    /**
     * Gets the path to REDCap DataEntry index file
     * @return string
     */
    private function getFilePath(): string
    {
        return APP_PATH_DOCROOT . "/DataEntry/index.php";
    }

    /** @var string Original character limit code in REDCap source */
    private const OriginalCharLimitCode = "id=\"change_reason\" onblur=\"charLimit('change_reason',200);\"";

    /** @var string Replaced character limit code with extended capacity */
    private const ReplacedCharLimitCode = "id=\"change_reason\" onblur=\"charLimit('change_reason',5000);\"";

    /** @var int Default character limit */
    private const DEFAULT_CHAR_LIMIT = 200;

    /** @var int Extended character limit */
    private const EXTENDED_CHAR_LIMIT = 5000;

    /** @var string Default highlight style */
    private const DEFAULT_HIGHLIGHT_STYLE = 'solid 2px red';

    /**
     * Search term for locating the change reason popup in REDCap source.
     * Uses Nowdoc to preserve format and prevent tabs being replaced by spaces.
     */
    private const DropdownSearchTerm = <<<'EOT'
		<!-- Change reason pop-up-->
		<div id="change_reason_popup" title="data_entry_603" style="display:none;margin-bottom:25px;">
			<p>
				<?=RCView::tt("data_entry_68") // You must now supply ... ?>
			</p>
			<div style="font-weight:bold;padding:5px 0;">
				<?=RCView::tt("data_entry_69") // Reason for changes: ?>
			</div>'
EOT;

    /**
     * Replaces character limit code in REDCap source file
     *
     * Validates that exactly one instance of the search string exists before replacement
     * to prevent unintended modifications. Logs an error if the expected code is not found.
     *
     * @param string $from The code string to find and replace
     * @param string $to The replacement code string
     * @return void
     */
    private function replaceCharLimit(string $from, string $to): void
    {
        if (!file_exists($this->getFilePath())) {
            $this->log('File not found', ['path' => $this->getFilePath()]);
            return;
        }

        $file_contents = file_get_contents($this->getFilePath());
        if ($file_contents === false) {
            $this->log('Failed to read file', ['path' => $this->getFilePath(), 'error' => error_get_last()]);
            return;
        }

        $countOfFromCode = substr_count($file_contents, $from);
        $countOfToCode = substr_count($file_contents, $to);

        if ($countOfFromCode === 1) {
            $modified_contents = str_replace($from, $to, $file_contents);
            $result = file_put_contents($this->getFilePath(), $modified_contents);
            if ($result === false) {
                $this->log('Failed to write file', ['path' => $this->getFilePath(), 'error' => error_get_last()]);
            }
        } elseif ($countOfToCode === 1) {
            return; // Already replaced, nothing to do
        } else {
            $this->log('Failed to replace character limit code', [
                'search_string' => $from,
                'found_count' => $countOfFromCode,
                'expected_count' => 1
            ]);
        }
    }

    /**
     * Applies or reverts the text capacity change based on setting
     *
     * @param bool $updateCapacity True to extend capacity, false to revert to original
     * @return void
     */
    public function applyTextCapacityChange(bool $updateCapacity): void
    {
        if ($updateCapacity) {
            $this->replaceCharLimit(self::OriginalCharLimitCode, self::ReplacedCharLimitCode);
        } else {
            $this->replaceCharLimit(self::ReplacedCharLimitCode, self::OriginalCharLimitCode);
        }
    }

    /**
     * Hook: Called when module is enabled at system level
     *
     * Updates the REDCap DataEntry/index.php file to include:
     * - Extended character capacity (if enabled)
     * - Dropdown code with system-level reason options
     *
     * @param string $version Module version being enabled
     * @return void
     */
    public function redcap_module_system_enable(string $version): void
    {
        $this->log('Module enabled at system level', ['version' => $version]);

        // Change the char capacity - always set here as applies across all projects
        $textCapacityEnabled = (bool)$this->getSystemSetting("enlarge-reason-text-capacity");
        $this->applyTextCapacityChange($textCapacityEnabled);

        // Reason for change dropdown with system-level options
        $reasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option") ?? [];
        $this->applyDefaultReasonForChangeDropdown($reasonForChangeOptions, true);
    }

    /**
     * Hook: Called when module is disabled at system level
     *
     * Reinstates the original REDCap DataEntry/index.php by:
     * - Reverting character capacity to original 200 characters
     * - Removing the inserted dropdown code
     *
     * @param string $version Module version being disabled
     * @return void
     */
    public function redcap_module_system_disable(string $version): void
    {
        $this->log('Module disabled at system level', ['version' => $version]);

        // Revert character capacity to original value
        $this->replaceCharLimit(self::ReplacedCharLimitCode, self::OriginalCharLimitCode);

        // Remove the added dropdown code
        $reasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option") ?? [];
        $insertedCode = $this->makeInsertCode($reasonForChangeOptions);
        $this->removeCode($insertedCode);
    }

    /**
     * Hook: Validates settings when saved
     *
     * Validates configuration and applies changes to the REDCap source file
     * when system settings are modified.
     *
     * @param array $settings The settings being saved
     * @return string|null Error message or null if valid
     */
    public function validateSettings($settings)
    {
        // For project settings, ensure at least one feature is enabled
        if (array_key_exists("provide-reasons-for-change-dropdown", $settings) &&
            array_key_exists("highlight-field-when-changed", $settings)) {
            if (empty($settings['provide-reasons-for-change-dropdown']) &&
                empty($settings['highlight-field-when-changed'])) {
                return "Please ensure at least one Enhance Reason For Change External Module setting is configured.";
            }
        }

        // Handle system settings
        if (array_key_exists("enlarge-reason-text-capacity", $settings)) {
            $this->applyTextCapacityChange((bool)$settings['enlarge-reason-text-capacity']);
            $reasonForChangeOptions = $settings["sys-reason-for-change-option"] ?? [];
            $this->applyDefaultReasonForChangeDropdown($reasonForChangeOptions, false);
        }

        return null;
    }


    /**
     * Inserts code into the REDCap source file after a multi-line search term
     *
     * Searches for consecutive lines matching the search term and inserts
     * the new code after the last matched line.
     *
     * @param string $searchTerm Multi-line search term to find
     * @param string $insertCode The code to insert
     * @return void
     */
    private function insertCode(string $searchTerm, string $insertCode): void
    {
        if (!file_exists($this->getFilePath())) {
            $this->log('File not found for code insertion', ['path' => $this->getFilePath()]);
            return;
        }

        $file_contents = file($this->getFilePath());
        if ($file_contents === false) {
            $this->log('Failed to read file for code insertion', ['path' => $this->getFilePath()]);
            return;
        }

        $found = false;
        $searchArray = explode("\n", $searchTerm);
        $matched = 0;

        foreach ($file_contents as $index => $line) {
            // Increment $matched so checks next line on next iteration
            if (str_contains($line, $searchArray[$matched])) {
                $matched++;
            }

            // If all the lines were found then mark as found
            if ($matched === count($searchArray) - 1) {
                array_splice($file_contents, $index + 1, 0, $insertCode);
                $found = true;
                break;
            }
        }

        if ($found) {
            $result = file_put_contents($this->getFilePath(), implode('', $file_contents));
            if ($result === false) {
                $this->log('Failed to write file after code insertion', ['path' => $this->getFilePath()]);
            }
        } else {
            $this->log('Failed to find search term for inserting code');
        }
    }

    /**
     * Removes previously inserted code from the REDCap source file
     *
     * @param string $removeCode The code block to remove
     * @return void
     */
    private function removeCode(string $removeCode): void
    {
        if (!file_exists($this->getFilePath())) {
            $this->log('File not found for code removal', ['path' => $this->getFilePath()]);
            return;
        }

        $file_contents = file_get_contents($this->getFilePath());
        if ($file_contents === false) {
            $this->log('Failed to read file for code removal', ['path' => $this->getFilePath()]);
            return;
        }

        if (str_contains($file_contents, $removeCode)) {
            $modified_contents = str_replace($removeCode, "", $file_contents);
            $result = file_put_contents($this->getFilePath(), $modified_contents);
            if ($result === false) {
                $this->log('Failed to write file after code removal', ['path' => $this->getFilePath()]);
            }
        } else {
            $this->log('Failed to find code to remove');
        }
    }

    /**
     * Creates the dropdown HTML/JavaScript code from the provided options
     *
     * Generates a select dropdown with the given options and JavaScript
     * to update the change reason textarea when a selection is made.
     *
     * @param array|null $reasonForChangeOptions Array of reason option strings
     * @return string The generated HTML/JavaScript code block
     */
    private function makeInsertCode(?array $reasonForChangeOptions): string
    {
        $optionList = "<option value='' disabled selected>select a standard reason</option>";

        if (is_array($reasonForChangeOptions)) {
            foreach ($reasonForChangeOptions as $option) {
                if ($option !== null && $option !== "") {
                    $escapedOption = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                    $optionList .= "<option value=\"{$escapedOption}\">{$escapedOption}</option>";
                }
            }
        }

        $selectControl =
            "<select id='reason-for-change-opt' name='reason-for-change-opt' class='x-form-text x-form-field'>
        {$optionList}
        </select>";

        $jsToUpdateChangeReason = "
<script type='text/javascript'>
    let sel = document.getElementById('reason-for-change-opt');
    sel.addEventListener('change', function(e) {
        let changeReason = document.getElementById('change_reason');
        changeReason.value = sel.options[sel.selectedIndex].text;
    });
</script>";

        return
            "<!-- ****** inserted by Enhance Reasons for Change module ****** -->
<div id='reasons-for-change-container' style='display:none;'>
<p><small>Choose a standard reason or enter free text. Changing the selection will overwrite existing content.</small></p>
$selectControl
$jsToUpdateChangeReason
</div>
<!-- ****** end of insert ****** -->" . PHP_EOL;
    }

    /**
     * Applies the default reason for change dropdown to the REDCap source file
     *
     * Removes any existing dropdown code and inserts new code with the provided options.
     *
     * @param array|null $reasonForChangeOptions Array of reason option strings
     * @param bool $empty Whether this is an initial insertion (true) or update (false)
     * @return void
     */
    private function applyDefaultReasonForChangeDropdown(?array $reasonForChangeOptions, bool $empty): void
    {
        // Get from db and build existing code
        $oldReasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option") ?? [];
        $oldCode = $this->makeInsertCode($oldReasonForChangeOptions);

        // Remove first if not empty (updating existing code)
        if (!$empty) {
            $this->removeCode($oldCode);
        }

        // Build new code and apply from new settings
        $newCode = $this->makeInsertCode($reasonForChangeOptions);
        $this->insertCode(self::DropdownSearchTerm, $newCode);
    }

    /**
     * Outputs JavaScript for field highlighting functionality
     *
     * Generates JavaScript functions for detecting field changes and applying
     * visual highlighting to modified fields.
     *
     * @param string $highlightStyle CSS style for the highlight border
     * @return void
     */
    private function outputHighlightJs(string $highlightStyle): void
    {
        // Sanitize the style to prevent XSS - only allow safe CSS characters
        $safeStyle = preg_replace('/[^a-zA-Z0-9\s\-#.,()%]/', '', $highlightStyle);
        $escapedStyle = htmlspecialchars($safeStyle, ENT_QUOTES, 'UTF-8');

        echo "
            <script type='text/javascript'>

                function HandleUpdate(ele) {
                    ele.style.borderRight = '{$escapedStyle}';
                }
                                
                // element validation type is used to determine if the field is autocomplete or not
                // if it is, need to listen to the blur event not change event 
                function AddEventListener(qField, elementValidationType, fieldVal) {                    
                    let fieldTR = document.getElementById(qField + '-tr');                   
                    
                    if(elementValidationType === 'autocomplete') {                                                
                        const dropDownElement = document.querySelector('#rc-ac-input_' + qField);                                                                         
                        if(dropDownElement) {                              
                              dropDownElement.addEventListener('blur', function(e) {
                                //only trigger the ui update if the current value is changed from original fieldVal
                                if(dropDownElement.value !== fieldVal) {
                                   HandleUpdate(fieldTR);
                                }                                
                              })
                        }                       
                    } else {                        
                        
                        if(fieldTR && 
                            !(fieldTR.classList.contains('@READONLY') || fieldTR.classList.contains('@HIDDEN'))) {
                                                                                                    
                            fieldTR.addEventListener('change', function(e) {
                                HandleUpdate(fieldTR);
                            });                                                    
                        }                                            
                    }                    
                }
                
                // function to find nearest label whose id gives the field name
                function getFieldNameFromSourceButton(div, ancestorEle) {                    
                    let ele = div.closest(ancestorEle);
                                        
                    if (ele) {
                        //the label id is in format label-somefield so remove leading label- part
                        if(ancestorEle === 'label') {                            
                            return ele.id.substring(6);
                        }
                        //the tr id is in format somefield-tr so remove trailing -tr part
                        if(ancestorEle === 'tr') {
                            return ele.id.slice(0, -3);
                        }
                    }
                    
                    return null;
                }

                // for the given field, manually triggers the change event when the (M) button is used
                function triggerEvent(sourceDiv, ancestorEle) {                    
                    let fieldName = getFieldNameFromSourceButton(sourceDiv, ancestorEle);
                    
                    let event = new Event('change', { 
                        bubbles: true, 
                        cancelable: true,
                    });
                    
                    let tar = document.querySelector('[name=\"' + fieldName + '\"]');
                    if(tar) {                        
                        tar.dispatchEvent(event);
                    }
                }
                
            </script>";
    }

    /**
     * Adds project-specific reasons for change to the dropdown
     *
     * Injects JavaScript that appends project-level options to the system-level
     * dropdown options at runtime.
     *
     * @return void
     */
    private function addProjectSpecificReasonsForChange(): void
    {
        $projReasons = $this->getProjectSetting("reason-for-change-option");

        if (!is_array($projReasons) || count($projReasons) === 0) {
            return;
        }

        // Filter out empty values and use json_encode for safe JS output
        $filteredReasons = array_filter($projReasons, function ($item) {
            return $item !== null && $item !== "";
        });

        if (count($filteredReasons) === 0) {
            return;
        }

        $reasonForChangeOptionsJson = json_encode(
            array_values($filteredReasons),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        echo "<script type='text/javascript'>
const reasonsForChange = {$reasonForChangeOptionsJson};
let reasonForChangeOpt = document.getElementById('reason-for-change-opt');
if(reasonForChangeOpt) {
    document.addEventListener('click', function() {
        reasonsForChange.forEach(opt => {
            const isOptionPresent = Array.from(reasonForChangeOpt.options).some(option => option.value === opt);
            if(!isOptionPresent) {
                let option = document.createElement('option');
                option.value = opt;
                option.text = opt;
                reasonForChangeOpt.appendChild(option);
            }
        });
    })
}
</script>";
    }

    /**
     * Handles project-level settings for the reason for change dropdown
     *
     * Shows the dropdown container and adds project-specific reasons if configured.
     *
     * @return void
     */
    private function handleProjectSettings(): void
    {
        if (!$this->getProjectSetting("provide-reasons-for-change-dropdown")) {
            return;
        }

        $this->addProjectSpecificReasonsForChange();

        // Show the markup (by default it is hidden)
        echo "<script type='text/javascript'>
let changeDialog = document.getElementById('change_reason_popup');
if(changeDialog) {
    let reasonForChangeContainer = document.getElementById('reasons-for-change-container');
    if(reasonForChangeContainer) {
        reasonForChangeContainer.style.display = 'block';
    }
}
</script>";
    }


    /**
     * Hook: Called when a data entry form is displayed
     *
     * Injects JavaScript for field highlighting and project-specific dropdown options.
     *
     * @param int|string $project_id The project ID
     * @param string|null $record The record name
     * @param string $instrument The instrument/form name
     * @param int $event_id The event ID
     * @param int|null $group_id The DAG group ID
     * @param int $repeat_instance The repeat instance number
     * @return void
     */
    public function redcap_data_entry_form(
        $project_id,
        ?string $record,
        string $instrument,
        int $event_id,
        ?int $group_id,
        int $repeat_instance
    ): void {
        global $Proj;

        if (empty($project_id)) {
            return;
        }

        $shouldHighlight = (bool)$this->getProjectSetting("highlight-field-when-changed");
        $provideReasonsForChange = (bool)$this->getProjectSetting("provide-reasons-for-change-dropdown");
        $projReasons = $this->getProjectSetting("reason-for-change-option") ?? [];
        $reasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option") ?? [];

        if (!$shouldHighlight && !$provideReasonsForChange) {
            $this->showAlert('Please ensure at least one Enhance Reason For Change External Module setting is configured.');
            return;
        }

        if ($provideReasonsForChange) {
            if (($Proj->project['require_change_reason'] ?? 0) != 1) {
                $this->showAlert('Please ensure require a reason for change is enabled in Additional Customizations in Project Setup.');
                return;
            }
        }

        // Check if system reasons for change options are empty
        $sysReasonForChangeOptionsEmpty = !$this->hasNonEmptyValues($reasonForChangeOptions);

        // Check if project reasons for change options are empty
        $proReasonForChangeOptionsEmpty = !$this->hasNonEmptyValues($projReasons);

        // If Reason for change is enabled and no options are configured
        if ($provideReasonsForChange && $proReasonForChangeOptionsEmpty && $sysReasonForChangeOptionsEmpty) {
            $this->showAlert('Please configure the Reason for Change options.');
            return;
        }

        if ($shouldHighlight) {
            $style = $this->getProjectSetting("highlight-field-when-changed-style");
            $highlightStyle = $style ?? self::DEFAULT_HIGHLIGHT_STYLE;

            // Provide JS functions for field highlighting
            $this->outputHighlightJs($highlightStyle);

            //need to create a different selector depending on the element type
            //i.e. a standard text question (or int etc.) just uses a text box with name attribute and can use change
            //however, for a data time or multi checkbox is different selector and process

            $projFields = array_keys($Proj->metadata);
            $formFields = REDCap::getFieldNames($instrument);

            //gets the fields and element_validation_type and enum for the current form
            $items = [];
            foreach (array_intersect($formFields, $projFields) as $formField) {
                $items[$formField] = [
                    "element_validation_type" => $Proj->metadata[$formField]["element_validation_type"],
                    "element_enum" => $Proj->metadata[$formField]["element_enum"],
                ];
            }

            //get the data for this form
            $params =
                array(
                    'project_id' => $project_id,
                    'records' => array($record),
                    'fields' => array_keys($items),
                    'events' => array($event_id)
                );

            $data = REDCap::getData($params);

            $isRepeatingForm = !empty($data[$record]['repeat_instances']);

            if(!$isRepeatingForm) {
                $thisFormData = $data[$record][$event_id];
            } else {
                //the structure of the array depends on project settings and existing instances of the form
                if(!empty($data[$record]['repeat_instances'][$event_id])){
                    if(!empty($data[$record]['repeat_instances'][$event_id][$instrument])){
                        if(!empty($data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance])) {
                            $thisFormData = $data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance];
                        } else {
                            //set the default new value that is given when new forms shown i.e. 0 for Incomplete
                            $thisFormData["{$instrument}_complete"] = 0;
                        }
                    } else {
                        //the form name is not always given when only one form
                        $thisFormData = $data[$record]['repeat_instances'][$event_id][''][$repeat_instance];
                    }
                } else {
                    $thisFormData = $data[$record][''][$event_id][$instrument][$repeat_instance];
                }
            }

            //adds the event listener for every field
            echo "<script type='text/javascript'>";

            //add simple event listeners for user interaction
            foreach ($items as $field=>$arr) {
                $valType = $arr["element_validation_type"];
                $fieldEnum = $arr["element_enum"];

                $fieldValue = $thisFormData[$field];

                //if the field is an autocomplete, need to extract the option label from the enum using
                //the value which is the key
                if($valType == "autocomplete") {
                    $associativeArray = [];
                    $parts = array_map(function($option) { return trim($option); }, explode('\n', $fieldEnum));
                    foreach ($parts as $item) {
                        list($key, $value) = explode(", ", $item, 2);
                        $associativeArray[$key] = $value;
                    }
                    $fieldValue = $associativeArray[$fieldValue];
                }

                echo "AddEventListener('$field', '$valType', '$fieldValue');\n";
            }

            //adds the click event to the buttons available via the (M) button so that when a user interacts
            //with a field using this mechanism, the change is still captured and the field highlighted
            echo "$('.set_btn').on('click', function (e) {            
                triggerEvent(e.target, 'label');            
            });\n";

            //handle dates - they can be updated directly by entering a value directly in the text box
            //which is already handled

            //or indirectly by using the 'today' or 'now' button - both have a class of 'today-now-btn'
            echo "$('.today-now-btn').on('click', function (e) {                  
                triggerEvent(e.target, 'tr');            
            });";

            //or by selecting any of the values in the datetime popup
            //note, the targeted trigger is only available once the page has fully loaded
            echo "
                window.onload = function() {
                    $('.ui-datepicker-trigger').on('click', function (e) {                    
                        $('#ui-datepicker-div .ui-state-default').on('click', function (f) {
                            //trigger the event from the outer target i.e. the original ui-datepicker-trigger
                            triggerEvent(e.target, 'tr');
                        })
                    })                                           
                }            
            ;";

            echo "</script>";
        }

        // Handle project settings for dropdown visibility and project-specific options
        $this->handleProjectSettings();
    }

    /**
     * Checks if an array has non-empty values
     *
     * @param array|null $values The array to check
     * @return bool True if has non-empty values
     */
    private function hasNonEmptyValues(?array $values): bool
    {
        if (!is_array($values) || count($values) === 0) {
            return false;
        }

        foreach ($values as $value) {
            if (!empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shows a JavaScript alert message
     *
     * @param string $message The alert message
     * @return void
     */
    private function showAlert(string $message): void
    {
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<script type='text/javascript'>alert('{$escapedMessage}');</script>";
    }
}