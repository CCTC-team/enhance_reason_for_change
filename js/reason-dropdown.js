/**
 * Enhance Reason for Change - Dropdown Module
 * Handles the injection and management of the reason for change dropdown
 */
(function() {
    'use strict';

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReasonDropdown);
    } else {
        initReasonDropdown();
    }

    function initReasonDropdown() {
        var config = window.EnhanceRFC || {};

        if (!config.dropdownEnabled) {
            return;
        }

        // Use MutationObserver to detect when change_reason_popup appears
        var observer = new MutationObserver(function(mutations, obs) {
            var popup = document.getElementById('change_reason_popup');
            if (popup && !document.getElementById('reasons-for-change-container')) {
                injectDropdown(popup, config);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Also check if popup already exists
        var existingPopup = document.getElementById('change_reason_popup');
        if (existingPopup && !document.getElementById('reasons-for-change-container')) {
            injectDropdown(existingPopup, config);
        }
    }

    function injectDropdown(popup, config) {
        var reasons = config.reasons || [];
        var systemReasons = config.systemReasons || [];
        var maxChars = config.maxChars || 200;

        // Combine system and project reasons
        var allReasons = systemReasons.concat(reasons);

        if (allReasons.length === 0) {
            return;
        }

        // Find the text area container
        var textarea = popup.querySelector('#change_reason');
        if (!textarea) {
            return;
        }

        // Update character limit if configured
        if (config.enlargeCapacity && maxChars > 200) {
            textarea.setAttribute('onblur', "charLimit('change_reason'," + maxChars + ");");
        }

        // Build dropdown options
        var optionList = '<option value="" disabled selected>select a standard reason</option>';
        allReasons.forEach(function(reason) {
            if (reason && reason.trim() !== '') {
                var escaped = escapeHtml(reason);
                optionList += '<option value="' + escaped + '">' + escaped + '</option>';
            }
        });

        // Create container
        var container = document.createElement('div');
        container.id = 'reasons-for-change-container';
        container.innerHTML =
            '<p><small>Choose a standard reason or enter free text. Changing the selection will overwrite existing content.</small></p>' +
            '<select id="reason-for-change-opt" name="reason-for-change-opt" class="x-form-text x-form-field" style="width:400px;margin-bottom:10px;">' +
            optionList +
            '</select>';

        // Find insertion point (after the "Reason for changes:" label)
        var reasonLabel = popup.querySelector('div[style*="font-weight:bold"]');
        if (reasonLabel && reasonLabel.nextSibling) {
            reasonLabel.parentNode.insertBefore(container, reasonLabel.nextSibling);
        } else {
            // Fallback: insert before textarea's parent div
            var textareaParent = textarea.closest('div');
            if (textareaParent) {
                textareaParent.parentNode.insertBefore(container, textareaParent);
            }
        }

        // Add event listener
        var select = document.getElementById('reason-for-change-opt');
        if (select) {
            select.addEventListener('change', function() {
                var changeReason = document.getElementById('change_reason');
                if (changeReason) {
                    changeReason.value = this.options[this.selectedIndex].text;
                    // Trigger char counter update
                    if (window.EnhanceRFC && window.EnhanceRFC.updateCharCounter) {
                        window.EnhanceRFC.updateCharCounter();
                    }
                }
            });
        }

        // Initialize character counter if enabled
        if (config.showCharCounter) {
            initCharCounter(textarea, maxChars);
        }
    }

    function initCharCounter(textarea, maxChars) {
        if (document.getElementById('enhance-rfc-char-counter')) {
            return;
        }

        var counter = document.createElement('div');
        counter.id = 'enhance-rfc-char-counter';
        counter.className = 'text-muted small';
        counter.style.cssText = 'margin-top:5px;font-size:12px;';

        textarea.parentNode.appendChild(counter);

        function updateCounter() {
            var remaining = maxChars - textarea.value.length;
            counter.textContent = remaining + ' characters remaining';
            counter.style.color = remaining < 50 ? 'red' : 'inherit';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();

        // Store reference for dropdown to update
        window.EnhanceRFC = window.EnhanceRFC || {};
        window.EnhanceRFC.updateCharCounter = updateCounter;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
