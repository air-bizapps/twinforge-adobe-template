<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Stores the checkout note on the quote before the shipping information is saved.
 *
 * Pattern: plug into the interface (the guest endpoint converts the masked id and calls this same
 * interface, so one plugin covers guests and customers); the original $cartId is untyped, so the
 * plugin keeps it untyped; the quote repository caches the active quote, so the data set here is
 * persisted by the original method's own save.
 */
class ShippingInformationManagementPlugin
{
    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @param ShippingInformationManagementInterface $subject
     * @param int|string $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return null keeps the original arguments
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $extension = $addressInformation->getShippingAddress()?->getExtensionAttributes();
        $note = $extension?->getAcmeReferenceNote();
        if ($note !== null) {
            $quote = $this->cartRepository->getActive((int)$cartId);
            $quote->setData('acme_reference_note', mb_substr(trim((string)$note), 0, 255));
        }
        return null;
    }
}
