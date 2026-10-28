<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Queue;

use Acme\Reference\Api\ItemRepositoryInterface;
use Acme\Reference\Model\Email\ItemNotificationSender;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Consumer (handler in etc/queue_consumer.xml): the argument type is the topic's request type.
 *
 * Pattern: reload the entity by id (it may have changed or been deleted since publishing);
 * a permanent failure is logged and swallowed, otherwise the message is retried forever.
 */
class ItemSavedConsumer
{
    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemNotificationSender $sender
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemNotificationSender $sender,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $itemId
     * @return void
     */
    public function process(string $itemId): void
    {
        try {
            $this->sender->send($this->itemRepository->getById((int)$itemId));
        } catch (NoSuchEntityException $e) {
            $this->logger->info('Item removed before the notification was sent', ['item_id' => $itemId]);
        } catch (\Throwable $e) {
            $this->logger->error('Item notification failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }
    }
}
