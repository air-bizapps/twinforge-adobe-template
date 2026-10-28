<?php
declare(strict_types=1);

namespace Acme\Demo\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\RedirectFactory;

class Edit extends Action
{
    private RedirectFactory $resultRedirectFactory;

    public function execute()
    {
        return $this->resultRedirectFactory->create();
    }
}
