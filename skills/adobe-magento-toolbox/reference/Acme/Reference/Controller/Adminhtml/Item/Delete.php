<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Delete (POST: the form's Delete button posts with deleteConfirm). Goes through the repository,
 * so the protection in ItemRepositoryPlugin::aroundDeleteById applies here too.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item_delete';

    /**
     * @param Context $context
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        Context $context,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('We can\'t find an item to delete.'));
            return $redirect->setPath('*/*/');
        }
        try {
            $this->itemRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The item has been deleted.'));
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/edit', ['id' => $id]);
    }
}
