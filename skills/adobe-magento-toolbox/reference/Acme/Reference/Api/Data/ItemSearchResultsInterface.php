<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results of getList(): only narrows the item type (the docblock is what the Web API reads).
 *
 * @api
 */
interface ItemSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Acme\Reference\Api\Data\ItemInterface[]
     */
    public function getItems();

    /**
     * @param \Acme\Reference\Api\Data\ItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
