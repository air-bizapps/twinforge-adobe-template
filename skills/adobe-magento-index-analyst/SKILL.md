---
name: adobe-magento-index-analyst
description: "Diagnose and tune Magento 2.4.8 indexing: indexer states (invalid, working, suspended), changelog backlog, slow or stuck reindex, price dimensions and threads, OpenSearch indices and aliases, products missing from search, and database indexes on custom tables. Use when data is stale on the storefront, a reindex is slow or stuck, or search results are wrong. Declaring an indexer and the modes: adobe-magento-backend-standards."
---

# Magento indexing and search

Read the index state before reindexing, and prove what the storefront reads. Modes, mview
triggers, `reindexList()`, the custom indexer contract and the OpenSearch engine setting are in
`adobe-magento-backend-standards` §Indexers; the schedule-mode rule in
`adobe-magento-performance-analyst`. This skill does not repeat them.

## Rules

**States**
- `indexer:status` shows Status (`valid`, `invalid`, `working`, `suspended`), Update On and, in
  schedule mode, `idle (N in backlog)` for the changelog not yet applied.
- `invalid` is not passive: cron job `indexer_reindex_all_invalid` (group `index`, every minute)
  runs a full reindex of it. `indexer:reset` therefore schedules a full rebuild on a live store;
  treat it as the production-risk action in `projeto-engineering-practices`.
- Invalidating an indexer also invalidates the ones declared to run after it (`<dependencies>` in
  `indexer.xml`), so one reset can rebuild several.
- `working` with no reindex process alive means a killed run: `indexer:reset <id>` and reindex that
  one indexer.
- Big imports or mass updates: `indexer:set-status suspended <id>` first. Cron then skips both its
  full reindex and its changelog (and those of indexers with the same `shared_index`); the
  triggers keep recording ids. `indexer:set-status valid <id>`
  afterwards and the backlog is applied in batches of 1000.
- `customer_grid` has no real changelog (view `customer_dummy`): in schedule mode the admin customer
  grid goes stale until `indexer:reindex customer_grid`.

**Reindex time**
- Price and search are split by dimension and run in parallel with `MAGE_INDEXER_THREADS_COUNT=<n>
  bin/magento indexer:reindex <id>`.
- Price dimensions (`indexer:set-dimensions-mode catalog_product_price <mode>`: `none`, `website`,
  `customer_group`, `website_and_customer_group`) split the price tables. B2B multiplies rows: each
  shared catalog adds a customer group, so rows grow by products × websites × groups. Changing the
  mode needs a full price reindex: plan it.
- Batch sizes live in `env.php` under `indexer/batch_size/<indexer_id>`
  (`catalogsearch_fulltext/mysql_get`, `elastic_save`, ...). Lower them when a reindex runs out of
  memory, rather than raising `memory_limit` without end.

**OpenSearch**
- Each store view has index `<prefix>_product_<store_id>_v<N>` behind alias
  `<prefix>_product_<store_id>`. A full reindex builds a new `_v`, moves the alias and deletes the
  old one; partial updates write to the current one. The storefront reads the alias.
- `catalog/search/opensearch_index_prefix` is unique per environment sharing a cluster; two
  environments with the same prefix overwrite each other's catalog.
- The engine is `opensearch`. 2.4.8 has no `elasticsearch7`; `elasticsearch8` still loads but is
  deprecated and not the target. `setup:upgrade` validates the engine connection and fails when
  OpenSearch is unreachable.
- With B2B shared catalog or catalog permissions on, what a customer may see comes from
  `catalogpermissions_category` and `catalogpermissions_product`; keep them valid with search.

**Database indexes (custom tables)**
- Index the columns your queries filter, join or sort on: `<index referenceId="..."
  indexType="btree">` in `db_schema.xml`, then `bin/magento setup:db-declaration:generate-whitelist
  --module-name=Vendor_Module`. Unique rules use a constraint (`adobe-commerce-standards` §Schema).
  Don't add indexes to core tables; they slow every core write.
- Prove with `EXPLAIN` on the real query captured by the query log
  (`adobe-magento-performance-analyst`): `key` must name your index, `rows` must drop.

## Steps

1. State the symptom: stale data, slow or stuck reindex, or wrong search result.
2. Read `indexer:status` and `mview_state` before changing anything.
3. For search, check the product itself (enabled, visibility includes search, website, stock with
   `cataloginventory/options/show_out_of_stock`) and then the document in OpenSearch.
4. Act on the single indexer involved; avoid full `indexer:reindex` on a live store.
5. Report which indexers you touched, in which mode, and whether you reindexed.

## Verify

```bash
bin/magento indexer:status
bin/magento indexer:show-mode
bin/magento indexer:show-dimensions-mode
mysql -e "SELECT view_id, mode, status, version_id, updated FROM mview_state"
bin/magento config:show catalog/search/engine
bin/magento config:show catalog/search/opensearch_index_prefix
curl -s "$OS_URL/_cat/aliases/<prefix>_product_*?v"
curl -s "$OS_URL/_cat/indices/<prefix>_product_*?v"
curl -s "$OS_URL/<prefix>_product_<store_id>/_doc/<product_id>"     # the product is indexed
curl -s "$OS_URL/<prefix>_product_<store_id>/_mapping" | head -c 2000
```

## Pitfalls

- Backlog grows and never empties → cron not running, or the indexer is `suspended`.
- Change visible only after a full reindex → write bypassed the model; call `reindexList()`
  (`adobe-magento-backend-standards`).
- Search fine in one environment, wrong in another → shared cluster with the same index prefix.
- Reindex killed by memory → smaller batch sizes and threads per dimension, not a bigger limit.
- Old `elasticsearch7_*` paths in `env.php` → ignored in 2.4.8; use `opensearch_*`.

## Docs

- https://developer.adobe.com/commerce/php/development/components/indexing/
- https://developer.adobe.com/commerce/php/development/components/indexing/custom-indexer/
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/manage-indexers
- https://experienceleague.adobe.com/en/docs/commerce-operations/performance-best-practices/configuration
- https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/prerequisites/search-engine/overview
