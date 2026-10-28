/**
 * Reference module of the adobe-magento-toolbox skill.
 *
 * Knockout UI component, created by Magento_Ui/js/core/app (x-magento-init "*") and bound in the
 * template with data-bind="scope: 'acmeReferenceCounter'". template "Acme_Reference/item-counter"
 * resolves to view/frontend/web/template/item-counter.html (a real file, never an invented id).
 */
define(['uiComponent'], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Acme_Reference/item-counter',
            total: 0
        }
    });
});
