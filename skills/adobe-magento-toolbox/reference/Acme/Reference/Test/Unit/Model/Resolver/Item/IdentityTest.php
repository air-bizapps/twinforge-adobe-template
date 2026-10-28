<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Test\Unit\Model\Resolver\Item;

use Acme\Reference\Model\Resolver\Item\Identity;
use PHPUnit\Framework\TestCase;

/**
 * Pure class, no mocks: the cache tags must match Item::getIdentities() for both answer shapes.
 */
class IdentityTest extends TestCase
{
    public function testTagsOfAListAnswer(): void
    {
        $tags = (new Identity())->getIdentities(['items' => [['id' => 1], ['id' => 2]]]);

        $this->assertSame(['acme_reference_item', 'acme_reference_item_1', 'acme_reference_item_2'], $tags);
    }

    public function testTagsOfASingleItemAnswer(): void
    {
        $this->assertSame(
            ['acme_reference_item', 'acme_reference_item_9'],
            (new Identity())->getIdentities(['id' => 9, 'code' => 'X'])
        );
    }
}
