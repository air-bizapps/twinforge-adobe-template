<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Publishes to the topic declared in etc/communication.xml. The message type must match the
 * topic's request type (string here): publish ids, never whole objects.
 */
class ItemSavedPublisher
{
    public const TOPIC = 'acme.reference.item.saved';

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    /**
     * @param int $itemId
     * @return void
     */
    public function publish(int $itemId): void
    {
        $this->publisher->publish(self::TOPIC, (string)$itemId);
    }
}
