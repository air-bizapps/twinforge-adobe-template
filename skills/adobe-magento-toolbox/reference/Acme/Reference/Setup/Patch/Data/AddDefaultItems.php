<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Setup\Patch\Data;

use Acme\Reference\Model\ResourceModel\Item as ItemResource;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Data patch: runs once per database (registered in patch_list) during setup:upgrade.
 *
 * Pattern: write with the connection (insertOnDuplicate makes it safe to run on a database that
 * already has the rows); never depend on a store, area or logged user; declare in
 * getDependencies() the patches that must run first.
 */
class AddDefaultItems implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->insertOnDuplicate(
            $this->moduleDataSetup->getTable(ItemResource::TABLE),
            [
                ['code' => 'SYSTEM_DEFAULT', 'name' => 'Default item (protected)', 'status' => 1, 'store_id' => 0],
                ['code' => 'SAMPLE', 'name' => 'Sample item', 'status' => 1, 'store_id' => 0],
            ],
            ['name']
        );
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
