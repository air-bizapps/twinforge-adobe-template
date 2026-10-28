---
name: adobe-magento-api-developer
description: "Design and build a Magento 2.4.8 REST or GraphQL API end to end: service contract shape, store scope in the URL, limits and paging, async and bulk REST, batch resolvers, extension attributes, integration auth, and how to prove it with curl. Use when adding or changing an endpoint or resolver. Base rules (ACL, docblocks, GraphQL cache): adobe-magento-backend-standards; repository scaffold: adobe-create-repository."
---

# Magento API development

Decisions and traps when exposing data through REST or GraphQL. The base rules (`webapi.xml` ACL,
`self`/`anonymous`, docblock types, GraphQL authorization, `@cache`) are in
`adobe-magento-backend-standards` §REST and GraphQL; exception to HTTP status mapping in
`adobe-commerce-standards` §REST API; per-surface security in `adobe-magento-security-analyst`.
This skill does not repeat them.

## Rules

**Contract**
- The endpoint binds to an `Api/*Interface` method, never to a model or controller: REST, GraphQL
  and internal callers then share one validated path. Scaffold: `adobe-create-repository`; working
  files: `php "$T/magento-example.php" service-contract` (and `rest`, `graphql`), `$T` being the
  scripts folder of `adobe-magento-toolbox`.
- Add fields to a core entity's API (order, customer, product) with `etc/extension_attributes.xml`
  and an `afterGet`/`afterGetList` plugin on its repository (`magento-example extension-attribute`).
  Never edit a core `Api/Data` interface.
- A list method takes `SearchCriteriaInterface` and returns a typed `*SearchResultsInterface`.
  Ad hoc `getByX(array $filters)` methods break the framework's filter, sort and paging handling.
- An external system that creates records sends its own key (SAP code, external id) and the service
  upserts by it. Middleware retries a POST after a timeout; without a natural key that becomes a
  duplicate.

**Scope and limits**
- The store code in the URL sets the scope of the write: `/rest/<store_code>/V1/...` saves at that
  store view, `/rest/all/V1/...` at the global level. A product PUT through `/rest/default/V1`
  writes store-view values that later shadow admin edits on the default scope.
- GraphQL picks the store from the `Store: <store_code>` header; without it the default store view.
- Web API input limits (`webapi/validation/input_limit_enabled`) are off by default, so
  `searchCriteria[pageSize]` is uncapped. Either enable them (maximum page size then defaults to 300)
  or cap the page size inside the service.
- GraphQL caps query depth (20), complexity (1000) and aliases (10): flatten a schema that hits
  them instead of raising the limits.

**Large or slow work**
- Imports from an external system go to the async endpoints of the same route:
  `POST /rest/<store>/async/V1/<route>` (one item) or `/rest/<store>/async/bulk/V1/<route>` (array).
  The answer is a `bulk_uuid`; progress at `GET /rest/V1/bulk/<uuid>/status`. The work runs in the
  consumer `async.operations.all`, so confirm consumers run in that environment
  (`adobe-create-queue`). GET routes have no async form.
- A GraphQL field resolved per parent item (one query per product in a list) implements
  `Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface`, or
  `BatchServiceContractResolverInterface` when a service already loads many ids at once. Model:
  `Magento\CatalogGraphQl\Model\Resolver\Product\BatchProductLinks`.

**Auth and wiring**
- Customer tokens last 1 h, admin tokens 4 h (`oauth/access_token_lifetime/*`): clients request a
  new token instead of the lifetime being raised.
- Preferences or plugins only for the API go in `etc/webapi_rest/di.xml` or `etc/graphql/di.xml`;
  in global `etc/di.xml` they also change admin and storefront.

## Steps

1. Write the request and response JSON (or GraphQL query) first and agree it with the consumer
   (frontend, SAP, middleware) before coding (`projeto-engineering-practices`).
2. Define or reuse the service contract; implement through the repository/service.
3. Declare the route or schema with its ACL or resolver authorization.
4. Decide store scope, page limit, sync or async, and batch resolution; write each in the report.
5. Prove with the calls below, then run `magento-verify`.

## Verify

```bash
TOKEN=$(curl -s -X POST "$BASE_URL/rest/V1/integration/admin/token" -H 'Content-Type: application/json' \
  -d '{"username":"'"$ADMIN_USER"'","password":"'"$ADMIN_PASS"'"}' | tr -d '"')
curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/rest/all/V1/<route>?searchCriteria[pageSize]=5"
curl -s -o /dev/null -w '%{http_code}\n' "$BASE_URL/rest/V1/<route>"            # no token: 401
curl -s "$BASE_URL/rest/default/schema?services=all" | grep -c '<route>'          # route is in the schema
curl -s -X POST "$BASE_URL/graphql" -H 'Content-Type: application/json' -H 'Store: default' \
  -d '{"query":"{ <field> { items { id } } }"}'
php "$T/magento-smoke.php"     # with a database: builds the classes, calls the webapi.xml routes
php "$T/magento-verify.php"    # before done
```

## Pitfalls

- Getter returns data but the JSON lacks the field → the `@return` type is missing or short; see
  `WEBAPI-DOCBLOCK` in `adobe-magento-backend-standards`.
- Partial PUT erases fields → merge with `addData()` (`adobe-commerce-standards`).
- List endpoint returns only `id` → wrong `SearchResultInterface` type (`adobe-commerce-standards`).
- Async call accepted but nothing changes → the consumer is not running; check `bin/magento
  queue:consumers:list` and the bulk status.
- GraphQL list slow with many items → a per-item resolver; switch to a batch resolver and count
  queries with `dev:query-log:enable` (`adobe-magento-performance-analyst`).

## Docs

- https://developer.adobe.com/commerce/php/development/components/service-contracts/
- https://developer.adobe.com/commerce/webapi/rest/
- https://developer.adobe.com/commerce/webapi/rest/use-rest/asynchronous-web-endpoints/
- https://developer.adobe.com/commerce/webapi/rest/use-rest/bulk-endpoints/
- https://developer.adobe.com/commerce/webapi/get-started/api-security/
- https://developer.adobe.com/commerce/webapi/graphql/develop/
- https://developer.adobe.com/commerce/webapi/get-started/authentication/
