<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Test\Unit\Plugin;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Acme\Reference\Plugin\ItemRepositoryPlugin;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test pattern: the path mirrors the class (Test/Unit/Plugin/ItemRepositoryPluginTest.php);
 * every property is DECLARED (dynamic properties are deprecated in PHP 8.2 and the Magento unit
 * bootstrap turns the deprecation into an error); mock interfaces, not concrete classes; one
 * behaviour per test, named after it.
 */
class ItemRepositoryPluginTest extends TestCase
{
    /**
     * @var ItemRepositoryPlugin
     */
    private ItemRepositoryPlugin $plugin;

    /**
     * @var ItemRepositoryInterface|MockObject
     */
    private ItemRepositoryInterface|MockObject $subject;

    protected function setUp(): void
    {
        $this->plugin = new ItemRepositoryPlugin();
        $this->subject = $this->createMock(ItemRepositoryInterface::class);
    }

    public function testBeforeSaveNormalizesTheCode(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->method('getCode')->willReturn('  abc_1 ');
        $item->expects($this->once())->method('setCode')->with('ABC_1');

        $this->assertSame([$item], $this->plugin->beforeSave($this->subject, $item));
    }

    public function testAroundDeleteByIdBlocksProtectedItems(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->method('getCode')->willReturn(ItemRepositoryPlugin::PROTECTED_PREFIX . 'DEFAULT');
        $this->subject->method('getById')->with(7)->willReturn($item);
        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): bool {
            $proceedCalled = true;
            return true;
        };

        $this->expectException(LocalizedException::class);
        try {
            $this->plugin->aroundDeleteById($this->subject, $proceed, 7);
        } finally {
            $this->assertFalse($proceedCalled, 'The original deleteById must not run for a protected item.');
        }
    }

    public function testAroundDeleteByIdCallsTheOriginalAndReturnsItsResult(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->method('getCode')->willReturn('SAMPLE');
        $this->subject->method('getById')->willReturn($item);

        $result = $this->plugin->aroundDeleteById($this->subject, fn(int $id): bool => $id === 7, 7);

        $this->assertTrue($result);
    }

    public function testAfterGetByIdUsesTheCodeWhenTheNameIsEmpty(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->method('getName')->willReturn('');
        $item->method('getCode')->willReturn('SAMPLE');
        $item->expects($this->once())->method('setName')->with('SAMPLE');

        $this->assertSame($item, $this->plugin->afterGetById($this->subject, $item, 1));
    }
}
