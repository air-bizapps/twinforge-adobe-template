---
name: adobe-magento-performance-analyst
description: "Performance rules for Magento 2.4.8 code: measuring (profiler, query log, APM, realistic fixtures), collection usage, queries in loops, around plugins, Proxy, hot-path observers, indexers by schedule. Use when writing code on storefront pages, listings, checkout, observers or plugins, and when a page or command is slow. Caching, tags and stale pages: adobe-magento-cache-analyst."
---

# Magento performance

Most slowness in custom code comes from uncacheable pages, queries in loops and heavy work on hot
paths. Measure before and after; don't optimize by guess. Page cache, tags, GraphQL cache and Redis:
`adobe-magento-cache-analyst`. Browser-side metrics and assets: `adobe-magento-frontend-standards`.

## Rules

**Measuring**
- Measure in `production` mode with every cache enabled, as the site runs. Developer mode generates
  code and static files on demand and inflates every number.
- Use the tool that answers the question: the built-in profiler for timers on one HTML request; the
  query log for query count and slow queries; a trace profiler (Blackfire, Tideways, Xdebug profiler)
  for which function costs; APM (`Magento_NewRelicReporting` with New Relic) for production trends.
- Measure server time (TTFB) on a cache miss and a hit separately. A slow hit is infrastructure; a slow
  miss is code.
- Test on catalog and customer volumes close to production:
  `bin/magento setup:performance:generate-fixtures setup/performance-toolkit/profiles/ce/small.xml`
  (`ee` and `b2b` profiles exist). It writes products, customers and orders, so run it only on a
  throwaway database.

**Queries**
- No `load()`, `getById()` or repository call inside a loop: collect the ids, load once with an `in`
  filter.
- Select only the needed columns (`addFieldToSelect`, `addAttributeToSelect` with a list, never `*`)
  and page with `setPageSize()`/`setCurPage()`; batch jobs walk page by page. Lists exposed by API
  get a maximum page size.
- Totals with `getSize()` (a COUNT query), not `count()`, which loads every row.
- A filter or join on a custom column gets an index in `db_schema.xml`; confirm with `EXPLAIN` that
  the query uses it.

**Code on hot paths**
- `before`/`after` plugins; `around` only to skip or wrap the original call. It adds a stack frame to
  every call and the plugin runs for every product of a listing (`adobe-create-plugin`).
- A costly dependency that a path rarely uses is injected as a Proxy in `di.xml`
  (`Vendor\Module\Model\Heavy\Proxy`); never name `\Proxy` in the PHP constructor. CLI commands are
  all built on every `bin/magento` call: their repositories go behind a Proxy (`magento-example cli`).
- Observers on frequent events (`controller_action_predispatch`, `layout_load_before`, model load
  events) exit early when they don't apply.
- External HTTP calls stay out of page rendering and order placement: publish to a queue
  (`adobe-create-queue`), or call with a short timeout and cache the answer (`adobe-create-http-client`).

**Indexers**
- Indexers run "Update by Schedule" (`bin/magento indexer:set-mode schedule`); "Update on Save"
  reindexes inside the admin save or import request. A custom indexer declares `mview.xml` so schedule
  mode works; code never calls `reindexAll()` in a request. Declaring indexers:
  `adobe-magento-backend-standards`.
- Schedule mode depends on cron (`indexer_update_all_views`): a growing changelog backlog means cron
  is not running or a view is stuck.

## Steps

1. Reproduce the slow request in production mode and measure (profiler, query log, trace).
2. Find the dominant cost: uncacheable page, query count, one slow query, a plugin, an observer or an
   external call.
3. Apply the rule above, then measure the same request again; put both numbers in the report.

## Verify

```bash
curl -s -o /dev/null -w '%{time_starttransfer}\n' "$BASE_URL/page"   # TTFB, miss then hit
bin/magento dev:profiler:enable html          # timers at the page bottom; HTML requests only
bin/magento dev:profiler:disable
bin/magento dev:query-log:enable --include-all-queries=true --query-time-threshold=0.05 --include-call-stack=true
bin/magento dev:query-log:disable             # log in var/debug/db.log
bin/magento indexer:show-mode
bin/magento indexer:status                    # schedule backlog per indexer
```

`MAGE_PROFILER=html` in the web server environment does the same as the profiler command. Is the page
cacheable at all: `adobe-magento-cache-analyst` Verify.

## Pitfalls

- Profiling in developer mode -> numbers dominated by code generation; switch to production first.
- Query log left on -> `var/debug/db.log` grows with every request; disable it.
- Fixtures generated on a shared database -> thousands of fake products and customers; use a copy.
- Indexers back on "Update on Save" after a deploy script -> admin saves and imports slow down;
  check `indexer:show-mode`.
- An optimization reported without before/after numbers -> not proven; measure the same request twice.

## Docs

- https://developer.adobe.com/commerce/php/development/components/plugins
- https://developer.adobe.com/commerce/php/development/components/proxies
- https://developer.adobe.com/commerce/php/development/components/indexing/optimization
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/setup/mage-profiler
- https://experienceleague.adobe.com/en/docs/commerce-operations/performance-best-practices/overview
- https://experienceleague.adobe.com/en/docs/commerce-operations/performance-best-practices/development-environment
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/setup/fixtures
