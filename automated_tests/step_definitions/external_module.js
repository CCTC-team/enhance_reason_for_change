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
    cy.get('#questiontable').find('tr').contains(label).parents('tr').should('not.have.attr', 'style', 'border-right')
})