<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\ResourceModel\Item;

use Acme\Reference\Model\Item;
use Acme\Reference\Model\ResourceModel\Item as ItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection. Inject Acme\Reference\Model\ResourceModel\Item\CollectionFactory (generated), never
 * the collection itself: a collection is stateful and must be created per use.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'acme_reference_item_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'item_collection';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Item::class, ItemResource::class);
    }
}
