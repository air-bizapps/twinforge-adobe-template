<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Block\Adminhtml\Item\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Base of the form buttons. Buttons are classes implementing ButtonProviderInterface, referenced in
 * the form XML as <button name="save" class="..."/>; <button> has no <action> or <text> child.
 */
class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected readonly Context $context
    ) {
    }

    /**
     * @return int|null id of the item being edited
     */
    public function getItemId(): ?int
    {
        $id = (int)$this->context->getRequest()->getParam('id');
        return $id ?: null;
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
