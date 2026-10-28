<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Model\ResourceModel\Item as ItemResource;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Entity model.
 *
 * Pattern:
 * - inherited properties ($_eventPrefix, $_eventObject, $_cacheTag) are redeclared WITHOUT a native
 *   type and with the parent's visibility: adding a type or narrowing visibility is a fatal error
 *   when the class loads, and php -l does not catch it;
 * - $_eventPrefix makes Magento dispatch acme_reference_item_save_after, ..._save_commit_after, etc.;
 * - getters cast: the database returns strings, and a strict ?int return type rejects "1";
 * - IdentityInterface: saving the item cleans the full page and GraphQL caches tagged with it.
 */
class Item extends AbstractModel implements ItemInterface, IdentityInterface
{
    public const CACHE_TAG = 'acme_reference_item';

    /**
     * @var string
     */
    protected $_eventPrefix = 'acme_reference_item';

    /**
     * @var string
     */
    protected $_eventObject = 'item';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ItemResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritdoc
     */
    public function getCode(): ?string
    {
        $value = $this->getData(self::CODE);
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function setCode(string $code): ItemInterface
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        $value = $this->getData(self::NAME);
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): ItemInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?int
    {
        $value = $this->getData(self::STATUS);
        return $value === null ? null : (int)$value;
    }

    /**
     * @inheritdoc
     */
    public function setStatus(int $status): ItemInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getPrice(): ?float
    {
        $value = $this->getData(self::PRICE);
        return $value === null || $value === '' ? null : (float)$value;
    }

    /**
     * @inheritdoc
     */
    public function setPrice(?float $price): ItemInterface
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @inheritdoc
     */
    public function getValidFrom(): ?string
    {
        $value = $this->getData(self::VALID_FROM);
        return $value === null || $value === '' ? null : substr((string)$value, 0, 10);
    }

    /**
     * @inheritdoc
     */
    public function setValidFrom(?string $validFrom): ItemInterface
    {
        return $this->setData(self::VALID_FROM, $validFrom === '' ? null : $validFrom);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): ?int
    {
        $value = $this->getData(self::STORE_ID);
        return $value === null ? null : (int)$value;
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): ItemInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        $value = $this->getData(self::DESCRIPTION);
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function setDescription(?string $description): ItemInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);
        return $value === null ? null : (string)$value;
    }
}
