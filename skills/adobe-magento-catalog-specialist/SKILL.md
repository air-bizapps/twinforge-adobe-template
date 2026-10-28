---
name: adobe-magento-catalog-specialist
description: "Native Magento 2.4.8 / Adobe Commerce catalog model a developer must respect: product types, EAV and row_id, attribute sets and scopes, prices, categories and URL rewrites, product links, search attributes, stock (MSI), and B2B company and shared catalog. Use when code reads or writes products, prices, categories or stock, or when inspecting catalog data. Quote, order and documents: adobe-magento-order-specialist. Project business rules: bunge-domain-lookup."
---

# Magento catalog (products, prices, stock, shared catalog)

Native Magento behavior only. What the project decided on top of it (pricing, credit, approval flows) comes from `bunge-domain-lookup`. Carts, orders, invoices and negotiable quotes: `adobe-magento-order-specialist`.

## Rules: catalog

- **Product types** (`type_id`): `simple`, `virtual`, `configurable`, `grouped`, `bundle`, `downloadable`, `giftcard` (Commerce). A configurable or bundle line exists twice in the quote and order: parent plus child rows (`adobe-magento-order-specialist`).
- **EAV values sit in `catalog_product_entity_{int,varchar,decimal,text,datetime}` by `store_id`** (0 = default). In Adobe Commerce (content staging), value tables link by `row_id`, and `entity_id` is disabled there. Avoid raw SQL; if unavoidable, take the column from `MetadataPool::getMetadata(ProductInterface::class)->getLinkField()`. New attribute: `php "$T/magento-example.php" eav-product-attribute` (`$T` is the `scripts/` folder of `adobe-magento-toolbox`).
- **An attribute only shows on products whose attribute set has it**: the data patch adds it to the sets (and group) that need it; a value saved on a product outside the set is not displayed or editable.
- **Attribute scope**: `ScopedAttributeInterface::SCOPE_STORE` (0), `SCOPE_GLOBAL` (1), `SCOPE_WEBSITE` (2). Store scope only for translatable text.
  - Writes in a store-view context create store-level copies that stop following the default: the REST `/rest/V1/` and `/rest/<store>/V1/` paths, or the repository with a store view current.
  - Use `/rest/all/V1/` or store id 0 for default values.
  - Use `Magento\Catalog\Model\Product\Action::updateAttributes($ids, $data, $storeId)` for a few attributes on many products.
- **Search and layered navigation flags** (`is_searchable`, `is_filterable`, `used_in_product_listing`, `used_for_sort_by`) take effect only after the `catalogsearch_fulltext` reindex; `used_in_product_listing` = 0 means the value is missing from collections and listing GraphQL.
- **Price scope** (`catalog/price/scope`) is global (0) or website (1); there is no store-view price.
  - Read prices through `$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue()`, which applies special, tier/group, catalog rule and shared catalog prices. The raw `price` attribute ignores all of them.
  - Listings read `catalog_product_index_price`: after price changes, check the `catalog_product_price` and `catalogrule_rule` indexers.
- **Categories**: tree by `path` (`1/2/15`), one root per store group; moving a category regenerates its products' URL rewrites (heavy).
- **URLs come from `url_rewrite`**, generated from `url_key` per store on save. A `url_key` must be unique per store; imports or direct SQL that skip the model leave products without URLs or 404s.
- **Product links** (`catalog_product_link`): related = 1, up-sell = 4, cross-sell = 5. Write them with `ProductLinkRepositoryInterface`, not raw inserts.
- **Images go through the media gallery** (`ProductAttributeMediaGalleryManagementInterface`) with roles (`image`, `small_image`, `thumbnail`); a file copied into `pub/media` alone is not linked.
- **Stock (MSI)**: sell against salable quantity (source quantity minus `inventory_reservation`) with `GetProductSalableQtyInterface` / `IsProductSalableInterface` and the website's stock id, not `qty` from `cataloginventory_stock_item`. Source quantities change through `SourceItemsSaveInterface`.

## Rules: B2B catalog

- **Features are toggled per website**: `btob/website_configuration/{company_active,sharedcatalog_active,negotiablequote_active,quickorder_active,requisition_list_active,purchaseorder_enabled}`.
- **A company groups customers** (company admin, users, roles); its `customer_group_id` comes from its shared catalog.
- **A shared catalog is a customer group**: custom prices are tier prices for that group, and when enabled it fully controls category permissions. Changes apply through the `sharedCatalogUpdatePrice` and `sharedCatalogUpdateCategoryPermissions` consumers. The public catalog (`type` 1) serves guests and customers without a company.
- **A product missing from a company's shared catalog is invisible to it** on listing, search and direct URL; check assignment before suspecting indexes.

## Steps (inspecting catalog data)

1. Read through repositories or REST with a store code; SQL read-only, with the link field.
2. Check the scope read (default or store copy) and the indexers behind the page.
3. B2B: check company, group and shared catalog first.

## Verify

- `php "$T/magento-api.php" 'Magento\Catalog\Api\ProductRepositoryInterface'` (real signatures) and `php "$T/magento-verify.php"` before done.
- `bin/magento indexer:status`; `bin/magento queue:consumers:list | grep sharedCatalog`.
- `bin/magento config:show catalog/price/scope`; `bin/magento config:show btob/website_configuration/sharedcatalog_active --scope=websites --scope-code=<code>`.
- Read-only SQL: `SELECT store_id, request_path FROM url_rewrite WHERE entity_type = 'product' AND entity_id = <id>`.

## Pitfalls

- Shared catalog price changed but storefront unchanged -> consumers not running or price index stale.
- Attribute value changed in the default scope, store still shows the old one -> a store-level copy exists; delete it or write per store.
- New filterable attribute absent from layered navigation -> `catalogsearch_fulltext` not reindexed or not in the attribute set.
- Product 404 after import -> `url_rewrite` not generated for that store.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-admin/catalog/products/product-create
- https://experienceleague.adobe.com/en/docs/commerce-admin/catalog/products/pricing/catalog-price-scope
- https://developer.adobe.com/commerce/php/development/components/attributes/
- https://developer.adobe.com/commerce/php/development/components/inventory-management/
- https://experienceleague.adobe.com/en/docs/commerce-admin/b2b/shared-catalogs/catalog-shared
- https://developer.adobe.com/commerce/webapi/rest/b2b/
