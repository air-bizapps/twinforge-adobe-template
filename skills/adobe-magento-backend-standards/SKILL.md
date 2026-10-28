---
name: adobe-magento-backend-standards
description: "Magento 2 / Adobe Commerce backend-specific conventions: repositories and service contracts, REST/GraphQL API extension (ACL, token auth, GraphQL cache), indexers (save vs schedule, mview, OpenSearch), cron, message queues, and admin-side data. Use for backend (PHP application/data layer) work. Builds on `adobe-commerce-standards` (shared base) — read that first; this skill only covers what's specific to backend work."
---

# Magento Backend Standards

Backend-specific depth on top of the shared `adobe-commerce-standards` base
(extension points, DI, module structure, schema, testing layers, deploy
lifecycle — read that skill first, this one doesn't repeat it).

## Repositories and service contracts

- Public API surface: `Api/Data/*Interface` (data objects) and
  `Api/*RepositoryInterface` (CRUD/search) — these are the stable contract
  other modules and the frontend/API layer depend on. Changing a method
  signature here is a breaking change (see `adobe-commerce-standards` on
  backward compatibility).
- Repository implementations live in `Model/*Repository`, backed by
  `Model\ResourceModel` for persistence. Do not put business logic in the
  repository itself — that belongs in a dedicated service class the
  repository or a plugin calls.
- Use `SearchCriteria` + `SearchResultsInterface` for list/search endpoints
  rather than ad hoc query methods — it's what the framework's grids,
  GraphQL resolvers, and REST list endpoints expect.

## REST and GraphQL API extension

Working examples: `magento-example rest` and `magento-example graphql` (skill
`adobe-magento-toolbox`).

- REST: declare routes in `etc/webapi.xml`, binding to a
  `*RepositoryInterface`/service method. ACL resource in `etc/acl.xml`
  controls who can call it.
- Every method exposed in `webapi.xml`, and every getter/setter of the
  `Api\Data` interfaces it uses, needs `@param` for each parameter and
  `@return`, with full class names (`\Vendor\Module\Api\Data\ItemInterface[]`):
  the Web API reads types from the docblock at runtime, and compile does not
  catch it (`magento-verify`: `WEBAPI-DOCBLOCK`).
- `<resource ref>`: an `acl.xml` id for admin and integration tokens; `self`
  for the logged-in customer, with the id forced from the token
  (`<data><parameter name="customerId" force="true">%customer_id%</parameter></data>`),
  never read from the URL or body; `anonymous` skips token and ACL, so only
  when the business rule requires access without login, finding the record
  by something unguessable (masked id as in core `guest-carts`, token
  compared by hash), never by a sequential id. Default and existing debt:
  `adobe-create-repository`, `bunge-backend-code-quality`.
- Tokens go in `Authorization: Bearer`, from `POST /V1/integration/customer/token`
  or `/V1/integration/admin/token`. An external system (SAP, middleware) gets
  an Integration with only its routes' resources, never an admin user's
  token (expires in 4 h, carries the admin's whole role); the integration
  token works as Bearer only with `oauth/consumer/enable_integration_as_bearer`
  = 1 (off by default: 401).
- GraphQL: schema changes in `etc/schema.graphqls`; resolvers implement
  `ResolverInterface`. Keep resolvers thin — delegate to a repository or
  service, don't put business logic directly in the resolver.
- The GraphQL schema has no ACL, so the resolver authorizes: customer data
  through `$context->getExtensionAttributes()->getIsCustomer()` and
  `$context->getUserId()` (never a `customer_id` argument), admin or
  integration mutations through `AuthorizationInterface::isAllowed('<acl id>')`.
  Errors meant for the client are `GraphQlAuthorizationException`,
  `GraphQlInputException`, `GraphQlNoSuchEntityException`; anything else
  arrives as "Internal server error".
- GraphQL cache: a GET response is cached only with the tags of
  `@cache(cacheIdentity: ...)`, whose `IdentityInterface` returns the tags
  the model's `getIdentities()` cleans on save (or the answer goes stale). A
  field without `@cache` is cached along with the cacheable fields of the
  same request, so customer-specific data (customer price, cart) declares
  `@cache(cacheable: false)`.
- When a frontend need turns out to require a new/changed API field, that's
  exactly the cross-domain case in `projeto-engineering-practices` — confirm
  the exact shape needed with the Frontend Developer before finalizing the
  schema, not after.

## Indexers

- Declare in `etc/indexer.xml`. Know the difference between full reindex
  and partial (`mview`-based) — most storefront-facing data (catalog,
  price) uses `mview` for near-real-time updates; changing indexer mode or
  forcing full reindexes on a live store is a production-risk action, flag
  it per `projeto-engineering-practices` safety rules.
- Modes (`bin/magento indexer:show-mode`): Update on Save reindexes in the
  request that saves the model; Update by Schedule has MySQL triggers fill
  `<view>_cl` changelog tables, applied every minute by the
  `indexer_update_all_views` cron job (group `index`): with cron down, the
  backlog in `indexer:status` only grows. `indexer:set-mode` rebuilds the
  triggers (production risk, as above).
- Writes that skip the model's save (direct SQL, bulk imports) reach the
  index only through the triggers: call the indexer processor's
  `reindexList($ids)` (`Magento\Framework\Indexer\AbstractProcessor`), which
  reindexes in save mode and does nothing in schedule mode.
- Custom indexer: `etc/indexer.xml` + `etc/mview.xml` (subscribed tables) and
  one class implementing `Magento\Framework\Indexer\ActionInterface` and
  `Magento\Framework\Mview\ActionInterface`.
- Search runs on OpenSearch (`catalog/search/engine` = `opensearch`,
  connection in `catalog/search/opensearch_*`, usually `env.php`); 2.4.8 has
  no `elasticsearch7`, and `Magento\Elasticsearch\` classes are the base the
  OpenSearch module reuses. A patch that changes an attribute's searchable,
  filterable or sortable flags needs `indexer:reindex catalogsearch_fulltext`
  after deploy.
- After a change that affects indexed data, state in your task update which
  indexer(s) are affected and whether you reindexed (`bin/magento
  indexer:reindex <indexer>`) or it will pick up via `mview` on its own.

## Cron

- Declare jobs in `etc/crontab.xml`, grouped by `etc/cron_groups.xml` job
  group when timing/isolation matters. Never add a job to the default group
  without checking whether it needs its own group (long-running jobs
  blocking the default group's schedule is a common real-world Magento
  incident). How to build the job and when it deserves its own group:
  `adobe-create-cronjob`.

## Message queues

- If Bunge's environment uses RabbitMQ (confirm with the DevOps Engineer —
  don't assume it's provisioned), queues are declared in
  `etc/communication.xml` (topics), `etc/queue_consumer.xml` (consumers),
  and `etc/queue_publisher.xml` (publishers). Prefer async processing over
  synchronous work for anything that doesn't need an immediate response
  (e.g., sending confirmation emails, syncing to an external system).

## Admin backend — UI Components (grids and forms)

Source: Adobe Commerce official developer docs (`developer.adobe.com/commerce/php/development/components/add-admin-grid`, checked 2026-09-03). This is the standard recipe for a new admin CRUD area — follow it, don't hand-roll a query-driven grid.

**File layout for a grid** (module `Vendor_Module`, entity `Entity`):

```
etc/adminhtml/routes.xml           # admin route id + front name
etc/adminhtml/menu.xml             # menu item, parent, ACL resource, action path
etc/acl.xml                        # ACL resource tree (menu, then child actions)
etc/di.xml                         # DataProvider virtualType wiring (below)
view/adminhtml/layout/<route>_index_index.xml
view/adminhtml/ui_component/<module>_<entity>_listing.xml
Ui/DataProvider/<Entity>/ListingDataProvider.php
Ui/DataProvider/<Entity>/Listing/Collection.php
Controller/Adminhtml/Index/Index.php
Controller/Adminhtml/<Entity>/MassDelete.php   # if mass actions are needed
```

**DataProvider wiring (`di.xml`)** — the part that's easy to get wrong: the UI component's `provider` attribute (`<module>_<entity>_listing.<module>_<entity>_listing_data_source`) must match a `CollectionFactory` argument entry, which maps to a `virtualType` collection class with its own `mainTable`/`resourceModel`:

```xml
<type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
  <arguments>
    <argument name="collections" xsi:type="array">
      <item name="<module>_<entity>_listing_data_source" xsi:type="string">VendorModuleEntityCollection</item>
    </argument>
  </arguments>
</type>
<virtualType name="VendorModuleEntityCollection" type="Vendor\Module\Ui\DataProvider\Entity\Listing\Collection">
  <arguments>
    <argument name="mainTable" xsi:type="string">vendor_module_entity</argument>
    <argument name="resourceModel" xsi:type="string">Vendor\Module\Model\ResourceModel\Entity</argument>
  </arguments>
</virtualType>
```

**Class hierarchy:**
- Listing `ListingDataProvider` extends `Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider`.
- Listing `Collection` extends `Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult` (override `_initSelect()` + `addFilterToMap()` only if you need to filter/sort on a joined column not on the main table).
- A read-only/consultation-only grid (no create/edit/delete) is the *same* listing recipe — the "read-only" part is enforced by simply not adding a form UI component, edit controller, or mass-delete controller for it, and not exposing action buttons in the listing's `<actionsColumn>`. There's no separate "read-only grid" base class — the grid is always just a query view; write capability only exists if you build the write path.
- Form `DataProvider` implements `Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface`, typically by extending `Magento\Ui\DataProvider\ModifierPoolDataProvider` (which itself extends `Magento\Ui\DataProvider\AbstractDataProvider`) when the form needs pluggable modifiers; plain `AbstractDataProvider` is enough otherwise.
- Column with custom actions (edit/delete links) extends `Magento\Ui\Component\Listing\Columns\Column`, implements `prepareDataSource()`, gets a `UrlInterface` constructor argument from the UI component XML to build action URLs.

**Listing XML (`<listing>` root) — three sections:**
1. `dataSource` — points at the wired data source above.
2. `listingToolbar` — `<massaction>`, `<filters>`, `<paging>`, `<bookmark>`, `<columnsControls>`.
3. `columns` — `<selectionsColumn>` (checkboxes, only if mass actions exist), the data columns (`<settings>`: label, filter type, width), `<actionsColumn>` (only if the row is editable/deletable).

**Admin controllers:**
- List page controller: implements `HttpGetActionInterface`, extends `Magento\Backend\App\Action`, returns `Magento\Framework\View\Result\Page` via `PageFactory`.
- `MassDelete`/write controllers: implement `HttpPostActionInterface`, declare `const ADMIN_RESOURCE = 'Vendor_Module::specific_resource'` (never reuse an unrelated existing resource just to save a step), use `Filter::getCollection()` for the selected rows, return a redirect via `ResultFactory::TYPE_REDIRECT`.
- ACL resources form a tree (`etc/acl.xml`) rooted under the module's menu resource, with child resources per action/column/button so admin roles can be scoped per-capability, not just per-module.

## Backend testing

- Repository/service logic: unit test the service class with the
  repository mocked, not the other way around.
- API contracts: integration or webapi functional tests
  (`dev/tests/api-functional`) when the behavior genuinely depends on the
  HTTP/GraphQL layer itself, not just the underlying PHP logic.
