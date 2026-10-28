/**
 * Reference module of the adobe-magento-toolbox skill.
 *
 * jQuery widget started by data-mage-init='{"acmeToggleDescription": {...}}' (alias in
 * requirejs-config.js). Options merge with the defaults; _create runs once per element.
 */
define(['jquery', 'jquery-ui-modules/widget'], function ($) {
    'use strict';

    $.widget('acme.acmeToggleDescription', {
        options: {
            target: '.acme-reference-items__description',
            hiddenClass: 'no-display'
        },

        /** @inheritdoc */
        _create: function () {
            this.element.find(this.options.target).addClass(this.options.hiddenClass);
            this._on({click: this._toggle});
        },

        /** Shows or hides the description of the clicked row. */
        _toggle: function () {
            this.element.find(this.options.target).toggleClass(this.options.hiddenClass);
        }
    });

    return $.acme.acmeToggleDescription;
});
