<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Acme\Reference\Api\ItemRepositoryInterface;
use Acme\Reference\Model\ResourceModel\Item\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Grid mass action. Filter::getCollection() applies what the user selected (or "select all" with
 * the grid filters). Each item goes through the repository, so plugins and events run; a failure
 * in one item does not stop the others.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item_delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function execute(): ResultInterface
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        foreach ($collection->getAllIds() as $itemId) {
            try {
                $this->itemRepository->deleteById((int)$itemId);
                $deleted++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        if ($deleted) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deleted));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
