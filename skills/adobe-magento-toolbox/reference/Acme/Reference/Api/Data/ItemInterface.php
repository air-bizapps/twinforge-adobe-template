<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Api\Data;

/**
 * Data contract of the entity (service contract).
 *
 * Pattern: field names as constants; every getter and setter has a docblock with the full type.
 * The Web API reads the types from the docblocks (Magento\Framework\Reflection\TypeProcessor):
 * a method without @return or @param fails at runtime in REST, not at compile time.
 * getId()/setId() stay untyped because AbstractModel already declares them that way.
 *
 * @api
 */
interface ItemInterface
{
    public const ITEM_ID = 'item_id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const STATUS = 'status';
    public const PRICE = 'price';
    public const VALID_FROM = 'valid_from';
    public const STORE_ID = 'store_id';
    public const DESCRIPTION = 'description';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return int|null
     */
    public function getStatus(): ?int;

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * @return float|null
     */
    public function getPrice(): ?float;

    /**
     * @param float|null $price
     * @return $this
     */
    public function setPrice(?float $price): self;

    /**
     * Date in Y-m-d.
     *
     * @return string|null
     */
    public function getValidFrom(): ?string;

    /**
     * @param string|null $validFrom Date in Y-m-d
     * @return $this
     */
    public function setValidFrom(?string $validFrom): self;

    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
