<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Block\Adminhtml\Item\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete button, only for an existing item. deleteConfirm(..., {"data": {}}) sends a POST, which
 * the Delete controller requires (HttpPostActionInterface).
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        if (!$this->getItemId()) {
            return [];
        }
        $escaper = $this->context->getEscaper();
        $message = $escaper->escapeJs($escaper->escapeHtml(__('Are you sure you want to delete this item?')));
        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {\"data\": {}})",
                $message,
                $this->getUrl('*/*/delete', ['id' => $this->getItemId()])
            ),
            'sort_order' => 20,
        ];
    }
}
