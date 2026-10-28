<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Query acmeReferenceItem(code): one enabled item. A disabled item answers exactly like a missing
 * one, so the public API does not reveal what exists.
 */
class Item implements ResolverInterface
{
    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemFormatter $formatter
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemFormatter $formatter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $code = trim((string)($args['code'] ?? ''));
        try {
            $item = $this->itemRepository->getByCode(strtoupper($code));
        } catch (NoSuchEntityException $e) {
            $item = null;
        }
        if ($item === null || $item->getStatus() !== ItemInterface::STATUS_ENABLED) {
            throw new GraphQlNoSuchEntityException(__('No item with code "%1".', $code));
        }
        return $this->formatter->format($item);
    }
}
