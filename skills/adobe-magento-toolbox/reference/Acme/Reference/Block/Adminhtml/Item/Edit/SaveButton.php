<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Block\Adminhtml\Item\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Ui\Component\Control\Container;

/**
 * Split Save button (the same shape as the CMS block form): buttonAdapter calls the form's save
 * action; targetName is "<form name>.<form name>"; params[1] becomes request params ("back").
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    private const TARGET = 'acme_reference_item_form.acme_reference_item_form';

    /**
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['buttonAdapter' => ['actions' => [
                    ['targetName' => self::TARGET, 'actionName' => 'save', 'params' => [true, ['back' => 'continue']]],
                ]]],
            ],
            'class_name' => Container::SPLIT_BUTTON,
            'options' => [
                [
                    'id_hard' => 'save_and_close',
                    'label' => __('Save & Close'),
                    'data_attribute' => [
                        'mage-init' => ['buttonAdapter' => ['actions' => [
                            ['targetName' => self::TARGET, 'actionName' => 'save', 'params' => [true, ['back' => 'close']]],
                        ]]],
                    ],
                ],
            ],
            'sort_order' => 90,
        ];
    }
}
