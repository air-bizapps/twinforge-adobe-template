<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver;

use Acme\Reference\Api\Data\ItemInterface;

/**
 * Turns an item into the array of the GraphQL type AcmeReferenceItem. Resolvers return arrays
 * whose keys are the schema field names; keeping the mapping in one place keeps them in sync.
 */
class ItemFormatter
{
    /**
     * @param ItemInterface $item
     * @return array
     */
    public function format(ItemInterface $item): array
    {
        return [
            'id' => (int)$item->getId(),
            'code' => $item->getCode(),
            'name' => $item->getName(),
            'status' => $item->getStatus(),
            'price' => $item->getPrice(),
            'valid_from' => $item->getValidFrom(),
            'description' => $item->getDescription(),
        ];
    }
}
