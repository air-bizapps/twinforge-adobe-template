/**
 * Reference module of the adobe-magento-toolbox skill.
 *
 * Mixin of Magento_Checkout/js/action/set-shipping-information (requirejs-config.js > mixins).
 * The checkout field (LayoutProcessorPlugin) stores its value in custom_attributes; the API reads
 * extension_attributes, so the value is moved before the original action runs. A mixin receives
 * the original module and returns the wrapped one; it never copies the original's code.
 */
define(['mage/utils/wrapper', 'Magento_Checkout/js/model/quote'], function (wrapper, quote) {
    'use strict';

    /**
     * custom_attributes may arrive as an object or as an array of {attribute_code, value}.
     *
     * @param {Object|Array} attributes
     * @returns {String|null}
     */
    function findNote(attributes) {
        var found = null;

        if (!attributes) {
            return null;
        }
        if (Array.isArray(attributes)) {
            attributes.forEach(function (attribute) {
                if (attribute && attribute['attribute_code'] === 'acme_reference_note') {
                    found = attribute.value;
                }
            });

            return found;
        }

        return attributes['acme_reference_note'] || null;
    }

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            var shippingAddress = quote.shippingAddress(),
                note;

            if (shippingAddress) {
                note = findNote(shippingAddress.customAttributes);
                shippingAddress['extension_attributes'] = shippingAddress['extension_attributes'] || {};
                shippingAddress['extension_attributes']['acme_reference_note'] = note;
            }

            return originalAction();
        });
    };
});
