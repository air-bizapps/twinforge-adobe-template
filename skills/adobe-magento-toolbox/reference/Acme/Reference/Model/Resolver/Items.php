<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Query acmeReferenceItems: a public, paged list of ENABLED items.
 *
 * Pattern: validate the arguments (GraphQlInputException = message the client sees); public queries
 * always force the visibility filter on the server; read through the repository; return arrays
 * shaped like the schema type; page_info follows the core SearchResultPageInfo.
 */
class Items implements ResolverInterface
{
    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ItemFormatter $formatter
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ItemFormatter $formatter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $pageSize = (int)($args['pageSize'] ?? 20);
        $currentPage = (int)($args['currentPage'] ?? 1);
        if ($pageSize < 1 || $pageSize > 100) {
            throw new GraphQlInputException(__('pageSize must be between 1 and 100.'));
        }
        if ($currentPage < 1) {
            throw new GraphQlInputException(__('currentPage must be greater than 0.'));
        }

        $this->searchCriteriaBuilder->addFilter(ItemInterface::STATUS, ItemInterface::STATUS_ENABLED);
        if (isset($args['filter']['code']['eq'])) {
            $this->searchCriteriaBuilder->addFilter(ItemInterface::CODE, (string)$args['filter']['code']['eq']);
        }
        if (isset($args['filter']['name']['match'])) {
            $this->searchCriteriaBuilder->addFilter(ItemInterface::NAME, '%' . $args['filter']['name']['match'] . '%', 'like');
        }
        $criteria = $this->searchCriteriaBuilder->setPageSize($pageSize)->setCurrentPage($currentPage)->create();
        $result = $this->itemRepository->getList($criteria);

        $items = [];
        foreach ($result->getItems() as $item) {
            $items[] = $this->formatter->format($item);
        }
        return [
            'items' => $items,
            'total_count' => $result->getTotalCount(),
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => (int)ceil($result->getTotalCount() / $pageSize),
            ],
        ];
    }
}
