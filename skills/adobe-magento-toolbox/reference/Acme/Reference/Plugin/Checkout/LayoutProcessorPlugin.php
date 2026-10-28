<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Plugin\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessor;

/**
 * Adds a field to the checkout shipping address form (jsLayout), declared in etc/frontend/di.xml.
 *
 * The value travels with the shipping information: the JS mixin
 * view/frontend/web/js/action/set-shipping-information-mixin.js moves it from custom_attributes
 * to extension_attributes, ShippingInformationManagementPlugin stores it on the quote, and
 * etc/fieldset.xml copies it to the order.
 *
 * Pattern: check that the path exists (virtual carts and one-step checkouts change the tree);
 * template and elementTmpl are real Magento_Ui templates, never invented names (C3).
 */
class LayoutProcessorPlugin
{
    /**
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess(LayoutProcessor $subject, array $jsLayout): array
    {
        $steps = $jsLayout['components']['checkout']['children']['steps']['children'] ?? [];
        if (!isset($steps['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset'])) {
            return $jsLayout;
        }
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['shipping-address-fieldset']['children']['acme_reference_note'] = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'provider' => 'checkoutProvider',
            'dataScope' => 'shippingAddress.custom_attributes.acme_reference_note',
            'label' => __('Delivery note'),
            'sortOrder' => 250,
            'validation' => ['max_text_length' => 255],
            'visible' => true,
        ];
        return $jsLayout;
    }
}
