---
name: adobe-magento-ui-component-developer
description: "Build any admin UI Component in a Webjump/* module — a Listing (grid) alone, a Form alone, or both as a full CRUD — plus its layout XML and the etc/di.xml wiring via virtualType (no custom grid DataProvider class). Use whenever a task touches a ui_component XML: 'add an admin grid for X', 'a read-only listing', 'a form to edit Y', 'a CRUD in the backoffice', 'add mass delete, search, filters, inline edit or a modal'. Reports a missing entity, repository or controller instead of improvising it."
---

# Magento UI Component developer (Listing, Form or both)

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `adobe-magento-ui-component-developer` skill.
Two deliberate changes from the source: Bunge's `Webjump\*` namespace instead of FTD's,
and the Actions column is a small per-module `Column` subclass (core's shape) instead of
FTD's shared `FTD\Framework` class — Bunge has no equivalent, so this version needs nothing
beyond vanilla Magento/Adobe Commerce.

## Why this skill exists: the BUN-27 root cause

BUN-17 through BUN-21 hand-built an admin CRUD grid for `Webjump\BungeListPrice` the
generic-tutorial way — a custom PHP `DataProvider` class extending
`Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider`, wired via a
`di.xml` `<plugin>`. A constructor mistake in that class is what caused BUN-27. **The
fix here is not "write the DataProvider constructor more carefully" — it's not writing
one at all.**

## 1. Scope contract

**First, name what the task asks for**, and build only that:

| Task | Build | Controllers needed |
|---|---|---|
| Listing only (grid, often read-only) | §3, §4, §6 + listing layout | `Index` |
| Form only (edit one record or a settings-like screen) | §3b, §4, §6 + edit layout | `Edit`, `Save` |
| Full CRUD | everything below | the six standard ones |

Mass actions, inline edit, search/filters and modals (§5, §5b) only when the task asks.
A read-only listing has no Actions column with edit/delete and no form.

**Assumes already exist** (report the gap, never improvise the missing piece):
`Model`/`ResourceModel`/`Collection` + `db_schema.xml` (→ `create-entity`),
`{Entity}RepositoryInterface` (→ `create-repository`), routing + the six standard
controllers + base `etc/acl.xml`/`etc/adminhtml/menu.xml` (→ `create-controller`).

**This skill produces:** `etc/di.xml` UI-Component wiring (virtualTypes only — no new
PHP class) and, per the table above, the Listing UI Component (with its actions-column class
when rows are editable), the Form UI Component, their layout XML files (`index`, and
`edit`/`new` when there is a form), and — only if requested — the `<massaction>` node, a dedicated
`MassDelete` controller, and an additive `ResourceModel::massDelete()` method with its
own ACL resource.

**Never generate:** anything owned by `create-entity`/`create-repository`/
`create-controller` — a missing foundation gets reported to whoever asked, not
fabricated so this skill's own output has something to point at.

## 1b. Which real file to copy from — never write ui_component XML from memory

Every element in Magento's UI Component XML exists somewhere in a real,
shipped module. If you're not sure a structure is correct, it almost
certainly isn't yet — find the matching file below and copy its shape
before writing your own:

| You're building | Copy the shape from (in `vendor/magento/`) |
|---|---|
| Grid, simple CRUD | `module-cms/view/adminhtml/ui_component/cms_block_listing.xml` |
| Grid, more columns/filters, date column | `module-cms/view/adminhtml/ui_component/cms_page_listing.xml` |
| Grid, read-only (no actions/mass-action) | same as above minus `<massaction>`/`<actionsColumn>` — do not invent a "read-only" variant of anything else |
| Form, flat data shape (+ its `dataProvider` class, `Magento\Cms\Model\Block\DataProvider` — a *custom* class extending `ModifierPoolDataProvider`, see §3b) | `module-cms/view/adminhtml/ui_component/cms_block_form.xml` |
| Form, nested data shape, date field, custom field component | `module-inventory-admin-ui/view/adminhtml/ui_component/inventory_source_form.xml` |
| Index/listing layout XML | `module-inventory-admin-ui/view/adminhtml/layout/inventory_source_index.xml` (or `Webjump\BungeWebhook`'s `view/adminhtml/layout/*_index.xml`) — **has no `layout` attribute on `<page>` at all** |
| Edit/New layout XML | `module-inventory-admin-ui/view/adminhtml/layout/inventory_source_{edit,new}.xml` |
| A date column/field | any of the above — every one has one; never invent a `dateComponent`/`elementTmpl` value that isn't in one of these files |
| A local (Webjump) example alongside a vendor one | `Webjump\BungeWebhook`'s **grids and index layout only** (`view/adminhtml/ui_component/webjump_bungewebhook_*_listing.xml`, `view/adminhtml/layout/*_index.xml`) — see `bunge-backend-code-quality` for why its edit screen is off-limits |

**`layout="admin-2columns-left"` is for edit pages with a left tab/navigation
panel, never for a plain listing.** It reserves a `left` container
(`vendor/magento/module-theme/view/adminhtml/page_layout/admin-2columns-left.xml`)
that a listing page has nothing to put in — the page still renders and still
passes schema validation, but the browser shows an empty white column beside
the grid (seen in practice, 14079-2/BUN-105: the index layout copied its
reference almost exactly, then added this one attribute the reference didn't
have). Copy the reference's `<page>` tag literally, including which
attributes it does *not* have — don't add one because it "looks like a
sensible admin page default." `tools/uicomponent/validate.php` now WARNs on
this specific pattern (see its docblock, Part 2) — read the warning, it names
the file.

Before using any XML element or attribute you haven't already seen in one of
these files, grep for it first — zero hits means you're about to invent
something:

```bash
grep -rlF '<the-element-or-attribute>' vendor/magento/*/view/adminhtml/ui_component/
```

Zero results is not proof it's wrong on its own, but it is a hard stop:
find the real file that does what you need and copy its shape instead of
proceeding on memory.

## 2. Golden rules

1. **For the Listing's Grid Collection specifically: never write a custom
   `DataProvider` class for the common case.** Use
   `virtualType`s only — no DataProvider file, no test-coverage obligation, and
   structurally impossible to repeat BUN-27's mistake because there is no
   constructor to get wrong. Escalate to a real class only when the grid genuinely
   needs a join or a computed column the base collection can't express — and say so
   explicitly when you do. **This rule is about the grid, not the form** — see
   section 3b, a different, real Magento convention applies there, and reusing
   the grid's `DataProvider` class for the form is a distinct, confirmed way to
   break it (BUN-14079-3).
2. **Every page and every action gets its own dedicated ACL resource, mass actions
   included.** Never reuse `{Vendor}_{Module}::delete` for `MassDelete` — that removes
   the ability to grant single-row delete without also granting bulk delete.
3. **The `provider`/`dep`/`selectProvider`/`fieldAction.provider` strings in Listing/Form
   XML are namespace paths, not free labels.** Getting one wrong is silent — no PHP
   error, no failed compile, just a grid that never loads data or an inline edit that
   never saves. This is the single most common way an admin grid breaks.
4. **The `<dataSource>` element itself must declare a JS `component`** — either as a
   `component="Magento_Ui/js/grid/provider"` (Listing) /
   `component="Magento_Ui/js/form/provider"` (Form) attribute on the `<dataSource>` tag,
   or the equivalent `<argument name="data"><item name="js_config"><item
   name="component">` block. See section 6 (BUN-14079) — this is a second, distinct way
   a grid/form silently breaks, different from a wrong dot-path.
5. **Every XML attribute must be one the XSD actually allows on that element.** Config
   goes in `<settings>` as child elements (the `<argument name="data">` `<item>` array is
   the legacy syntax) — most attributes you'd expect to write directly on a tag (e.g.
   `dataType` on `<column>`) are not valid there and only belong inside `config`/
   `<settings>`. This throws a real, loud `LocalizedException` at component-creation
   time (see section 6) — cheap to catch, but only if you check for it.

## 3. Wiring — virtualType only, no custom PHP

```xml
<virtualType name="Webjump\{Module}\Model\ResourceModel\{Entity}\Grid\Collection"
             type="Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult">
    <arguments>
        <argument name="mainTable" xsi:type="const">Webjump\{Module}\Api\Data\{Entity}Interface::TABLE_NAME</argument>
        <argument name="resourceModel" xsi:type="string">Webjump\{Module}\Model\ResourceModel\{Entity}\Collection</argument>
    </arguments>
</virtualType>
```

- **Grid Collection**: `virtualType` extending `...DataProvider\SearchResult`, pointed at
  the entity's `TABLE_NAME` const and Collection — not a custom class + `<plugin>`.
- **Actions column**: `<actionsColumn class="...">` pointing to a small
  `Magento\Ui\Component\Listing\Columns\Column` subclass that builds the row links in
  `prepareDataSource()` (shape: core's `Magento\Cms\Ui\Component\Listing\Column\BlockActions`,
  or `ItemActions` in `magento-example ui-listing`; constructor rule in
  `adobe-commerce-standards`). Every core `actionsColumn` has a class; an
  `<item name="actions">` in the XML is legacy and crashes the grid (`UI-LEGACY-ACTIONS`).
- **Delete button**: reuse core `Magento\Backend\Ui\Component\Control\DeleteButton` via a
  `virtualType`, configured with `confirmationMessage`/`idFieldName`/`deleteRoutePath` —
  this is already vanilla Magento, not a project-specific class.
- `idFieldName`/`requestFieldName`/`primaryFieldName` must all match the entity's real
  primary key column, confirmed against `db_schema.xml` and what `create-controller`'s
  `Edit`/`Delete` actions actually read via `getParam()` — never assumed to be
  `entity_id`.
- The `CollectionFactory` mapping's item `name` must exactly equal the Listing's
  `<dataSource name="...">` — string equality, checked, not assumed.

Verify with `bin/magento setup:di:compile` — it catches a malformed virtualType or a
`di.xml` typo immediately. It does **not** catch a wrong dot-path string below; that
only surfaces in the browser.

## 3b. Form DataProvider — a *different* rule than the grid's, not an exception to it

**The grid's DataProvider (§3) and the form's DataProvider are not the same
problem, and Magento does not solve them with the same class.** Confirmed by
reading the actual vendor source (BUN-14079-3, 2026-09-28):

- `Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider` (the
  class §3's grid virtualType uses) loads data by calling
  `Reporting::search()`, which does
  `$this->collectionFactory->getReport($searchCriteria->getRequestName())` —
  it looks up a collection **by the dataSource's own `name`** in the
  `CollectionFactory` map from §3, and has no built-in concept of "load one
  record by primary key." Its constructor
  (`vendor/magento/framework/View/Element/UiComponent/DataProvider/DataProvider.php:93`)
  takes `$reporting, $searchCriteriaBuilder, $request, $filterBuilder` —
  **not** a `collection` or `filter` argument; passing those in a `di.xml`
  virtualType is silently ignored (they don't match any constructor
  parameter), which is exactly how this failure hides until the page is
  actually opened.
- Reusing this class for a **form**'s `dataProvider`, without also
  registering the form's own dataSource name in the `CollectionFactory` map,
  throws `Exception: Not registered handle
  <form>_data_source` (`CollectionFactory.php:49`) the moment the New/Edit
  page loads — a real, loud, unmissable-in-the-browser exception, but one
  that `tools/uicomponent/validate.php` (XSD-only) and `setup:di:compile`
  (checks the virtualType compiles, not that its arguments are ones the
  class actually reads) both pass cleanly. Registering the missing name
  would still not be enough on its own — this class also has no default
  single-record filtering, so the form would then load empty or with every
  row instead of just the one being edited.
- **The correct, idiomatic Magento pattern for a form is a small custom
  class extending `Magento\Ui\DataProvider\AbstractDataProvider`** (or
  `Magento\Ui\DataProvider\ModifierPoolDataProvider` if you also need to
  transform data, e.g. date normalization) — this is not the BUN-27
  mistake, it's what Magento's own core does: `module-cms`'s
  `cms_block_form.xml` wires `dataProvider class="Magento\Cms\Model\Block\
  DataProvider"`, and that class extends `ModifierPoolDataProvider`. A
  working local example already exists in this codebase:
  `Webjump\BungeListPriceAdmin\Model\DataProvider\ListPriceDataProvider`
  (restored 2026-09-27 from the original working BUN-14079 form) —
  `extends AbstractDataProvider`, takes a typed `CollectionFactory` in its
  own constructor (`parent::__construct(...)` then
  `$this->collection = $collectionFactory->create();`), and `getData()`
  returns `$this->collection->getItems()` keyed by entity id. Copy that
  shape, not the grid's virtualType-only pattern, for a form that loads an
  existing record.
- The `di.xml` wiring for that class is still mostly declarative — one
  `virtualType` or `<type>` preference passing your typed `CollectionFactory`
  argument to the custom class's constructor — it's the *class* that's
  custom, not the wiring philosophy from §3.
- **A date field's `getData()` value and its declared display format are one
  fact, stated twice, and both must agree** — confirmed live in a browser
  (BUN-14079-3, 2026-09-28): a `formElement="date"` field with
  `<formElements><date><settings><options><option name="dateFormat">
  dd/MM/yyyy</option></options></settings></date></formElements>` needs the
  value your DataProvider's `getData()` returns for that field to *already
  be* in `d/m/Y`, every time — not whatever raw format the row happens to
  have stored (`Y-m-d`, `Ymd`, `d-m-Y`, with or without a time part; this
  codebase's data is SAP-imported and genuinely has all of these). Get this
  wrong and nothing throws — the page loads fine — but the field shows
  today's date, `undefined`, and "Please enter a valid date" the instant a
  human opens the edit page, because the picker widget can't parse the raw
  value against the format it was told to expect. Normalize **every** date
  field in `getData()` itself (try each known raw format with
  `DateTime::createFromFormat()`, in order, format the first match as
  `d/m/Y`) — don't add the `dateFormat` option without also adding this, and
  don't add this without also declaring the matching `dateFormat` option;
  each one alone still breaks. The `Save` controller's inverse conversion
  (`d/m/Y` → `Y-m-d` before persisting) is a separate, already-covered step —
  don't confuse the two directions.

## 3c. Reusing a core virtualType (e.g. `DeleteButton`) — pass *every*
required constructor argument, not just the ones a reference happens to show

Reusing `Magento\Backend\Ui\Component\Control\DeleteButton` via a
`virtualType` (§3's suggested pattern) is correct, but **read that class's
real `__construct()` before copying a shorter example** — confirmed live
(BUN-14079-3, 2026-09-28):
`vendor/magento/module-backend/Ui/Component/Control/DeleteButton.php:68`
takes `RequestInterface, UrlInterface, Escaper, string $confirmationMessage,
string $idFieldName, string $deleteRoutePath, int $sortOrder` — every
scalar parameter with no default (`confirmationMessage`, `idFieldName`,
`deleteRoutePath`, **and `sortOrder`**) must be supplied as a `di.xml`
`<argument>`; the object-typed ones (`RequestInterface`, `UrlInterface`,
`Escaper`) are auto-resolved and never need one. Omitting `sortOrder`
throws `BadMethodCallException: Missing required argument $sortOrder of
<YourModule>\...\DeleteButton` the instant the edit page tries to build the
button — schema-valid `di.xml`, invisible to `validate.php`, only visible
in the browser (or `bin/magento setup:di:compile`, when runnable).

The general rule this generalizes to: **before writing a `virtualType`
around any core class, open that class's constructor and check off every
parameter with no default value** — a reference file you're copying may
simply not exercise every one of them.

## 3d. A grid row action that posts to a `HttpPostActionInterface`
controller must say so — `'post' => true`, not just `href`

Confirmed live (BUN-14079-3, 2026-09-28): the standard
`Magento_Ui/js/grid/columns/actions` JS component
(`vendor/magento/module-ui/view/base/web/js/grid/columns/actions.js`,
`defaultCallback()`) does `if (action.post) { dataPost().postData(...) }
else { window.location.href = action.href; }` — it only submits a POST
when the action's data explicitly has `'post' => true`; otherwise it does a
plain browser `GET` navigation to `href`. A `Delete` controller correctly
implementing `HttpPostActionInterface` (as `create-controller` produces)
then rejects that `GET` with a 404 — the link looks right, the controller
looks right, and it still 404s, because nothing tied them together. Any
actions-column PHP class building a `delete`-type action array needs
`'post' => true` alongside `href`/`label`/`confirm`; an `edit`-type action
(a plain `HttpGetActionInterface` page) does not.

## 4. Dot-path naming — the #1 way a grid silently breaks

Every dot-separated path in Listing/Form XML (`provider`, `dep`, `selectProvider`,
`fieldAction.provider`) is a namespace path from the root component, not a label.

| Concept | Convention | Example (`{listing_name}` = `webjump_bungelistprice_listing`) |
|---|---|---|
| Listing file & root node | `{listing_name}.xml`, root node = filename stem | `webjump_bungelistprice_listing` |
| Data source name | `{listing_name}_data_source` | `webjump_bungelistprice_listing_data_source` |
| `js_config.provider` | `{listing_name}.{listing_name}_data_source` | `webjump_bungelistprice_listing.webjump_bungelistprice_listing_data_source` |
| Columns container | `{listing_name}_columns` | `webjump_bungelistprice_columns` |
| `settings/deps/dep` | same string as `js_config.provider` | `webjump_bungelistprice_listing.webjump_bungelistprice_listing_data_source` |
| `settings/spinner` | the `<columns>` name | `webjump_bungelistprice_columns` |
| `editorConfig.selectProvider` (inline edit) | `{listing_name}.{listing_name}.{columns_name}.ids` — listing name appears **twice** | `..._listing.webjump_bungelistprice_listing.webjump_bungelistprice_columns.ids` |
| `childDefaults.fieldAction.provider` (inline edit) | `{listing_name}.{listing_name}.{columns_name}_editor` | `..._listing.webjump_bungelistprice_listing.webjump_bungelistprice_columns_editor` |
| Form file & root node | `{form_name}.xml`, root node = filename stem | `webjump_bungelistprice_form` |
| Form's `js_config.provider` | `{form_name}.{form_name}_data_source` | `webjump_bungelistprice_form.webjump_bungelistprice_form_data_source` |
| Form's `<namespace>` | same string as `{form_name}` | `webjump_bungelistprice_form` |

The doubled `{listing_name}` in `selectProvider`/`fieldAction.provider` looks like a typo
— it isn't. The outer one is the page-level component instance; the inner one is that
instance's own registered name. Copy the pattern exactly; don't reason about it from
scratch each time.

Verify: `grep -oE '(provider|dep|selectProvider)="[^"]*"' view/adminhtml/ui_component/*.xml`
and check every value against the table above by hand.

## 5. Mass actions — only when actually requested

Don't add speculatively — that's an untested code path and an ACL resource nobody asked
for.

- `<massaction>` node goes inside `<listingToolbar>`, alongside `<filters>`/`<paging>`.
- `MassDelete` is a dedicated controller (not one of `create-controller`'s standard six),
  implementing `HttpPostActionInterface`, using
  `Magento\Ui\Component\MassAction\Filter::getCollection()` to resolve selected rows.
- `MassDelete`'s `ADMIN_RESOURCE` is a **dedicated** `Webjump_{Module}::massDelete`
  resource — never the single-row `Delete`'s resource.
- `ResourceModel::massDelete(array $entities): bool` is **additive** to the existing
  `create-entity`-produced ResourceModel (one new method, not a redesign), and talks to
  the connection directly (`$connection->delete(...)`) — one query total, not a loop over
  `$repository->deleteById()`.

```php
public function massDelete(array $entities): bool
{
    if (!$entities) {
        return false;
    }
    $ids = [];
    foreach ($entities as $entity) {
        $ids[] = $entity->getEntityId();
    }
    $connection = $this->getConnection();
    return (bool)$connection->delete(
        $connection->getTableName({Entity}Interface::TABLE_NAME),
        $connection->quoteInto({Entity}Interface::ENTITY_ID . ' IN (?)', array_unique($ids))
    );
}
```

Verify: open the mass-action dropdown and confirm "Delete" is present; log in as a role
with `::delete` but not `::massDelete` and confirm the mass action is hidden/rejected
while single-row delete still works.

## 5b. Search, filters, bookmarks, inline edit, modal — only when requested

Copy the XML shape from `magento-example ui-listing` (toolbar, `status`/`valid_from`
columns, `editorConfig`); these are the dependencies it doesn't make obvious:

- **Keyword search** (`<filterSearch name="fulltext"/>`) filters only if the main table
  has a `fulltext` index (`db_schema.xml`, owned by `create-entity`); without one
  `FulltextFilter` ignores the keyword silently. The first fulltext index found is used,
  so it must cover every searchable column.
- **Filters**: a `select` filter needs `<options class>` + `<dataType>select</dataType>`;
  a column outside the main table can't be filtered or sorted until the collection joins
  it (rule 1's escalation case).
- **Bookmarks** keep each admin user's columns, filters and paging in `ui_bookmark` (by
  listing name). If an XML change "doesn't show", switch to Default View before debugging.
- **Inline edit**: keep `clientConfig.validateBeforeSave` false (the default first POSTs
  to a `validateUrl` the grid doesn't have, and the save never runs); `saveUrl` as
  `xsi:type="url"`; `selectProvider` must name an existing `<selectionsColumn>`. The
  `InlineEdit` controller is POST with its own ACL, reads `items[<id>][<field>]` and
  answers JSON `{"messages": [], "error": false}`.
- **Modal** (grid or form in a popup): `<modal>` holding an `<insertListing>`/`<insertForm>`,
  opened by a `<button>` whose `actions` call `toggleModal` on the modal's full name.
  Copy `assign_sources_modal` from
  `vendor/magento/module-inventory-admin-ui/view/adminhtml/ui_component/inventory_stock_form.xml`.

## 6. `<dataSource>` JS registration and XSD attribute traps (BUN-14079)

BUN-14079 (Sep 2026) hit two more silent-breakage classes on top of section 4's
dot-path problem, both on `Webjump\BungeListPriceAdmin`'s form and grids. Neither
throws a PHP exception in the normal request path and neither shows anything in the
browser console or Network tab — the page loads, the spinner never resolves, and
there's nothing to grep in any log. See the `magento-uicomponent-debug` company skill
for the full debugging playbook; this section is the prevention side.

**Trap 1 — a `<dataSource>` that never registers its JS component.** This is XML that
passes schema validation and even renders server-side without error, but the
client-side `deps` promise the form/grid `component` waits on never resolves:

```xml
<!-- BROKEN: fabricated structure, passes XSD, never wires the JS provider -->
<dataSource name="my_form_data_source">
    <argument name="dataProvider" xsi:type="configurableObject">
        <argument name="data" xsi:type="array">
            <item name="config" xsi:type="array">
                <item name="validateBeforeSave" xsi:type="boolean">false</item>
            </item>
        </argument>
    </argument>
    <settings>...</settings>
</dataSource>
```

```xml
<!-- CORRECT (form): component attribute directly on the tag -->
<dataSource name="my_form_data_source" component="Magento_Ui/js/form/provider">
    <settings>
        <submitUrl path="*/*/save"/>
    </settings>
    <dataProvider class="Webjump\Module\Model\DataProvider\MyDataProvider" name="my_form_data_source">
        <settings>
            <requestFieldName>entity_id</requestFieldName>
            <primaryFieldName>entity_id</primaryFieldName>
        </settings>
    </dataProvider>
</dataSource>
```

For a **Listing** `<dataSource>`, the `configurableObject` + base
`Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider` pattern (as
used in section 3's sibling examples across Magento core, e.g.
`Magento_Cms::cms_block_listing.xml`'s older cousins) IS legitimate — the difference
is whether the JS `component` (`Magento_Ui/js/grid/provider` /
`Magento_Ui/js/form/provider`) ends up declared somewhere in the resulting config.
When in doubt, **copy a real, currently-working vendor `ui_component` file whole**
(e.g. `vendor/magento/module-inventory-admin-ui/view/adminhtml/ui_component/
inventory_source_form.xml` for a form) rather than reconstructing the `<dataSource>`
block from memory.

**Trap 2 — a `<component>`/`<columnsWidth>`/`<dateComponent>`/etc. written as a nested
element or attribute somewhere the schema doesn't allow it** — e.g. `<component>` as a
child of a column's `<settings>` instead of an attribute on `<column>` itself, or
`<dateComponent>` outside the one place the date-field schema permits it. This class of
bug throws immediately and loudly — a real `LocalizedException` naming the exact file,
line, and element — the moment `UiComponentFactory::create()` parses the file, or the
moment schema validation runs. `DOMDocument::load()`/`simplexml_load_file()` do **not**
catch it (they only check the XML is well-formed, not that the schema accepts it) —
this is exactly how it reached two sibling grid files unnoticed
(`bungelistpriceadmin_listpricefull_grid.xml` and `..._fractioned_grid.xml` both had a
`<columnsWidth>` the schema doesn't define).

Run `php tools/uicomponent/validate.php <path>` (repo root, `tools/uicomponent/validate.php`
— needs only the composer autoloader, no Magento bootstrap, no DB) on every new or
changed `ui_component/*.xml` file before considering it done — not only when debugging
a reported failure. It is the section 8 gate below, not an optional extra.

## 7. Scope boundaries with sibling skills

```text
create-entity      → db_schema.xml, DTO, ResourceModel, Collection
create-repository  → SearchResults, Repository, di.xml preferences, webapi.xml/acl.xml
create-controller  → routes.xml, Index/NewAction/Edit/Save/Delete/InlineEdit, base acl.xml, menu.xml
adobe-magento-ui-component-developer → Listing/Form UI Components, di.xml UI wiring, layout XML, mass actions
```

The one file owned by another skill this skill may **edit** (never recreate) is the
`ResourceModel` produced by `create-entity` — adding `massDelete()`, and only when mass
actions are requested. If a prerequisite is missing, stop and name the sibling skill to
run first — never fabricate a Repository or controller set just so the UI Component has
something to reference.

## 8. Definition of done

- [ ] Module and `create-entity`/`create-repository`/`create-controller` outputs
      confirmed present (checked with `find`/`grep`, not assumed)
- [ ] `etc/di.xml` has the Grid Collection virtualType and (if the form has a delete
      button) the DeleteButton virtualType — no custom grid DataProvider class for the common case
- [ ] Every dot-path string in Listing/Form XML matches section 4's table exactly
- [ ] If the form has a `dataProvider class="..."`, that class extends
      `AbstractDataProvider`/`ModifierPoolDataProvider` (§3b) — **not** the grid's
      `Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider`. If it
      does, the New/Edit page throws `Not registered handle <name>` the moment it
      loads, unless that exact name is also in the `CollectionFactory` map — and
      even then it won't filter to one record. This is not caught by
      `validate.php` or `setup:di:compile`; only opening the page (or reading this
      checklist item) catches it.
- [ ] Form root declares `<item name="template" xsi:type="string">templates/form/collapsible</item>`
      in `argument data` (or a tabs `layout` in `settings`) — without it the spinner
      never goes away (`UI-FORM-NO-LAYOUT`)
- [ ] Mass actions (if requested): dedicated `MassDelete` controller + dedicated
      `::massDelete` ACL resource + additive `ResourceModel::massDelete()`
- [ ] Three layout files present: index (mounts Listing), edit (mounts Form), new
      (`<update>` of the edit handle)
- [ ] Every `<aclResource>`/`ADMIN_RESOURCE` string matches a resource declared in
      `etc/acl.xml`, one-to-one, no orphans
- [ ] **`php tools/uicomponent/validate.php app/code/Webjump/<Module>` prints `OK` for
      every file — no `FAIL` lines.** This is a hard gate, not a nice-to-have: it needs
      no Magento bootstrap, no DB, runs in under a second, and is the one check in this
      list that's actually runnable inside the subtask. Paste its output in the
      delivery report — including any `WARN` line (layout lint): it's not a build
      failure, but it names a real file and a real problem; fix it before reporting done.
- [ ] `bin/magento setup:di:compile` exit 0, if runnable in this workspace — catches a
      malformed virtualType or `di.xml` typo the XML validator above doesn't check.
      Note in the report if this step wasn't runnable (e.g. no DB in this workspace)
      rather than skipping it silently.
- [ ] For every `virtualType` wrapping a core class (`DeleteButton` and any other):
      opened that class's real `__construct()` and confirmed a `di.xml` `<argument>`
      exists for every scalar parameter with no default (§3c) — not just the ones a
      reference file happened to show.
- [ ] Every `delete`-type (or otherwise `HttpPostActionInterface`-targeting) row
      action in an actions-column class sets `'post' => true` (§3d) — an `href`
      alone silently 404s.
- [ ] Every date field (`formElement="date"`) has both a `dateFormat` option in its
      `<formElements><date>` block *and* matching normalization in the form
      DataProvider's `getData()` (§3b, last bullet) — one without the other breaks
      the field the instant a human opens the edit page, invisible to every check
      above it.
- [ ] If DB is available in this workspace: a real smoke test — instantiate the
      grid, the form (with an existing `entity_id` request param), and every
      `virtualType`-wrapped button class via `ObjectManagerInterface`/
      `UiComponentFactory`, `prepare()` + `getData()` each, and read one real row's
      values back. None of the three failure modes above (§3c's missing
      constructor argument, §3d's silent 404, this list's date-format mismatch)
      throw anywhere `validate.php` or `setup:di:compile` looks — a real
      instantiation is the only check in this list that catches all three at once.
      Not possible in every dev workspace; note in the report either way.
- [ ] Report names which follow-up skills apply: `inject-validator` (save validation),
      logging skills, `create-unit-tests` (only for a new `MassDelete` controller — the
      XML/di.xml wiring is config, not code, nothing else here needs a unit test)

A real browser check (grid loads, form loads, save works) remains the strongest
signal but is not something the dev subtask can do — it happens at PR review /
archive time. Do not report "verified" for anything beyond what the two checks
above actually confirm.

## Antipatterns

- Writing a custom grid `DataProvider` class for the common case — exactly the
  generic-tutorial pattern that caused BUN-27.
- Reusing the `::delete` ACL resource for `MassDelete` "to save time."
- Guessing a `selectProvider`/`fieldAction.provider` string instead of copying section 4's
  pattern exactly — the failure is silent, only visible in the browser.
- Generating `routes.xml`, the standard controllers, the base `etc/acl.xml` tree, or
  `etc/adminhtml/menu.xml` — that belongs to `create-controller`.
- Inventing a Repository or a controller set just so the UI Component has something to
  reference — report the gap instead.
- Reconstructing a `<dataSource>` block from memory instead of copying a real, working
  vendor `ui_component` file — the fabricated `configurableObject` wrapper in section 6
  passes schema validation and looks plausible, but never wires the JS provider.
- Assuming "the page loaded, no exception" or "`DOMDocument::load()` returned true"
  means the XML is schema-valid — neither checks the actual Magento schema. Run
  `php tools/uicomponent/validate.php` on every new/changed `ui_component` file,
  especially when it was produced by copy-pasting a sibling file — the same
  invalid-attribute bug can and did land in two sibling grids at once (BUN-14079).

