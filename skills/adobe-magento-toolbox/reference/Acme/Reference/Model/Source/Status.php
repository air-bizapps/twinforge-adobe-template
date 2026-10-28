<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Source;

use Acme\Reference\Api\Data\ItemInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Option source: one class serves the admin form select, the grid column and filter, and a
 * system.xml select (<source_model>). Values are what is stored; labels go through __().
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ItemInterface::STATUS_ENABLED, 'label' => __('Enabled')],
            ['value' => ItemInterface::STATUS_DISABLED, 'label' => __('Disabled')],
        ];
    }
}
