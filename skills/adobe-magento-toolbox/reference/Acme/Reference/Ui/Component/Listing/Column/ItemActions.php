<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions of the grid (Edit, Delete). The actions are built here, in prepareDataSource();
 * an <item name="actions"> in the listing XML is a legacy shape that fails in this Magento version.
 *
 * Constructor: the parent's parameters (context, uiComponentFactory, components, data) in the
 * parent's order, extra dependencies before the arrays, and parent::__construct with exactly the
 * parent's parameters.
 */
class ItemActions extends Column
{
    private const URL_EDIT = 'acme_reference/item/edit';
    private const URL_DELETE = 'acme_reference/item/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['item_id'])) {
                continue;
            }
            $code = $this->escaper->escapeHtml((string)($item['code'] ?? ''));
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_EDIT, ['id' => $item['item_id']]),
                'label' => __('Edit'),
            ];
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_DELETE, ['id' => $item['item_id']]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete %1', $code),
                    'message' => __('Are you sure you want to delete the item %1?', $code),
                ],
                'post' => true,
            ];
        }
        return $dataSource;
    }
}
