<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Ui\DataProvider;

use Acme\Reference\Controller\Adminhtml\Item\Save;
use Acme\Reference\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;

/**
 * Data provider of the edit form (the same shape as Magento\Cms\Model\Block\DataProvider).
 *
 * Pattern:
 * - extends a REAL core class (ModifierPoolDataProvider); its constructor is name, primaryFieldName,
 *   requestFieldName, meta, data, pool - pass exactly those to parent::__construct;
 * - $collection is inherited and untyped: assign it, never redeclare it with a type (fatal);
 * - getData() returns [id => fields]; the framework already filters the collection by the request
 *   field ("id"), so only the edited item is loaded;
 * - data kept by Save after a failure (DataPersistor) is shown again.
 */
class ItemFormDataProvider extends ModifierPoolDataProvider
{
    /**
     * @var array|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = [],
        ?PoolInterface $pool = null
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $item) {
            $this->loadedData[$item->getId()] = $item->getData();
        }
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (!empty($persisted)) {
            $item = $this->collection->getNewEmptyItem();
            $item->setData($persisted);
            $this->loadedData[$item->getId()] = $item->getData();
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);
        }
        return $this->loadedData;
    }
}
