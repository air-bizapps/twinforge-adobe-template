<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\Data\ItemInterfaceFactory;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;

/**
 * Form submit (POST only). The form posts the fields at the root of the request: getPostValue().
 *
 * Pattern: every field goes through a setter with a cast (no setData with the raw request);
 * dates typed in the admin locale are normalized by the core Date filter; on error the posted data
 * is kept in DataPersistor so the form comes back filled; "back" comes from the split Save button.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item_save';
    public const PERSISTOR_KEY = 'acme_reference_item';

    /**
     * @param Context $context
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemInterfaceFactory $itemFactory
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     */
    public function __construct(
        Context $context,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemInterfaceFactory $itemFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly DateFilter $dateFilter
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = (array)$this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data[ItemInterface::ITEM_ID] ?? 0);
        try {
            $item = $id ? $this->itemRepository->getById($id) : $this->itemFactory->create();
            $item->setCode((string)($data[ItemInterface::CODE] ?? ''));
            $item->setName((string)($data[ItemInterface::NAME] ?? ''));
            $item->setStatus((int)($data[ItemInterface::STATUS] ?? ItemInterface::STATUS_ENABLED));
            $price = $data[ItemInterface::PRICE] ?? '';
            $item->setPrice($price === '' || $price === null ? null : (float)$price);
            $validFrom = (string)($data[ItemInterface::VALID_FROM] ?? '');
            $item->setValidFrom($validFrom === '' ? null : $this->dateFilter->filter($validFrom));
            $item->setStoreId((int)($data[ItemInterface::STORE_ID] ?? 0));
            $item->setDescription(isset($data[ItemInterface::DESCRIPTION]) ? (string)$data[ItemInterface::DESCRIPTION] : null);

            $item = $this->itemRepository->save($item);
            $this->messageManager->addSuccessMessage(__('The item has been saved.'));
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            if ($this->getRequest()->getParam('back') === 'continue') {
                return $redirect->setPath('*/*/edit', ['id' => $item->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the item.'));
        }
        $this->dataPersistor->set(self::PERSISTOR_KEY, $data);
        return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }
}
