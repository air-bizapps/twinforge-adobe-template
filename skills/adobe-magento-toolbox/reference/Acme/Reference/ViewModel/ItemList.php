<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\ViewModel;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel of the storefront template (passed as the "view_model" argument in the layout).
 *
 * Pattern: implements ArgumentInterface; holds the logic the template needs, so the template
 * only prints; JSON for x-magento-init is built with JsonHexTag (escapes < > & ' " inside a
 * <script>), which is why the template may print it with @noEscape.
 */
class ItemList implements ArgumentInterface
{
    private const PAGE_SIZE = 20;

    /**
     * @var ItemInterface[]|null
     */
    private ?array $items = null;

    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param JsonHexTag $jsonHexTag
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly JsonHexTag $jsonHexTag
    ) {
    }

    /**
     * @return ItemInterface[]
     */
    public function getItems(): array
    {
        if ($this->items === null) {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter(ItemInterface::STATUS, ItemInterface::STATUS_ENABLED)
                ->setPageSize(self::PAGE_SIZE)
                ->setCurrentPage(1)
                ->create();
            $this->items = array_values($this->itemRepository->getList($criteria)->getItems());
        }
        return $this->items;
    }

    /**
     * Configuration of the JavaScript components started by the template (x-magento-init).
     *
     * @return string
     */
    public function getInitJson(): string
    {
        return $this->jsonHexTag->serialize([
            '#acme-reference-items' => [
                'Acme_Reference/js/item-list' => ['highlightClass' => 'is-highlighted'],
            ],
            '*' => [
                'Magento_Ui/js/core/app' => [
                    'components' => [
                        'acmeReferenceCounter' => [
                            'component' => 'Acme_Reference/js/view/item-counter',
                            'total' => count($this->getItems()),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * data-mage-init of each row (jQuery widget, alias declared in requirejs-config.js).
     *
     * @return string
     */
    public function getToggleInitJson(): string
    {
        return $this->jsonHexTag->serialize(['acmeToggleDescription' => ['hiddenClass' => 'no-display']]);
    }
}
