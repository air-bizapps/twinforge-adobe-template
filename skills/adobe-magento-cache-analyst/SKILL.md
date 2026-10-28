---
name: adobe-magento-cache-analyst
description: "Magento 2.4.8 caching: full page cache (Varnish, ESI), private content, cache tags and identities, GraphQL @cache, custom cache types, block cache, Redis/Valkey and L2 cache. Use when a page is not cached, shows stale or another customer's data, or code caches data. Slow code or queries: adobe-magento-performance-analyst."
---

# Magento caching

Where Magento caches, how entries are tagged and purged, and how custom code stays cacheable. When to
run `cache:clean` versus `cache:flush` after a change: `adobe-commerce-standards` (Deploy/cache lifecycle).
Query counts, plugins and profiling: `adobe-magento-performance-analyst`.

## Rules

**Full page cache**
- Never put `cacheable="false"` on a block: one such block makes the whole page uncacheable, and in
  `default.xml` every page. Per-customer content goes to a customer-data section: a
  `Magento\Customer\CustomerData\SectionSourceInterface` registered in the `sectionSourceMap` of
  `Magento\Customer\CustomerData\SectionPoolInterface` (`etc/frontend/di.xml`), invalidated by the
  actions in `etc/frontend/sections.xml`, read in JS with `Magento_Customer/js/customer-data`. Model:
  `Magento\Checkout\CustomerData\Cart`.
- Content that varies by a segment (not by user) beyond store, currency, customer group and B2B
  company goes into `Magento\Framework\App\Http\Context`, so the cache varies by it. Otherwise one
  segment sees the page cached for another.
- A model shown on the storefront implements `Magento\Framework\DataObject\IdentityInterface`, and the
  block rendering it returns the same tags. Saving through the resource model then purges the pages
  and GraphQL answers that carry them.
- Writes that bypass the model (direct SQL, import) clean the tags themselves: register ids in
  `Magento\Framework\Indexer\CacheContext` and dispatch `clean_cache_by_tags` with it. Model:
  `Magento\Catalog\Plugin\Model\Product\Action\UpdateAttributesFlushCache`.
- Production runs Varnish (`caching_application` = 2; built-in = 1 for development). Purges reach it
  only with `--http-cache-hosts=<host:port>` in `env.php` and the app host in the VCL purge ACL.
- Regenerate the VCL after an upgrade (`bin/magento varnish:vcl:generate --export-version=7`; 6 also
  exists): it carries that release's purge and cookie rules.
- A layout block with a `ttl` attribute becomes an ESI include under Varnish, with its own lifetime:
  for fragments fresher than the page, never for private content.

**GraphQL**
- Public queries declare `@cache(cacheIdentity: "...")` with an identity class returning the model's
  tags; customer, cart or price-per-customer queries declare `@cache(cacheable: false)`. Only GET
  requests are cached, and one uncached query in the request bypasses the cache for all of it.
  Pattern: `magento-example graphql`.

**Application cache**
- Data your code caches goes in its own cache type: `etc/cache.xml` plus a class extending
  `Magento\Framework\Cache\Frontend\Decorator\TagScope` with `TYPE_IDENTIFIER` and `CACHE_TAG`. It then
  shows in `cache:status` and can be cleaned alone.
- Save with the type tag plus the entity tags, so an entity save purges it; serialize with
  `SerializerInterface` (JSON), never PHP `serialize()`.
- A block cached in `block_html` sets `cache_lifetime` and returns from `getCacheKeyInfo()` everything
  it varies by (store, customer group, currency, arguments). A missing key part serves one store's
  HTML to another.
- Code that changes config cleans only the affected type (`TypeListInterface::cleanType()` /
  `invalidate()`), not a full flush.

**Backends**
- Use Redis or Valkey for the `default` and `page_cache` frontends, on separate databases, and sessions
  on another database or instance. Set them with `setup:config:set --cache-backend=redis
  --cache-backend-redis-server= --cache-backend-redis-db= --page-cache=redis --page-cache-redis-db=`;
  this writes `Magento\Framework\Cache\Backend\Redis`, which adds `preload_keys` to `Cm_Cache_Backend_Redis`.
- Installs sharing one Redis need their own `--cache-id-prefix` and `--page-cache-id-prefix`.
- On several web nodes, use the L2 cache: `Magento\Framework\Cache\Backend\RemoteSynchronizedCache` with
  Redis as `remote_backend` and a local file backend, plus `use_stale_cache` and
  `--allow-parallel-generation` to avoid stampedes after a clean.
- Magento has no built-in cache warmer; on Cloud use `WARM_UP_PAGES` in `.magento.env.yaml`.

## Steps

1. Find the layer: page, GraphQL, block or custom type, backend.
2. Check tags (what purges it) and keys (what it varies by).
3. Fix, then test with every cache enabled.

## Verify

```bash
curl -s -o /dev/null -D - "$BASE_URL/page" | grep -i '^cache-control\|^x-magento\|^age'   # twice
bin/magento cache:status
bin/magento config:show system/full_page_cache/caching_application
redis-cli -n <db> info stats | grep keyspace_   # hits versus misses
varnishlog -g request -q 'ReqMethod eq "PURGE"'  # purges arriving on save
```

Built-in cache in developer mode: the second request shows `X-Magento-Cache-Debug: HIT` and
`X-Magento-Tags`. `cache-control: no-store, no-cache` means not cacheable.

## Pitfalls

- Customer name or id is null in a block only with the cache on -> cacheable pages clear the customer
  session after layout generation; use a customer-data section.
- Data changed, storefront stale -> the save bypassed the model or the identities don't match the page tags.
- GraphQL query never cached -> sent by POST, no `cacheIdentity`, or empty identities.
- Varnish never purges -> `http_cache_hosts` missing or the app host outside the VCL `purge` ACL.

## Docs

- https://developer.adobe.com/commerce/php/development/cache/page/
- https://developer.adobe.com/commerce/php/development/cache/page/private-content
- https://developer.adobe.com/commerce/php/development/cache/partial/cache-type/
- https://developer.adobe.com/commerce/webapi/graphql/usage/caching/
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cache/config-varnish
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cache/redis/config-redis
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cache/level-two-cache
