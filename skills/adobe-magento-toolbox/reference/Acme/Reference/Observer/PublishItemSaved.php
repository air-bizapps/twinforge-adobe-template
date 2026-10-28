<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Observer;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Model\Config;
use Acme\Reference\Model\Queue\ItemSavedPublisher;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Observer of this module's own event: decides fast and hands the slow part (e-mail) to the queue.
 * The event data key ("item") is the model's $_eventObject.
 */
class PublishItemSaved implements ObserverInterface
{
    /**
     * @param Config $config
     * @param ItemSavedPublisher $publisher
     */
    public function __construct(
        private readonly Config $config,
        private readonly ItemSavedPublisher $publisher
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getData('item');
        if (!$item instanceof ItemInterface || !$item->getId()) {
            return;
        }
        if ($this->config->isNotificationEnabled($item->getStoreId())) {
            $this->publisher->publish((int)$item->getId());
        }
    }
}
