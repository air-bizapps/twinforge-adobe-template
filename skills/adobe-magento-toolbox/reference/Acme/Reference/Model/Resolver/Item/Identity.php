<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver\Item;

use Acme\Reference\Model\Item;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

/**
 * Cache tags of the GraphQL answers (@cache(cacheIdentity: ...) in schema.graphqls). The tags are the
 * same that Item::getIdentities() cleans on save, so a cached answer never outlives the data.
 * Handles both shapes: a list ({items: [...]}) and a single item.
 */
class Identity implements IdentityInterface
{
    /**
     * @param array $resolvedData
     * @return string[]
     */
    public function getIdentities(array $resolvedData): array
    {
        $items = isset($resolvedData['items']) ? (array)$resolvedData['items'] : [$resolvedData];
        $tags = [Item::CACHE_TAG];
        foreach ($items as $item) {
            if (!empty($item['id'])) {
                $tags[] = Item::CACHE_TAG . '_' . $item['id'];
            }
        }
        return $tags;
    }
}
