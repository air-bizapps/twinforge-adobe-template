<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer of a core event (sales_order_place_after). Runs inside the checkout request: never call
 * external services or send e-mail here; log, flag or publish to a queue. Never throw: an exception
 * here breaks the customer's order placement.
 */
class OrderPlacedObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger the module's own log file (virtualType in etc/di.xml)
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof OrderInterface) {
            return;
        }
        $note = method_exists($order, 'getData') ? $order->getData('acme_reference_note') : null;
        if ($note !== null && $note !== '') {
            $this->logger->info('Order placed with delivery note', [
                'order' => $order->getIncrementId(),
                'note_length' => strlen((string)$note),
            ]);
        }
    }
}
