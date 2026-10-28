---
name: adobe-commerce-standards
description: "Magento 2 / Adobe Commerce engineering conventions: extension points, dependency injection, PHP 8.3, module structure and XML merging, feature completeness, declarative schema, API backward-compatibility, testing layers, and deploy/cache lifecycle. Use for any task that writes, reviews, or reasons about Magento 2 / Adobe Commerce PHP code. Client-agnostic — applies to any Magento 2 project, not just one company."
---

# Adobe Commerce / Magento 2 Standards

Framework-level conventions. This skill has no client-specific content —
company process (git, deploy, review routing) lives in a separate company
skill; personal tuning notes live in another. Keep this one reusable as-is
across any Magento 2 client project.

Full working files: `magento-example <topic>`; real signatures: `magento-api
'<FQCN>'`; check before done (XSD, schema URNs): `magento-verify` (skill
`adobe-magento-toolbox`).

## Extension points — prefer, in this order

1. **Plugin** (`etc/di.xml` `<type><plugin>`) for `before`/`after`/`around`
   behavior on a public method. Default choice for changing behavior without
   owning the class.
2. **Observer** (`etc/events.xml`) for reacting to a dispatched event without
   needing to alter a return value.
3. **Preference** (`etc/di.xml` `<preference>`) only when you must replace an
   entire class implementation (e.g. swapping a repository). One preference
   per interface company-wide — a second preference on the same type creates
   silent conflicts.
4. **Core file override / copy-paste-and-modify** — never. If a plugin or
   preference cannot do it, that is a signal to escalate, not to override.

Flag any preference in your task update: it is the extension point most
likely to conflict with another module or a future Adobe Commerce upgrade.

## Dependency injection

- Inject dependencies through the constructor only. Do not call
  `ObjectManager::getInstance()` directly outside of legacy compatibility
  shims (`Magento\Framework\App\ObjectManager`) — this is a well-known
  anti-pattern that hides real dependencies and breaks testability.
- Depend on interfaces (`Api/Data/*Interface`, `Api/*RepositoryInterface`),
  not concrete classes, so `di.xml` can swap implementations.
- Use `virtualType` in `di.xml` to configure a variant of a class without
  subclassing it.
- Factories (`*Factory`) are for creating data objects at runtime, not a
  substitute for proper DI of services.
- Area files (`etc/<area>/di.xml`, `etc/<area>/events.xml`; areas `frontend`,
  `adminhtml`, `webapi_rest`, `webapi_soap`, `graphql`, `crontab`) merge over
  the global `etc/` file and apply only in that area. A plugin or observer
  one area needs goes there.

## PHP 8.3 in Magento code

- Every new PHP file starts with `declare(strict_types=1);`. The database
  adapter, the request, and `ScopeConfigInterface::getValue()` return
  strings: cast before returning from a typed method, or it throws `TypeError`.
- Inject with promoted `private readonly` parameters and type your own
  properties, except a property the parent declares (see "Extending a Magento
  core class") and dependencies of a class Magento serializes (`AbstractModel`
  children, objects kept in session or cache): `AbstractModel::__sleep()`
  skips the child's private properties, which come back uninitialized.
- Backed enums stay inside your own logic. At Magento boundaries (`setData()`
  and DB columns, `Api/Data` types in REST or GraphQL, config, cache) pass
  `->value` and rebuild with `::from()`: the framework has no enum support, so
  an enum in `setData()` fails on save and REST outputs it as an empty object.

## Module structure

- `etc/module.xml` declares the module and its `<sequence>` (load-order
  dependencies) — get this right or `setup:upgrade` ordering breaks silently.
  List only modules whose tables, layout, or config this one extends; other
  dependencies go in `composer.json`. Omit `setup_version`: declarative
  schema and patches don't read it.
- XML config from all modules merges by node `name`/`id`, in `<sequence>`
  order: a `<plugin name>` or `<observer name>` on the same target, or an
  array `<item name>`, equal to another module's overrides it: prefix names
  with the module.
- `registration.php` registers the module path; required for autoloading and
  discovery.
- Public API surface: interfaces and classes annotated `@api` are a stable
  contract. Do not change their method signatures — add a new method or a
  new interface instead, and deprecate the old one with a version note.
- Data layer: `Model` (business object) → `Model\ResourceModel` (single-row
  DB access) → `Model\ResourceModel\Collection` (multi-row/query access).
  Do not query the database directly from a `Model` or a `Block`.

## Feature completeness — every layer the feature touches

A missing layer compiles and fails only when used: the plan lists each layer
the feature touches, built or explained.

| Layer | Done when | Skill; `magento-example` topic |
|---|---|---|
| Data | `db_schema.xml` + whitelist; model, resource model, collection; seed data in a data patch | `adobe-create-entity`; `db-schema`, `model`, `data-patch` |
| Service contract | `Api/` and `Api/Data/` interfaces, `di.xml` preferences | `adobe-create-repository`; `service-contract` |
| API | `webapi.xml` route with a named ACL resource; `schema.graphqls` + resolver when the storefront reads it | `adobe-create-repository`; `rest`, `graphql` |
| Admin | route, controller with `ADMIN_RESOURCE`, menu, grid and form | `adobe-create-controller`, `adobe-magento-ui-component-developer`; `admin-controller`, `ui-listing`, `ui-form` |
| Config / ACL | `system.xml` field, its ACL resource, defaults in `config.xml` | `adobe-create-config`; `config` |
| Frontend | layout handle, ViewModel, escaped template, JS via `requirejs-config.js`, e-mail template in `email_templates.xml` | `adobe-magento-frontend-standards`; `frontend-page`, `viewmodel`, `js`, `email` |
| Tests | unit tests in that skill's scope | `adobe-create-unit-tests`; `unit-test` |

## Schema changes

- Use **declarative schema** (`etc/db_schema.xml`) for all new table/column
  changes. `InstallSchema`/`UpgradeSchema` PHP scripts are legacy — only
  touch them if the module already uses that pattern and migrating is out of
  scope for the task.
- Data migrations (seeding, backfills) still use `Setup\Patch\Data` patch
  classes, not the old `UpgradeData.php` pattern.
- After a schema change: `bin/magento setup:upgrade` (applies schema/data
  patches), then `setup:di:compile` (regenerates the DI compilation cache —
  required after adding/changing any class Magento's DI reads, including
  plugins, preferences, and virtual types).
- **A unique constraint is a `<constraint xsi:type="unique">`, never an
  `<index indexType="unique">`.** Declarative schema's `indexType` enum is
  only `fulltext`, `btree`, `hash` — `unique` is not a valid `indexType` and
  `setup:upgrade` rejects the XML outright (`[facet 'enumeration'] The value
  'unique' is not an element of the set {...}`). Real failure (BUN-889,
  `Webjump\Seller`, `db_schema.xml`): `<index referenceId="..."
  indexType="unique">` on the `identifier` column — the scenario "reject
  duplicate identifier" depends on this actually being a DB-level unique
  constraint, so the bug wasn't just a syntax error, it silently would have
  shipped without the uniqueness guarantee the spec asked for. Fix:
  `<constraint xsi:type="unique" referenceId="...">`, same child
  `<column>` element, just a different parent tag and `xsi:type` instead of
  `indexType`. This bug is invisible to a static code review that doesn't
  know the schema.xsd enum by heart — the only reliable way to catch it is
  actually running `setup:upgrade` against the XML.

## Deploy/cache lifecycle — know what invalidates what

- `setup:upgrade` — schema/data patches. Run after any `db_schema.xml` or
  data-patch change.
- `setup:di:compile` — DI compilation cache. Run after adding/changing
  plugins, preferences, virtual types, or any class in a compiled scope.
- `setup:static-content:deploy` — frontend/admin static assets (CSS/JS/
  templates in production mode). Skip in `developer` mode, required in
  `production` mode.
- `cache:flush` vs `cache:clean` — `flush` empties the cache storage
  entirely; `clean` invalidates by tag. Prefer `clean` for the specific
  cache type you touched (`config`, `layout`, `block_html`, `full_page`,
  etc.) over a blanket `flush` unless you have a reason to nuke everything.
- Reindexing: know which indexers your change affects
  (`bin/magento indexer:info`) and state in your task update whether a
  reindex is needed and whether you ran it.

## Testing layers — pick the cheapest one that proves the work

1. **Unit tests** (`dev/tests/unit` or module `Test/Unit`) — no Magento
   bootstrap, no DB. Default choice for a single class/plugin/observer's
   logic.
2. **Integration tests** (`dev/tests/integration`) — real Magento bootstrap
   and test DB. Use when the logic genuinely depends on the framework
   (repositories, event dispatch, DI wiring) and cannot be unit-tested in
   isolation.
3. **MFTF / functional UI tests** — full browser flow. Reserve for
   user-facing flows; do not reach for this to verify a backend-only change.

Do not default to the full test suite. Run the smallest layer and the
smallest scope (a single test class/method) that proves the change, per the
standing execution contract, unless the task explicitly asks for full
release verification.

## Backward compatibility and versioning

- A method signature change on an `@api`-annotated interface is a breaking
  change — do not make it without calling it out explicitly and proposing a
  deprecation path instead.
- Check the target Adobe Commerce / Magento Open Source version's support
  lifecycle before recommending a pattern that may be removed or
  end-of-lifed on that version.

## REST API (`webapi.xml`) — the real exception → HTTP status mapping

Magento maps a thrown exception to an HTTP status by **exception type**, in
`Magento\Framework\Webapi\ErrorProcessor::maskException()` — not by however
a spec or a task happens to describe it. Confirmed in vendor source
(`vendor/magento/framework/Webapi/ErrorProcessor.php`, `maskException()`):

| You throw | HTTP status | Notes |
|---|---|---|
| `NoSuchEntityException` | **404** | The standard "record not found" — `RepositoryInterface::getById()` throwing this is what a GET/PUT/DELETE-by-id 404 scenario should cite. |
| `AuthorizationException` | **401** | ACL check failed — a valid, authenticated token that lacks the required resource. **Not 403.** |
| `AuthenticationException` | **401** | No token, or an invalid/expired one. |
| Any other `LocalizedException` (`InputException`, `ExpiredException`, `InvalidStateException`, …) | **400** | The catch-all for "bad request" — validation failures land here. |
| A plain `\Exception` (not a `LocalizedException`) | **500** | Uncaught/unexpected — never rely on this for an intentional error path. |

**There is no first-class 403 in this mapping.** Both "no token" and "token
present but insufficient permission" collapse to the same 401 — a spec
scenario that says one of those two cases should return 403 describes
behavior Magento's own framework doesn't produce; either fix the scenario
to 401, or (rare, and call it out explicitly if you actually need it) throw
a custom `WebapiException` with an explicit status code instead of relying
on a `LocalizedException` subtype. Seen in practice: the `bunge-
listprice-rest-crud` OpenSpec change's first draft spec claimed 403 for a
"non-admin token" scenario — corrected during review to 401, matching this
table, before any code was written against it.

When writing or reviewing a REST-facing spec/scenario/task (proposal,
design, or acceptance criteria), use this table instead of guessing a
status code from memory or from a generic REST/HTTP convention — Magento's
mapping is coarser than the general HTTP spec (no 403, no 422, no 409 by
default) and picking a code this table doesn't produce is a scenario that
will never actually be true once implemented.

## Partial update: `addData()` (merge) vs `setData()` (replace)

When implementing a **partial update** (e.g. a PUT that sends only the fields
the client wants to change), the distinction between these two methods on
`Magento\Framework\DataObject` is critical:

- **`setData($array)`** — **replaces** the entire internal `_data` array.
  Any field not present in `$array` becomes `null`. If the entity has typed
  getters (e.g. `public function getSapCode(): string`), the serializer will
  throw a `TypeError` when it tries to return `null` from a `string` getter.
  This is the most common cause of 500 errors on partial PUT endpoints.
- **`addData($array)`** — **merges** `$array` into the existing `_data`.
  Fields not in `$array` keep their current values. This is the correct
  method for partial updates.

**Rule**: in an `update()` method that receives only the fields to change,
always use `$existing->addData($incoming->getData())` — never `setData()`.

Real failure (BUN-888, `Webjump_BungeListPriceApi`): `ListPriceRestService::update()`
did `$existing->setData($data->getData())` for a partial PUT. Fields not sent
in the request body became `null`, and the response serialization threw
`TypeError` on `getSapCode(): string`. Fix was one word: `addData()`.

## `SearchResultsInterface` for REST list endpoints

When a repository's `getList()` needs to return **full entity data** (not
index documents) via a REST endpoint, the return type must be
`\Magento\Framework\Api\SearchResultsInterface` — **not**
`\Magento\Framework\Api\Search\SearchResultInterface` (note the `Search\`
prefix). The two interfaces look similar but serve different purposes:

| Interface | Purpose | Item type | REST serialization |
|---|---|---|---|
| `Api\SearchResultsInterface` | CRUD list/search | Your entity's `*Interface` | Serializes all fields the entity interface declares |
| `Api\Search\SearchResultInterface` | Index/search | `DocumentInterface` | Only serializes `id` — `DocumentInterface` has no entity fields |

If a repository returns `Search\SearchResultInterface`, the webapi output
processor only sees `DocumentInterface` fields → the response is
`{"items": [{"id": 1}, {"id": 2}], ...}` with no entity data.

**Recipe for a correct `getList()` return type** (3 files + 1 di.xml entry):

1. **`Api/Data/<Entity>SearchResultsInterface.php`** — extends
   `SearchResultsInterface`. The `getItems()`/`setItems()` docblocks must use
   the **fully qualified class name** of your entity interface — not a
   short name from a `use` import. The Magento docblock resolver reads the
   literal string in the `@return`/`@param` tag; it does not resolve PHP
   `use` statements.

   ```php
   declare(strict_types=1);

   namespace Vendor\Module\Api\Data;

   use Magento\Framework\Api\SearchResultsInterface;

   /**
   * @api
   * @inheritdoc
   */
   interface EntitySearchResultsInterface extends SearchResultsInterface
   {
       /**
        * @return \Vendor\Module\Api\Data\EntityInterface[]
        */
       public function getItems();

       /**
        * @param \Vendor\Module\Api\Data\EntityInterface[] $items
        */
       public function setItems(array $items);
   }
   ```

2. **`Model/<Entity>SearchResults.php`** — a concrete (can be empty beyond
   the constructor) class extending `Magento\Framework\Api\SearchResults`
   and implementing your interface:

   ```php
   declare(strict_types=1);

   namespace Vendor\Module\Model;

   use Magento\Framework\Api\SearchResults;
   use Vendor\Module\Api\Data\EntitySearchResultsInterface;

   class EntitySearchResults extends SearchResults implements EntitySearchResultsInterface
   {
   }
   ```

3. **`etc/di.xml`** — a `<preference>` wiring the interface to the concrete
   class. Without this, Magento's DI throws `Cannot instantiate interface`:

   ```xml
   <preference for="Vendor\Module\Api\Data\EntitySearchResultsInterface"
               type="Vendor\Module\Model\EntitySearchResults"/>
   ```

4. Update the repository interface's `getList()` return type to your new
   `*SearchResultsInterface` (fully qualified in the docblock), and update
   the implementation to create your concrete class instead of the generic
   `SearchResultInterface`.

**When reusing an existing repository**: always check the declared return
type of `getList()`. If it returns `Search\SearchResultInterface` (the
index-oriented type), it will not work for a REST CRUD list endpoint — you
need the `SearchResultsInterface` pattern above, even if the underlying
implementation already loads the right data. The return type declaration is
what the webapi output processor reads to determine which fields to
serialize.

## Extending a Magento core class — verify the parent, never assume its shape

PHP forbids a child class from adding a native type to a property it inherits
**untyped** from a parent class — `Type of <Child>::$prop must not be defined
(as in class <Parent>)`. This is a **fatal error at class-load time** (not a
lint warning, not a runtime exception you can catch) — it kills
`setup:upgrade`, `setup:di:compile`, and any request that touches the class,
the moment PHP tries to declare the class.

Many Magento core base classes declare their protected properties **without**
a native type (`protected $collection;`, not `protected Collection
$collection;`) — this predates PHP 7.4 typed properties and was never
retrofitted. `Magento\Ui\DataProvider\AbstractDataProvider::$collection` is
one of these. A child class that redeclares it with a type — even to
document the concrete type for an IDE — triggers this fatal.

**Before adding a native type to a property you override/redeclare from a
parent class, open the parent and check its exact declaration.** If the
parent's property is untyped, do not type it in the child either — use a
`@var` docblock for IDE/static-analysis benefit instead, and set it in the
constructor without a property redeclaration (see any working
`AbstractDataProvider` subclass in this codebase for the pattern: assign
`$this->collection = ...` in `__construct()`, no `protected Collection
$collection;` line at all).

Real failure (BUN-889, `Webjump\Seller`): `SellerDataProvider extends
Magento\Ui\DataProvider\ModifierPoolDataProvider` (which extends
`AbstractDataProvider`) redeclared `protected \Webjump\Seller\...\Collection
$collection;` — a PHP fatal that broke `setup:upgrade` for the whole branch.
Caught only by actually running the command, not by reading the code — this
is a class of bug static review tends to miss, since the code reads as
reasonable, well-typed PHP; the problem only exists in relation to the
parent's own (untyped) declaration.

## Extending a Magento core class — the parent's real signature is not optional reading

Four real, distinct fatals from the same root cause (BUN-889, `Webjump\Seller`
module) — a child class's declaration didn't match what the parent class
**actually** declares, because the parent was assumed/remembered/copied from
a similar-looking class instead of opened and read:

1. **Narrowing visibility on an inherited property** is a fatal, same family
   as the untyped-property case above: `Access level to <Child>::$prop must
   be protected (as in class <Parent>) or weaker`. `Edit extends
   Magento\Backend\App\Action` (→ `AbstractAction`) redeclared
   `private RedirectFactory $resultRedirectFactory;`, but the parent already
   declares it `protected $resultRedirectFactory` **and already sets it** in
   its own constructor from `$context->getResultRedirectFactory()`. The
   child didn't need to inject or redeclare it at all — just use
   `$this->resultRedirectFactory` inherited as-is (two sibling controllers in
   the same module, `Delete.php` and `Save.php`, already do this correctly —
   when in doubt, check a sibling file in the same PR before guessing).

2. **A return type resolves to the wrong namespace because the type isn't
   imported.** `SellerRepository::getList(): SearchResultsInterface` compiled
   to `Webjump\Seller\Model\SearchResultsInterface` (a class that doesn't
   exist) because the file imported `SearchResultsInterfaceFactory` but never
   imported `SearchResultsInterface` itself — PHP resolves an unqualified
   name relative to the **current** namespace when there's no matching `use`.
   Every type named in a method signature needs its own `use` statement (or
   fully-qualified `\` prefix); needing the factory does not imply the
   interface is imported too.

3. **A constructor parameter that doesn't exist in the parent, inserted by
   assumption.** `SellerActions extends Magento\Ui\Component\Listing\Columns\Column`
   declared `__construct(ContextInterface $context, UiComponentFactory
   $uiComponentFactory, LoggerInterface $logger, array $components = [],
   array $data = [])` and forwarded all 5 args to `parent::__construct()` —
   but the real `Column` constructor takes only 4 (`context,
   uiComponentFactory, components, data`), no `$logger` at all. The `$logger`
   value landed in the `$components` (array) slot:
   `Incompatible argument type: Required type: array. Actual type:
   \Psr\Log\LoggerInterface`. `$logger` was also never used anywhere in the
   class body — a signature copied from a different base class (or invented)
   rather than read from `Column` itself.

4. **A `virtualType` whose `name` is the same as its own `type`** —
   `<virtualType name="Vendor\Module\Foo" type="Vendor\Module\Foo">` — is
   rejected outright: `unsupported self-referencing virtual type: ...`.
   `virtualType` exists to give a **different**, injectable name to a
   variant of an existing class; if the class is concrete and you just need
   to configure its constructor arguments, use `<type name="...">` instead,
   never wrap a class in a virtualType of its own name.

**The fix for all four is the same discipline, not four different rules:**
before extending a Magento core class — or configuring one in `di.xml` —
open the actual parent class (or, for `di.xml`, a real working example
already in this codebase) and read its real constructor signature, its real
property declarations, and their real visibility/types. A plausible-looking
signature "in the style of" Magento is not the same as the signature the
parent class actually has, and the compiler/class-loader enforces the exact
one, not the plausible one.

## UI Component admin form — the save controller and the form are separate contracts, verify both

A declarative UI Component form (`Magento_Ui/js/form/provider` as the
`dataSource` component — the modern, Block-free admin form pattern) does
**not** get a Save button by default. Every button on the form's toolbar —
`save`, `delete`, `back` — is an explicit `<button>` entry in
`<settings><buttons>`, each backed by a PHP class implementing
`Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface`.
Omitting the `save` entry doesn't cause an error anywhere — the form loads
and renders fine, just with no way to submit it. This makes it easy to add
`delete`/`back` (often copied from a reference module) and forget `save`,
since nothing fails until a human actually opens the page.

Real failure (BUN-889, `Webjump\Seller`): `seller_seller_form.xml` declared
`back` and `delete` buttons but no `save` button, and no `SaveButton` class
existed in the module at all — the New/Edit Seller admin page had no way to
submit the form. Caught only by opening the actual page, not by any
automated check (`di:compile`/`setup:upgrade` both pass fine with a save
button completely missing — there's nothing to validate against).

**Minimal save button** (see `vendor/magento/module-cms/Block/Adminhtml/Block/Edit/SaveButton.php`
and `view/adminhtml/ui_component/cms_block_form.xml` for the real reference):

```php
class SaveButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [[
                            'targetName' => '<namespace>.<namespace>',
                            'actionName' => 'save',
                            'params' => [true],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
```

```xml
<button name="save" class="Vendor\Module\Ui\Component\Control\SaveButton"/>
```

`targetName` is `<namespace>.<namespace>` — the form's `<namespace>` value
from the same `<settings>` block, repeated (Magento's own convention when
the UI Component's own name equals its namespace, which is the normal case
for a single-form admin page).

**When reviewing/writing any admin CRUD form**, treat "can I actually save a
new record" as a required manual check, not an assumption — this is
UI-only and no compiler/upgrade command will ever catch its absence.

### The Save controller must read POST the way the form's `dataScope` submits it

A second, independent way the same form/controller pair breaks: the `Save`
controller assumes a POST structure that doesn't match what the form
actually sends. With `<dataScope>data</dataScope>` at the form's top level
(the normal case for a single-entity form — see the `cms_block_form.xml`
reference above), the fields submit **flat**, with no wrapper key. The
correct read is `$this->getRequest()->getPostValue()` — the whole POST body
— exactly what `Magento\Cms\Controller\Adminhtml\Block\Save::execute()`
does. A controller that instead does `$this->getRequest()->getPost('<entity>')`
— guessing a wrapper key that doesn't exist — gets `null`, and the very next
line (almost always `$model->addData($data)`) throws a `TypeError:
addData(): Argument #1 ($arr) must be of type array, null given` the moment
someone actually clicks Save.

Real failure (BUN-889, `Webjump\Seller`): `Save.php` did
`$this->getRequest()->getPost('seller')` against a form with
`<dataScope>data</dataScope>` — the `'seller'` wrapper key was invented, not
read from the form's actual configuration. Same root cause as the missing
save button above (a different part of the same form/controller pair,
found in the same module, in the same review pass) — **whatever the form's
`dataScope` actually is, and whatever a working reference controller
(`Magento\Cms\Controller\Adminhtml\Block\Save` is the simplest one) actually
does with the request, is not optional research** before writing a Save
controller. `di:compile`/`setup:upgrade`/unit tests written-not-run all pass
clean with this bug in place — like the missing button, it is invisible to
everything except actually submitting the form.

### A blindly-merged empty primary key silently turns INSERT into a no-op UPDATE

A third way the same `Save` controller breaks, more dangerous than the
previous two because it produces **no error at all** — a fake success. A
UI Component form always submits its primary-key field, even for a brand
new record, as an **empty string** (`''`), not absent and not `null` —
because the field exists in the form's hidden `<field name="<entity>_id">`
regardless of whether a real ID has been assigned yet. If the controller
does `$model->addData($data)` with that raw `''` still in `$data`, the
model's ID field becomes `''` — not `null`. Magento's save flow decides
INSERT vs UPDATE based on whether the ID is `null`; an empty string is not
`null`, so it takes the **UPDATE** path with a `WHERE <entity>_id = ''`
clause that matches zero rows. An `UPDATE ... WHERE` matching no rows is not
an error in SQL — no exception is thrown, the controller's success message
fires unconditionally, and the user sees "You saved the X." while nothing
was ever written. The giveaway, if you instrument it: the model's own ID
getter reports `0`/empty after "save" instead of a real new auto-increment
value.

**The fix is explicit, and Magento's own reference controllers all do it**:
null out the ID field before merging, when it's empty. From
`Magento\Cms\Controller\Adminhtml\Block\Save::execute()`:

```php
if (empty($data['block_id'])) {
    $data['block_id'] = null;
}
```

Real failure (BUN-889, `Webjump\Seller`): `Save.php` was missing this check
entirely — `addData($data)` ran with `$data['seller_id'] === ''` straight
from the POST body, every single "Add New Seller" silently no-op'd. Caught
only by comparing the model's post-save ID to what a real INSERT should
produce (`0` vs a real auto-increment value) — `di:compile`, `setup:upgrade`,
a written-not-run unit test, and even the controller's own try/catch all
saw nothing wrong, because from the database driver's point of view, nothing
went wrong.

**When reviewing any Save controller that merges raw POST data onto a model
via `addData()`/`setData()`, check specifically**: is the primary-key field
nulled out when empty, before the merge? This is not automatic, and is a
separate check from "does it read the right POST key" above.

## Common anti-patterns to flag, not silently fix

- Direct SQL against core tables instead of a repository/collection.
- Adding a native type to a property redeclared from a parent class that
  declares it untyped — PHP fatal at class-load time (see section above).
- Business logic inside a `Block` or template (`.phtml`) instead of a
  `ViewModel` or service class.
- A second `preference` on a type that already has one from another module.
- Skipping `setup:di:compile` after a DI-relevant change and assuming
  `developer` mode auto-recompiles — it does not for all compiled artifacts.
- Using `setData()` instead of `addData()` for a partial update — `setData()`
  replaces the entire `_data` array, nullifying fields not in the input
  (see "Partial update" section above).
- Reusing an existing repository's `getList()` without checking its return
  type — `Search\SearchResultInterface` (index) serializes only `id` via
  REST; you need `SearchResultsInterface` (CRUD) for entity data.







