/**
 * Reference module of the adobe-magento-toolbox skill.
 *
 * - map: a short alias (used by data-mage-init in items.phtml) to a module path. Every target must
 *   exist as view/<area>/web/<path>.js; Acme_Reference/js/x resolves by itself, no map needed.
 * - mixins: wraps a core JS module without replacing it (the checkout note travels to the API).
 */
var config = {
    map: {
        '*': {
            acmeToggleDescription: 'Acme_Reference/js/toggle-description'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Acme_Reference/js/action/set-shipping-information-mixin': true
            }
        }
    }
};
