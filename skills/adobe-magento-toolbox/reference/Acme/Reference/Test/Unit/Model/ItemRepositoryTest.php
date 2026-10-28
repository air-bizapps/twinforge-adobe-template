<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Test\Unit\Model;

use Acme\Reference\Api\Data\ItemSearchResultsInterfaceFactory;
use Acme\Reference\Model\Item;
use Acme\Reference\Model\ItemFactory;
use Acme\Reference\Model\ItemRepository;
use Acme\Reference\Model\ResourceModel\Item as ItemResource;
use Acme\Reference\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the partial update (C9): saving an item with an id keeps the fields it did not send.
 * Models are built with createPartialMock(Model::class, []) (no constructor): set the id field name
 * that _init() would set. Generated factories (ItemFactory) are created by the unit test bootstrap.
 */
class ItemRepositoryTest extends TestCase
{
    /**
     * @var ItemResource|MockObject
     */
    private ItemResource|MockObject $resource;

    /**
     * @var ItemFactory|MockObject
     */
    private ItemFactory|MockObject $itemFactory;

    /**
     * @var ItemRepository
     */
    private ItemRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ItemResource::class);
        $this->itemFactory = $this->createMock(ItemFactory::class);
        $this->repository = new ItemRepository(
            $this->resource,
            $this->itemFactory,
            $this->createMock(CollectionFactory::class),
            $this->createMock(ItemSearchResultsInterfaceFactory::class),
            $this->createMock(CollectionProcessorInterface::class)
        );
    }

    public function testSaveWithIdKeepsTheFieldsThatWereNotSent(): void
    {
        $existing = $this->createPartialMock(Item::class, []);
        $existing->setIdFieldName('item_id');
        $existing->setData(['item_id' => 5, 'code' => 'OLD', 'name' => 'Old name', 'price' => '10.0000']);
        $this->itemFactory->method('create')->willReturn($existing);
        $this->resource->method('load')->willReturnCallback(fn() => $this->resource);

        $incoming = $this->createPartialMock(Item::class, []);
        $incoming->setIdFieldName('item_id');
        $incoming->setData(['item_id' => 5, 'name' => 'New name']);

        $saved = $this->repository->save($incoming);

        $this->assertSame('New name', $saved->getName());
        $this->assertSame('OLD', $saved->getCode());
        $this->assertSame(10.0, $saved->getPrice());
    }

    public function testGetByIdThrowsWhenTheItemDoesNotExist(): void
    {
        $empty = $this->createPartialMock(Item::class, []);
        $empty->setIdFieldName('item_id');
        $this->itemFactory->method('create')->willReturn($empty);

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById(404);
    }
}
