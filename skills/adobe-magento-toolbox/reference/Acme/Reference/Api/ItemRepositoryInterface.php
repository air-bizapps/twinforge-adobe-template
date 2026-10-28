<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Api;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\Data\ItemSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Repository (service contract): the only entry point that REST, GraphQL, admin and other modules use.
 * Plugins go on this interface. Parameter names match the {placeholders} of etc/webapi.xml.
 *
 * @api
 */
interface ItemRepositoryInterface
{
    /**
     * Create, or update only the fields that came in when the item has an id.
     *
     * @param \Acme\Reference\Api\Data\ItemInterface $item
     * @return \Acme\Reference\Api\Data\ItemInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function save(ItemInterface $item): ItemInterface;

    /**
     * @param int $itemId
     * @return \Acme\Reference\Api\Data\ItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $itemId): ItemInterface;

    /**
     * @param string $code
     * @return \Acme\Reference\Api\Data\ItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByCode(string $code): ItemInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Acme\Reference\Api\Data\ItemSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ItemSearchResultsInterface;

    /**
     * @param \Acme\Reference\Api\Data\ItemInterface $item
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(ItemInterface $item): bool;

    /**
     * @param int $itemId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $itemId): bool;
}
