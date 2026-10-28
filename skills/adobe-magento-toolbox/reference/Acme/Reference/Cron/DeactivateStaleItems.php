<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Cron;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Acme\Reference\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Cron job (etc/crontab.xml). Runs without a request, store or session: everything it needs comes
 * from config and the repository. Work in pages, never load the whole table; one failing item is
 * logged and does not stop the others.
 */
class DeactivateStaleItems
{
    private const PAGE_SIZE = 200;

    /**
     * @param Config $config
     * @param ItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        $limit = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime(sprintf('-%d days', $this->config->getCleanupDays())));
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(ItemInterface::STATUS, ItemInterface::STATUS_ENABLED)
            ->addFilter(ItemInterface::UPDATED_AT, $limit, 'lt')
            ->setPageSize(self::PAGE_SIZE)
            ->setCurrentPage(1)
            ->create();

        $deactivated = 0;
        foreach ($this->itemRepository->getList($criteria)->getItems() as $item) {
            try {
                $item->setStatus(ItemInterface::STATUS_DISABLED);
                $this->itemRepository->save($item);
                $deactivated++;
            } catch (\Exception $e) {
                $this->logger->error('Could not deactivate item', ['item_id' => $item->getId(), 'error' => $e->getMessage()]);
            }
        }
        if ($deactivated > 0) {
            $this->logger->info('Stale items deactivated', ['count' => $deactivated]);
        }
    }
}
