<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Plugin;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * The three plugin kinds in one class, on a service contract (declared in etc/di.xml under
 * <type name="Acme\Reference\Api\ItemRepositoryInterface">).
 *
 * Rules:
 * - plug into an interface of the public API, on public methods; never on final, static or
 *   __construct, and prefer a plugin to a <preference> that replaces a core class;
 * - before{Method}: receives the original arguments, returns them as an array (or null to keep);
 * - around{Method}: only when you must decide whether the original runs. Call $proceed with the
 *   arguments and ALWAYS return its result (or throw). It costs a frame on every call: prefer
 *   before/after when they solve it;
 * - after{Method}: receives the result first, then the original arguments; returns the result;
 * - the first parameter is always $subject, typed with the plugged class or interface;
 * - argument types must match the original method: setup:di:compile rejects a mismatch.
 */
class ItemRepositoryPlugin
{
    /**
     * Codes starting with this prefix belong to the system and cannot be deleted.
     */
    public const PROTECTED_PREFIX = 'SYSTEM_';

    /**
     * Before: normalize the code before any save (admin, REST, GraphQL and imports all pass here).
     *
     * @param ItemRepositoryInterface $subject
     * @param ItemInterface $item
     * @return array
     */
    public function beforeSave(ItemRepositoryInterface $subject, ItemInterface $item): array
    {
        if ($item->getCode() !== null) {
            $item->setCode(strtoupper(trim($item->getCode())));
        }
        return [$item];
    }

    /**
     * Around: decides whether the original runs (protected items are never deleted).
     *
     * @param ItemRepositoryInterface $subject
     * @param callable $proceed
     * @param int $itemId
     * @return bool
     * @throws LocalizedException
     */
    public function aroundDeleteById(ItemRepositoryInterface $subject, callable $proceed, int $itemId): bool
    {
        $item = $subject->getById($itemId);
        if (str_starts_with((string)$item->getCode(), self::PROTECTED_PREFIX)) {
            throw new LocalizedException(
                __('The item "%1" is protected and cannot be deleted.', $item->getCode())
            );
        }
        return $proceed($itemId);
    }

    /**
     * After: completes the result (an item without name shows its code).
     *
     * @param ItemRepositoryInterface $subject
     * @param ItemInterface $result
     * @param int $itemId
     * @return ItemInterface
     */
    public function afterGetById(ItemRepositoryInterface $subject, ItemInterface $result, int $itemId): ItemInterface
    {
        if ((string)$result->getName() === '' && $result->getCode() !== null) {
            $result->setName($result->getCode());
        }
        return $result;
    }
}
