<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Edit page. The form loads its own data through the form DataProvider (request param "id"),
 * so the controller only checks that the item exists and sets the title. No Registry.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        $title = __('New Item');
        if ($id) {
            try {
                $title = __('Edit Item %1', $this->itemRepository->getById($id)->getCode());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This item no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Acme_Reference::item');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
