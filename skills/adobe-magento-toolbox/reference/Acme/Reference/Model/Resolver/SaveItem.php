<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Resolver;

use Acme\Reference\Api\Data\ItemInterfaceFactory;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Mutations createAcmeReferenceItem and updateAcmeReferenceItem (one resolver, the "id" argument
 * tells them apart).
 *
 * Pattern: authorize first (ACL of the admin/integration token); copy only the fields that came in
 * (update keeps the others, see ItemRepository::save); translate domain exceptions into the GraphQL
 * ones the client understands.
 */
class SaveItem implements ResolverInterface
{
    public const ACL_RESOURCE = 'Acme_Reference::item_save';

    /**
     * @param AuthorizationInterface $authorization
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemInterfaceFactory $itemFactory
     * @param ItemFormatter $formatter
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemInterfaceFactory $itemFactory,
        private readonly ItemFormatter $formatter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$this->authorization->isAllowed(self::ACL_RESOURCE)) {
            throw new GraphQlAuthorizationException(__('The current user is not allowed to save items.'));
        }
        $input = (array)($args['input'] ?? []);
        $id = isset($args['id']) ? (int)$args['id'] : null;
        if ($id === null && (empty($input['code']) || empty($input['name']))) {
            throw new GraphQlInputException(__('"code" and "name" are required to create an item.'));
        }

        $item = $this->itemFactory->create();
        if ($id !== null) {
            $item->setId($id);
        }
        if (isset($input['code'])) {
            $item->setCode((string)$input['code']);
        }
        if (isset($input['name'])) {
            $item->setName((string)$input['name']);
        }
        if (isset($input['status'])) {
            $item->setStatus((int)$input['status']);
        }
        if (array_key_exists('price', $input)) {
            $item->setPrice($input['price'] === null ? null : (float)$input['price']);
        }
        if (array_key_exists('valid_from', $input)) {
            $item->setValidFrom($input['valid_from'] === null ? null : (string)$input['valid_from']);
        }
        if (array_key_exists('description', $input)) {
            $item->setDescription($input['description'] === null ? null : (string)$input['description']);
        }

        try {
            return $this->formatter->format($this->itemRepository->save($item));
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('No item with id %1.', $id), $e);
        } catch (CouldNotSaveException $e) {
            throw new GraphQlInputException(__('%1', $e->getMessage()), $e);
        }
    }
}
