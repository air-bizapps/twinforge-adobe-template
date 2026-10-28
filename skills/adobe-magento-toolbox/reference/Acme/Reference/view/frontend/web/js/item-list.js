/**
 * Reference module of the adobe-magento-toolbox skill.
 *
 * Component started by x-magento-init: {"#selector": {"Acme_Reference/js/item-list": {config}}}.
 * The module returns a function(config, element), called once per element the selector matches.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        $(element).on('click', '.acme-reference-items__item', function () {
            $(this).toggleClass(config.highlightClass);
        });
    };
});
