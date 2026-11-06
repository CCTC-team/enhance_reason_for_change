//Add any of your own step definitions here
const { Given, defineParameterType } = require('@badeball/cypress-cucumber-preprocessor')


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see the field labeled {string} with a {int}px {string} right border in {string} color
 * @param {string} label - field label
 * @param {int} num - right border style in px
 * @param {string} lineType - right border style - solid/dashed
 * @param {string} color - color of right border
 * @description verify field has a right border of specified style
 */
Given('I should see the field labeled {string} with a {int}px {string} right border in {string} color', (label, num, lineType, color) => {
    cy.get('#questiontable').find('tr').contains(label).parents('tr').should('have.attr', 'style')
        .and('include', 'border-right: ' + num + 'px ' + lineType + ' ' + color)
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should NOT see the field labeled {string} with a colored right border
 * @param {string} label - field label
 * @description verify field does not have a right border
 */
Given('I should NOT see the field labeled {string} with a colored right border', (label) => {
    cy.get('#questiontable').find('tr').contains(label).parents('tr').should(($rows) => {
        $rows.each((index, row) => {
            expect(row.style.borderRightStyle).to.equal('')
        })
    })
})

/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the saved value should not have run as script
 * @description verify a saved value written as script did not run when the form loaded
 */
Given('the saved value should not have run as script', () => {
    cy.get('#questiontable').should('be.visible')
    cy.window().then((win) => {
        expect(win.erfcInjected).to.equal(undefined)
        expect(typeof win.AddEventListener).to.equal('function')
    })
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I change the text field labeled {string} to {string} without saving
 * @param {string} label - field label
 * @param {string} value - new value
 * @description changes a text field and moves focus away, which is what triggers the highlight
 */
Given('I change the text field labeled {string} to {string} without saving', (label, value) => {
    cy.get('#questiontable').find('tr').contains(label).parents('tr').find('input[type=text]').first().clear().type(value).blur()
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I change the autocomplete field {string} to {string} without saving
 * @param {string} field - field name
 * @param {string} label - option label
 * @description types an option label into an autocomplete field and moves focus away
 */
Given('I change the autocomplete field {string} to {string} without saving', (field, label) => {
    cy.get('#rc-ac-input_' + field).clear().type(label).blur()
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the text field labeled {string} should have the value {string}
 * @param {string} label - field label
 * @param {string} value - expected value
 * @description verify the saved value shown by a text field
 */
Given('the text field labeled {string} should have the value {string}', (label, value) => {
    cy.get('#questiontable').find('tr').contains(label).parents('tr').find('input[type=text]').first().should('have.value', value)
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I clear the autocomplete field {string} without saving
 * @param {string} field - field name
 * @description clears an autocomplete field and moves focus away
 */
Given('I clear the autocomplete field {string} without saving', (field) => {
    cy.get('#rc-ac-input_' + field).clear().blur()
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I set the missing data code {string} on the field {string}
 * @param {string} code - missing data code
 * @param {string} field - field name
 * @description sets a missing data code using the field's (M) button
 */
Given('I set the missing data code {string} on the field {string}', (code, field) => {
    cy.get('[name="missingDataButton"][fieldName="' + field + '"]').click()
    cy.get('#MDMenu .set_btn[code="' + code + '"]').click()
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example a system settings save by the current user should be refused and leave the source files unchanged
 * @description posts a system settings save directly, as the configuration dialog is not offered to this user, and compares the checksum of the core file the module edits
 */
Given('a system settings save by the current user should be refused and leave the source files unchanged', () => {
    const container = Cypress.env('mysql')?.docker_container || 'CCTC_REDCap_Docker'
    const coreFile = `/var/www/html/redcap_v${Cypress.env('redcap_version')}/DataEntry/index.php`
    const checksum = () => cy.exec(`docker exec ${container} md5sum ${coreFile}`).its('stdout')

    checksum().as('checksumBefore')
    cy.window().then((win) => {
        cy.request({
            method: 'POST',
            url: win.app_path_webroot + 'ExternalModules/manager/ajax/save-settings.php?moduleDirectoryPrefix=enhance_reason_for_change',
            form: true,
            failOnStatusCode: false,
            body: {
                redcap_csrf_token: win.redcap_csrf_token,
                settings: JSON.stringify({
                    'enlarge-reason-text-capacity': true,
                    'sys-reason-for-change-option____0': 'Not allowed'
                })
            }
        }).its('body').should('not.include', '"status":"success"')
    })
    cy.get('@checksumBefore').then((before) => {
        checksum().should('equal', before)
    })
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the autocomplete field {string} should show {string}
 * @param {string} field - field name
 * @param {string} label - option label
 * @description verify the label shown by an autocomplete field
 */
Given('the autocomplete field {string} should show {string}', (field, label) => {
    cy.get('#rc-ac-input_' + field).should('have.value', label)
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I leave the autocomplete field {string} unchanged
 * @param {string} field - field name
 * @description moves focus into and out of an autocomplete field without changing it
 */
Given('I leave the autocomplete field {string} unchanged', (field) => {
    cy.get('#rc-ac-input_' + field).focus().blur()
})


/**
 * @module EnhaceReasonForChange
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the field {string} should have no right border
 * @param {string} field - field name
 * @description verify a field's row has no highlight border
 */
Given('the field {string} should have no right border', (field) => {
    cy.get('#' + field + '-tr').should(($tr) => {
        expect($tr[0].style.borderRightStyle).to.equal('')
    })
})
