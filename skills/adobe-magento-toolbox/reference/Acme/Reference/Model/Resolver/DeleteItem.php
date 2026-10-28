<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver;

use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Mutation deleteAcmeReferenceItem. Goes through the repository, so the protection of
 * ItemRepositoryPlugin::aroundDeleteById also applies to GraphQL.
 */
class DeleteItem implements ResolverInterface
{
    public const ACL_RESOURCE = 'Acme_Reference::item_delete';

    /**
     * @param AuthorizationInterface $authorization
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$this->authorization->isAllowed(self::ACL_RESOURCE)) {
            throw new GraphQlAuthorizationException(__('The current user is not allowed to delete items.'));
        }
        $id = (int)($args['id'] ?? 0);
        try {
            return $this->itemRepository->deleteById($id);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('No item with id %1.', $id), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__('%1', $e->getMessage()), $e);
        }
    }
}
