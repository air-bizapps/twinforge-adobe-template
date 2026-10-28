<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin grid page. The page content is the layout acme_reference_item_index.xml.
 *
 * Pattern for every admin controller:
 * - extends Magento\Backend\App\Action and implements HttpGetActionInterface or HttpPostActionInterface
 *   (the method it accepts; writes are POST only);
 * - ADMIN_RESOURCE = the ACL id from etc/acl.xml;
 * - inherited properties ($resultRedirectFactory, $messageManager...) are used, never redeclared.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Acme_Reference::item');
        $page->getConfig()->getTitle()->prepend(__('Reference Items'));
        return $page;
    }
}
