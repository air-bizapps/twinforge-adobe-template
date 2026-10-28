---
name: adobe-magento-model-developer
description: "Data layer decisions in Magento 2.4.8: flat table vs EAV, loading and saving through resource models, model events and cache tags, collections and bulk writes, transactions and after-commit work. Use when designing an entity's storage or writing code that reads or writes it. Scaffolding the files: adobe-create-entity; repository and Web API: adobe-create-repository."
---

# Magento model developer

How models, resource models and collections behave and how to use them. The files and their
boundaries (schema, whitelist, interface constants, casting getters, no logic in collections) come
from `adobe-create-entity`; the repository from `adobe-create-repository`. `$T` is the `scripts/`
folder of `adobe-magento-toolbox`.

## Rules

**Storage**
- **A new entity is a flat table** (`db_schema.xml`). Do not build a custom EAV entity: it costs a
  join per attribute, its own indexers and admin plumbing.
- **To add a field to an existing EAV entity** (product, category, customer, address), add an
  attribute in a data patch: `magento-example eav-product-attribute` or `eav-customer-attribute`.
  Adobe Commerce links product and category values by `row_id` (`adobe-magento-catalog-specialist`).
- **To add data to a core entity's API** (order, quote), use extension attributes
  (`adobe-create-entity`, section 4).
- **Index every column you filter, sort or join on**, in `db_schema.xml`; foreign keys follow
  `adobe-create-entity`.

**Loading and saving**
- **Load through the resource model into a new instance**:
  `$model = $this->entityFactory->create(); $this->resource->load($model, $id);`.
  `AbstractDb::load(AbstractModel $object, $value, $field = null)` fills the object you pass; then
  check `$model->getId()` and throw `NoSuchEntityException::singleField('entity_id', $id)`. A missing
  row returns an empty model, not an exception.
- **Do not call `$model->load()`, `save()`, `delete()` or `getCollection()`**: `@deprecated` in
  `AbstractModel` since 100.1.0/101.0.0. Use the resource model, a `CollectionFactory` and the
  repository.
- **Inject `CollectionFactory`, never a `Collection`**: an injected collection is a shared instance,
  so filters and loaded items leak between callers.
- **Set a unique `$_eventPrefix`** (and `$_eventObject`) on each model so `<prefix>_save_after`,
  `<prefix>_delete_after` and `<prefix>_load_after` can be observed. The default `core_abstract`
  fires for every model without one.
- **Entities shown on cached pages implement `IdentityInterface`**: a `CACHE_TAG` constant,
  `$_cacheTag`, and `getIdentities()` returning `CACHE_TAG . '_' . $id`. Saving cleans those tags, so
  cached blocks and GraphQL answers refresh (`adobe-magento-cache-analyst`).

**Queries and volume**
- Query rules (no load in a loop, select columns, `getSize()`, paging, `EXPLAIN`) are in
  `adobe-magento-performance-analyst`, "Queries".
- **Iterate very large sets with `Magento\Framework\Model\ResourceModel\Iterator::walk()`**, or page
  with `setPageSize()`/`setCurPage()` and `clear()` each page, so memory stays flat.
- **Bulk writes use the connection**: `insertMultiple()` or `insertOnDuplicate()` on
  `$resource->getConnection()`. They skip model events and cache cleaning, so clean tags or
  reindex yourself and say so in the report.

**Transactions**
- **`AbstractDb::save()` already runs in a transaction for one row.** For several models that must
  commit together, use `Magento\Framework\DB\Transaction`: `addObject($a)->addObject($b)->save()`.
- **Side effects run after commit**: publishing to a queue, reindexing or calling an external system
  goes in `$resource->addCommitCallback()` or a `<prefix>_save_commit_after` observer. Inside
  `_afterSave()` they also run when the transaction rolls back.
- **Business validation stays out of the model** (`adobe-inject-validator`). `_beforeSave()` in the
  resource model only enforces persistence invariants such as timestamps.

Property types in `AbstractModel` children that Magento serializes, and string values from the
database: `adobe-commerce-standards`, "PHP 8.3".

## Steps

1. Decide the storage (Rules, Storage) and confirm every field with the requester.
2. Scaffold with `adobe-create-entity` (`magento-example model`, `db-schema`); add indexes.
3. Add `$_eventPrefix`, and `IdentityInterface` if the entity is rendered on cached output.
4. Build the repository with `adobe-create-repository`.
5. Write reads with a `CollectionFactory` and writes through the resource model or `Transaction`.

## Verify

- `bin/magento setup:upgrade --dry-run=1` writes the planned SQL to
  `var/log/dry-run-installation.log`; read it before the real run.
- `bin/magento setup:db:status` is up to date after `setup:upgrade`.
- Print a collection's SQL with `(string)$collection->getSelect()` and run `EXPLAIN` on it.
- `php "$T/magento-api.php" 'Magento\Framework\Model\ResourceModel\Db\AbstractDb'` before
  overriding a resource method; `php "$T/magento-verify.php"` ends in `VERIFY OK`.

## Pitfalls

- `getById()` returns an empty object instead of 404 -> check `getId()` after `load()` and throw.
- Results from another request's filter -> an injected `Collection`; switch to `CollectionFactory`.
- Observer never fires for your entity -> no `$_eventPrefix`, so it fires as `core_abstract_*`.
- Queue message processed before the row exists -> published inside the transaction; use
  `addCommitCallback()`.
- Import loop of thousands of `save()` calls -> one `insertOnDuplicate()` per batch.

## Docs

- https://developer.adobe.com/commerce/php/development/components/declarative-schema/
- https://developer.adobe.com/commerce/php/development/components/service-contracts/
- https://developer.adobe.com/commerce/php/development/components/extension-attributes/
- https://developer.adobe.com/commerce/php/development/components/indexing/
