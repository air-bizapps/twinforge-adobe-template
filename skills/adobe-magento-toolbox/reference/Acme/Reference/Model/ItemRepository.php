<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\Data\ItemSearchResultsInterface;
use Acme\Reference\Api\Data\ItemSearchResultsInterfaceFactory;
use Acme\Reference\Api\ItemRepositoryInterface;
use Acme\Reference\Model\ResourceModel\Item as ItemResource;
use Acme\Reference\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Repository: the service contract implementation.
 *
 * Pattern:
 * - save() with an id merges ONLY the fields that came in (REST PUT, GraphQL update): building a
 *   new object and saving it would wipe the fields the caller did not send (defect class C9);
 * - getList() delegates filters, sorting and paging to CollectionProcessorInterface;
 * - not found is NoSuchEntityException; save and delete failures are wrapped in
 *   CouldNotSave/CouldNotDelete, which the Web API turns into HTTP 400.
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * @param ItemResource $resource
     * @param ItemFactory $itemFactory
     * @param CollectionFactory $collectionFactory
     * @param ItemSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ItemResource $resource,
        private readonly ItemFactory $itemFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ItemSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(ItemInterface $item): ItemInterface
    {
        if (!$item instanceof Item) {
            throw new CouldNotSaveException(__('Unsupported item implementation: %1', get_class($item)));
        }
        if ($item->getId()) {
            $existing = $this->load((int)$item->getId());
            $existing->addData($item->getData());
            $item = $existing;
        }
        try {
            $this->resource->save($item);
        } catch (AlreadyExistsException $e) {
            throw new CouldNotSaveException(__('An item with code "%1" already exists.', $item->getCode()), $e);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the item: %1', $e->getMessage()), $e);
        }
        return $item;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $itemId): ItemInterface
    {
        return $this->load($itemId);
    }

    /**
     * @inheritdoc
     */
    public function getByCode(string $code): ItemInterface
    {
        $item = $this->itemFactory->create();
        $this->resource->load($item, $code, ItemInterface::CODE);
        if (!$item->getId()) {
            throw NoSuchEntityException::singleField(ItemInterface::CODE, $code);
        }
        return $item;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ItemSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(ItemInterface $item): bool
    {
        try {
            $this->resource->delete($item);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the item: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $itemId): bool
    {
        return $this->delete($this->load($itemId));
    }

    /**
     * @param int $itemId
     * @return Item
     * @throws NoSuchEntityException
     */
    private function load(int $itemId): Item
    {
        $item = $this->itemFactory->create();
        $this->resource->load($item, $itemId);
        if (!$item->getId()) {
            throw NoSuchEntityException::singleField('id', $itemId);
        }
        return $item;
    }
}
