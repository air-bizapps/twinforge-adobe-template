<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Controller\Adminhtml\Item;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Grid inline edit (editorConfig.clientConfig.saveUrl in the listing XML). Receives
 * items[<id>][<field>] and answers JSON {messages: [], error: bool}, the shape the grid expects.
 * Only the columns that have an <editor> in the listing are accepted.
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_Reference::item_save';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $items = (array)$this->getRequest()->getParam('items', []);
        if (!$this->getRequest()->getParam('isAjax') || !$items) {
            return $result->setData(['messages' => [__('Please correct the data sent.')], 'error' => true]);
        }
        $messages = [];
        foreach ($items as $itemId => $fields) {
            try {
                $item = $this->itemRepository->getById((int)$itemId);
                if (isset($fields[ItemInterface::CODE])) {
                    $item->setCode((string)$fields[ItemInterface::CODE]);
                }
                if (isset($fields[ItemInterface::NAME])) {
                    $item->setName((string)$fields[ItemInterface::NAME]);
                }
                if (isset($fields[ItemInterface::STATUS])) {
                    $item->setStatus((int)$fields[ItemInterface::STATUS]);
                }
                $this->itemRepository->save($item);
            } catch (\Exception $e) {
                $messages[] = __('[ID %1] %2', $itemId, $e->getMessage());
            }
        }
        return $result->setData(['messages' => $messages, 'error' => (bool)$messages]);
    }
}
