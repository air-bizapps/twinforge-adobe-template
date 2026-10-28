<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Account;

use Magento\Customer\Controller\AccountInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Page inside "My Account" (/acme-reference/account). Implementing
 * Magento\Customer\Controller\AccountInterface makes the core plugin redirect guests to the login
 * page; the layout adds the account navigation through <update handle="customer_account"/>.
 */
class Index implements HttpGetActionInterface, AccountInterface
{
    /**
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Reference Items'));
        return $page;
    }
}
