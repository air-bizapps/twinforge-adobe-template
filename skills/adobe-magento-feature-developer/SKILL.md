---
name: adobe-magento-feature-developer
description: "Delivery sequence for a Magento 2.4.8 feature: map what exists, plan the layers, build bottom-up with one service owning the logic, decide scope, cache, async work and toggles, then report deploy steps. Use when turning a requirement into working code across several layers. The layer checklist itself: adobe-commerce-standards (Feature completeness); new module skeleton: adobe-magento-module-developer."
---

# Magento feature developer

How to take a requirement to a working, deployable feature. The table of layers and the skill for
each one is in `adobe-commerce-standards`, "Feature completeness"; this skill is the order and the
decisions between them. Project business rules come from the project's domain skill (Bunge:
`projeto-domain-lookup`). `$T` is the `scripts/` folder of `adobe-magento-toolbox`.

## Rules

- **Map before you write.** Trace each page or endpoint the feature touches
  (`php "$T/magento-trace.php" <url>`), find the module that owns the domain, and read the core class
  you will extend (`php "$T/magento-api.php" '<FQCN>'`). Most "new" features are a plugin or a field on
  something that exists.
- **Write the layer plan first.** List every row of the Feature completeness table as "build" or "not
  needed because ...". A missing layer compiles and fails only when someone uses it.
- **One service owns the business rule**, behind an `Api/` interface. Controllers, resolvers, REST
  routes, cron, consumers and plugins call it and stay thin; the rule is tested and changed in one
  place.
- **Build bottom-up and compile each layer** before the next: data, contract, service, exposure
  (REST/GraphQL, admin, storefront), config, tests. An error in a lower layer cascades into every
  layer above it.
- **Decide the scope of every value** (global, website, store view) and read it with that scope
  (`adobe-create-config`). B2B and multi-website stores break features that only read the default.
- **Toggle what the business may switch off**: an enable field in `system.xml` at website scope,
  checked in the service, not in the template.
- **Keep slow or fallible work off the request**: external calls and e-mail on save or order paths go
  to a queue (`adobe-create-queue`) or run after commit (`addCommitCallback`,
  `adobe-magento-model-developer`); HTTP clients get a timeout (`adobe-create-http-client`).
- **Plan cache invalidation with the data**: a value shown on a cached page or GraphQL query needs
  cache tags cleaned on save, and per-customer data never goes into a cached block
  (`adobe-magento-cache-analyst`).
- **Name the indexers the feature affects** (price, stock, category, search) and what triggers them
  (`adobe-magento-backend-standards`, Indexers).
- **Log through a dedicated channel** (`adobe-inject-custom-logger`), never secrets or personal data.

## Steps

1. Read the requirement and the business rules; list open questions instead of guessing.
2. Map existing code (Rules, first item) and choose extension points (`adobe-commerce-standards`).
3. Write the layer plan with one line per layer.
4. Data: `adobe-create-entity`, then `setup:upgrade`.
5. Contract and service: `adobe-create-repository`; the business service behind its own interface.
6. Exposure, each with its skill: REST/GraphQL (`adobe-magento-backend-standards`), admin
   (`adobe-create-controller`, `adobe-magento-ui-component-developer`), storefront
   (`adobe-magento-frontend-standards`).
7. Config, ACL and toggles: `adobe-create-config`.
8. Async and scheduled work: `adobe-create-queue`, `adobe-create-cronjob`.
9. Unit tests for the service: `adobe-create-unit-tests`.
10. Run `php "$T/magento-verify.php"` until `VERIFY OK`, then exercise the feature end to end.
11. Report the deploy steps: `setup:upgrade`, `setup:di:compile`, static content, reindex, cache types
    to clean, consumers to restart (`adobe-commerce-standards`, Deploy/cache lifecycle).

## Verify

- `php "$T/magento-verify.php"` ends in `VERIFY OK`; with a database, `php "$T/magento-smoke.php"`
  calls the new `webapi.xml` routes.
- REST: `curl -H "Authorization: Bearer <token>" https://<host>/rest/V1/<route>` returns the
  documented shape and status.
- Admin: open the grid, create, edit and delete a record; storefront: open the page in a second store
  view or website.
- Async: `bin/magento queue:consumers:list` shows the consumer; `bin/magento cron:run` processes the job.

## Pitfalls

- Works only on the default store -> value read without scope; read it with the store or website id.
- Same rule copied into controller and resolver -> move it into the service and call it from both.
- Fine in developer mode, broken in production -> no `setup:di:compile` or static deploy, or XML that
  production does not validate (`adobe-magento-xml-specialist`).
- Admin save shows success but writes nothing -> `adobe-commerce-standards`, UI Component admin form.
- Stale value on the storefront after save -> cache tags not cleaned (`adobe-magento-cache-analyst`).

## Docs

- https://developer.adobe.com/commerce/php/best-practices/extensions/
- https://developer.adobe.com/commerce/php/development/components/service-contracts/
- https://developer.adobe.com/commerce/php/development/components/message-queues/
- https://developer.adobe.com/commerce/php/development/cache/
