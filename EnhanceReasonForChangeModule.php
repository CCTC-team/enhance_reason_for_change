<?php

namespace CCTC\EnhanceReasonForChangeModule;

use ExternalModules\AbstractExternalModule;
use PHPSQLParser\Test\Parser\unionWithParenthesisTest;
use RCView;
use REDCap;

class EnhanceReasonForChangeModule extends AbstractExternalModule
{


    //<div><textarea id="change_reason" onblur="charLimit('change_reason',200);" class="x-form-textarea x-form-field" style="width:400px;height:120px;">Some reason</textarea></div>

    const FilePath = APP_PATH_DOCROOT . "/DataEntry/index.php";

    const OriginalCharLimitCode = "id=\"change_reason\" onblur=\"charLimit('change_reason',200);\"";
    const ReplacedCharLimitCode = "id=\"change_reason\" onblur=\"charLimit('change_reason',5000);\"";

    //uses Nowdoc to preserve format to prevent tabs being replaced by spaces
    const DropdownSearchTerm = <<<'EOT'
		<!-- Change reason pop-up-->
		<div id="change_reason_popup" title="data_entry_603" style="display:none;margin-bottom:25px;">
			<p>
				<?=RCView::tt("data_entry_68") // You must now supply ... ?>
			</p>
			<div style="font-weight:bold;padding:5px 0;">
				<?=RCView::tt("data_entry_69") // Reason for changes: ?>
			</div>'
EOT;

    //replaces the char limit checking first that the replacement won't affect other instances of similar code
    //a message is logged if the code to replace doesn't equal exactly one instance in the code file
    function replaceCharLimit($from, $to): void
    {
        $file_contents = file_get_contents(self::FilePath);

        $countOfFromCode = substr_count($file_contents, $from);
        if ($countOfFromCode == 1) {
            $modified_contents = str_replace($from, $to, $file_contents);
            file_put_contents(self::FilePath, $modified_contents);
        } else {
            $mess = "Failed to replace the character limit code. Trying to find: $from and got a count of: $countOfFromCode when expecting count of 1";
            $this->log($mess);
        }
    }

    function applyTextCapacityChange($updateCapacity): void
    {
        if ($updateCapacity) {
            $this->replaceCharLimit(self::OriginalCharLimitCode, self::ReplacedCharLimitCode);
        } else {
            $this->replaceCharLimit(self::ReplacedCharLimitCode, self::OriginalCharLimitCode);
        }
    }

    function redcap_module_system_enable($version): void
    {
        //this should simply update the code file to include the necessary changes
        //for changing the max chars and drop down

        //change the char capacity - always set here as applies across all projects
        $textCapacityEnabled = $this->getSystemSetting("enlarge-reason-text-capacity");
        $this->applyTextCapacityChange($textCapacityEnabled);

        //reason for change dropdown
        //set here with default-reason-for-change-option options as given in system settings
        //NOTE: these may be added to per project
        $reasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option");
        $this->applyDefaultReasonForChangeDropdown($reasonForChangeOptions);
    }

    function redcap_module_system_disable($version): void
    {
        //reinstates the original i.e. removes any edits to the code

        //change the char capacity back to original value
        $this->replaceCharLimit(self::ReplacedCharLimitCode, self::OriginalCharLimitCode);

        //remove the added code for the dropdown
        $reasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option");
        $insertedCode = $this->makeInsertCode($reasonForChangeOptions);
        $this->removeCode($insertedCode);
    }

    //this is a built-in external module framework method
    //this is used to validate settings when saved - can be used to change the file contents for char limit
    public function validateSettings($settings) :?string
    {
        if (array_key_exists("enlarge-reason-text-capacity", $settings)) {

            //NOTE: system settings

            //update the code depending on system setting
            $this->applyTextCapacityChange($settings);
            $reasonForChangeOptions = $settings["sys-reason-for-change-option"];
            $this->applyDefaultReasonForChangeDropdown($reasonForChangeOptions);
        }

        //no changes required for a project setting change

        //do not return an error here if the replace process fails as the user can never resolve that
        //without resorting to a code change
        return null;
    }


    //adds the $insertCode into the FilePath after the $searchTerm
    function insertCode($searchTerm, $insertCode): void
    {
        $file_contents = file(self::FilePath);
        $found = false;

        $searchArray = explode("\n", $searchTerm);
        $matched = 0;

        foreach ($file_contents as $index => $line) {
            //increment $matched so checks next line on next iteration
            if (str_contains($line, $searchArray[$matched])) {
                $matched++;
            }

            //if all the lines were found then mark as found
            if ($matched == count($searchArray) - 1) {
                array_splice($file_contents, $index + 1, 0, $insertCode);
                $found = true;
                break;
            }
        }

        //write it back if was found
        if ($found) {
            file_put_contents(self::FilePath, implode('', $file_contents));
        } else {
            $this->log('failed to find the search term for inserting the code');
        }
    }

    //removes the inserted code from the FilePath
    function removeCode($removeCode): void
    {
        $file_contents = file_get_contents(self::FilePath);
        if (str_contains($file_contents, $removeCode)) {
            $modified_contents = str_replace($removeCode, "", $file_contents);
            file_put_contents(self::FilePath, $modified_contents);
        }else {
            $this->log('failed to find code to remove');
        }
    }

    //create the dropdown code from the options provided
    function makeInsertCode($reasonForChangeOptions): string
    {
        $optionList = "<option value='' disabled selected>select a standard reason</option>";
        foreach ($reasonForChangeOptions as $option) {
            if ($option != null && $option != "") {
                $optionList .= "<option value='{$option}'>{$option}</option>";
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

    function applyDefaultReasonForChangeDropdown($reasonForChangeOptions): void
    {
        //use the db system settings to remove the existing code
        //then build the new code from given options

        //get from db and build existing code
        $oldReasonForChangeOptions = $this->getSystemSetting("sys-reason-for-change-option");
        $oldCode = $this->makeInsertCode($oldReasonForChangeOptions);

        //always remove first
        $this->removeCode($oldCode);

        //build new code and apply from new settings
        $newCode = $this->makeInsertCode($reasonForChangeOptions);
        $this->insertCode(self::DropdownSearchTerm, $newCode);
    }

    function js($highlightStyle): void
    {
        echo "
            <script type='text/javascript'>
                                
                function HandleUpdate(ele) {
                    ele.style.borderRight = '$highlightStyle';                              
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

    //gets the reasons for change at a project level and appends to the default ones
    function addProjectSpecificReasonsForChange(): void
    {
        //get the project reasons and append to system list
        $projReasons = $this->getProjectSetting("reason-for-change-option");
        if($projReasons && is_array($projReasons) && count($projReasons) > 0) {
            $quotedArray = array_map(function($item) {
                return "'" . $item . "'";
            }, $projReasons);
           $reasonForChangeOptions = implode(",", $quotedArray);

        echo "<script type='text/javascript'>
const reasonsForChange = [ $reasonForChangeOptions ]
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
    }

    function handleProjectSettings() : void
    {
        //check if the project uses the reasons for change drop down and show it if so
        if($this->getProjectSetting("provide-reasons-for-change-dropdown")) {
            self::addProjectSpecificReasonsForChange();

            //show the new markup if not selected to be shown for project as by default it is hidden
            echo "<script type='text/javascript'>
//the inner change div is always present so can be adjusted immediately without waiting for an event
let changeDialog = document.getElementById('change_reason_popup');
if(changeDialog) {        
    let reasonForChangeContainer = document.getElementById('reasons-for-change-container');
    //with the container of the added html, simply hide it as the dropdown option is not checked
    if(reasonForChangeContainer) {
        reasonForChangeContainer.style.display = 'block';
    }        
}
</script>";
        }
    }


    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance): void
    {
        global $Proj;

        if (empty($project_id)) return;

        //check if project uses reason for change and return if not
        if($Proj->project['require_change_reason'] != 1) return;

        //only implement this when highlighting fields
        $shouldHighlight = $this->getProjectSetting("highlight-field-when-changed");
        if($shouldHighlight) {
            $style = $this->getProjectSetting("highlight-field-when-changed-style");
            $highlightStyle = $style ?? 'solid 2px red';

            //provide js functions as needed
            self::js($highlightStyle);

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

        //check project settings and hide reason for change drop down if not checked or add project reasons if given
        self::handleProjectSettings();
    }

}















