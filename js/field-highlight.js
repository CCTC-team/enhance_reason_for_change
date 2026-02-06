/**
 * Enhance Reason for Change - Field Highlight Module
 * Handles visual highlighting of modified fields
 */
(function() {
    'use strict';

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFieldHighlight);
    } else {
        initFieldHighlight();
    }

    function initFieldHighlight() {
        var config = window.EnhanceRFC || {};

        if (!config.highlightEnabled) {
            return;
        }

        var highlightStyle = config.highlightStyle || 'solid 2px red';
        var fields = config.fields || {};

        // Process each field
        Object.keys(fields).forEach(function(fieldName) {
            var fieldInfo = fields[fieldName];
            addEventListenerForField(
                fieldName,
                fieldInfo.validationType,
                fieldInfo.originalValue,
                highlightStyle
            );
        });

        // Handle (M) buttons for missing data codes
        $(document).on('click', '.set_btn', function(e) {
            triggerEventFromSource(e.target, 'label', highlightStyle);
        });

        // Handle today/now buttons for date fields
        $(document).on('click', '.today-now-btn', function(e) {
            triggerEventFromSource(e.target, 'tr', highlightStyle);
        });

        // Handle datetime picker selections
        window.addEventListener('load', function() {
            $(document).on('click', '.ui-datepicker-trigger', function(e) {
                var target = e.target;
                $(document).on('click', '#ui-datepicker-div .ui-state-default', function() {
                    triggerEventFromSource(target, 'tr', highlightStyle);
                });
            });
        });
    }

    function handleUpdate(element, highlightStyle) {
        if (element) {
            element.style.borderRight = highlightStyle;
        }
    }

    function addEventListenerForField(fieldName, validationType, originalValue, highlightStyle) {
        var fieldTR = document.getElementById(fieldName + '-tr');

        if (!fieldTR) {
            return;
        }

        if (validationType === 'autocomplete') {
            var dropDownElement = document.querySelector('#rc-ac-input_' + fieldName);
            if (dropDownElement) {
                dropDownElement.addEventListener('blur', function() {
                    if (dropDownElement.value !== originalValue) {
                        handleUpdate(fieldTR, highlightStyle);
                    }
                });
            }
        } else {
            if (!fieldTR.classList.contains('@READONLY') && !fieldTR.classList.contains('@HIDDEN')) {
                fieldTR.addEventListener('change', function() {
                    handleUpdate(fieldTR, highlightStyle);
                });
            }
        }
    }

    function getFieldNameFromSourceButton(element, ancestorType) {
        var ancestor = element.closest(ancestorType);

        if (!ancestor) {
            return null;
        }

        if (ancestorType === 'label') {
            // Label id format: label-fieldname
            return ancestor.id.substring(6);
        }

        if (ancestorType === 'tr') {
            // TR id format: fieldname-tr
            return ancestor.id.slice(0, -3);
        }

        return null;
    }

    function triggerEventFromSource(sourceElement, ancestorType, highlightStyle) {
        var fieldName = getFieldNameFromSourceButton(sourceElement, ancestorType);

        if (!fieldName) {
            return;
        }

        var event = new Event('change', {
            bubbles: true,
            cancelable: true
        });

        var target = document.querySelector('[name="' + fieldName + '"]');
        if (target) {
            target.dispatchEvent(event);
        }

        // Also directly highlight the field
        var fieldTR = document.getElementById(fieldName + '-tr');
        if (fieldTR) {
            handleUpdate(fieldTR, highlightStyle);
        }
    }
})();
