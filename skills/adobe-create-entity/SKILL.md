---
name: adobe-create-entity
description: "Scaffold a new Magento 2 entity's persistence layer in a Webjump/* module: db_schema.xml with keys and foreign-key relations, whitelist, Api/Data interface, model, ResourceModel, Collection, and extension attributes on core entities. Use for 'create an entity for X' or 'add a table to store Y', before adobe-create-repository, adobe-create-controller, or adobe-magento-ui-component-developer. No business logic, validation, or UI."
---

# Create Entity

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-entity` skill.
Namespace changed to Bunge's `Webjump\*` convention; content otherwise applies as-is —
this skill is pure Magento persistence-layer scaffolding, no FTD-specific dependency.

Full working files: `magento-example model`, `db-schema`, and `extension-attribute` (skill
`adobe-magento-toolbox`). Copy their shape; file names and constants follow this skill.

## 1. Scope contract

**Expected input:** the target module, entity name, table name, and full field list — or
willingness to answer when asked. For each relation: the referenced table and what happens
to this row when the referenced one is deleted. Never guess a schema from a vague
description; an incomplete schema always comes back as migration rework later.

**Produces:** `etc/db_schema.xml`, a regenerated `etc/db_schema_whitelist.json`,
`Api/Data/{Entity}Interface.php`, `Model/Data/{Entity}.php`,
`Model/ResourceModel/{Entity}.php`, `Model/ResourceModel/{Entity}/Collection.php`, and
`etc/extension_attributes.xml` when the data must appear on a core entity's API.

**Out of scope — don't advance these even if asked in the same request:** Commands/
Repository/`di.xml`/`webapi.xml`/`acl.xml` (→ `adobe-create-repository`), controllers/routing
(→ `adobe-create-controller`), admin grid/form (→ `adobe-magento-ui-component-developer`), business logic or
validation (→ `adobe-inject-validator`, once its Tier-2 dependency question resolves), the
plugin that fills an extension attribute (→ `adobe-create-plugin`), a new field on products,
customers, or categories (→ EAV attribute in a data patch: `magento-example
eav-product-attribute` or `eav-customer-attribute`). This skill is normally the first link in
a chain — don't jump ahead to the next skill's output.

## 2. Golden rules

1. **Never guess module, entity, table, or fields.** Ask for whatever wasn't given
   explicitly before writing anything.
2. **Every `db_schema.xml` change is followed, in the same turn, by regenerating the
   whitelist** (`bin/magento setup:db-declaration:generate-whitelist
   --module-name=Webjump_{Module}`). Magento only drops or recreates the tables, columns,
   indexes, and constraints listed there, so without it a later removal or rename silently
   never applies — no error.
3. **DTO, ResourceModel, and Collection are pure persistence mapping.** No business rule,
   validation, or listing filter belongs here — that's `adobe-inject-validator` or
   `adobe-create-repository`'s job.
4. **Table/column names are referenced only via the interface's `const`s, never as string
   literals.** A literal silently diverges when a column is renamed later — that failure
   shows up at runtime, not compile time.

## 3. Persistence-layer boundaries (hard rules)

| Rule | Why | Instead |
|---|---|---|
| No business logic, validation, or computed properties in `Model/Data/{Entity}`, `Model/ResourceModel/{Entity}`, or its `Collection` | These classes are pure persistence mapping; mixing in business rules makes the layer untestable and couples schema to logic that changes at a different pace | Business logic goes in a Command (`adobe-create-repository`); validation goes in `adobe-inject-validator` |
| Never make `Model/Data/{Entity}` extend `DataObject` or `AbstractSimpleObject` | `AbstractDb::save()`, `load()`, and `delete()` accept only `AbstractModel`, so anything else is a `TypeError` on the first save | Extend `Magento\Framework\Model\AbstractModel` (`AbstractExtensibleModel` if extensible, section 4) and implement `{Entity}Interface` |
| Never override `Collection::_construct()` with filters, joins, or ordering | Breaks the Repository/Command separation — listing filters are a `GetList` Command's job, driven by `CollectionProcessorInterface`, not the Collection itself | Implement the filter in `adobe-create-repository`'s `GetList` Command, never in the Collection |
| Never use `\Magento\Framework\App\ObjectManager::getInstance()` | Breaks DI and testability irreversibly for whoever inherits this code | Constructor injection (factories only when Magento genuinely requires them) |
| Never reference a table/column name as a string literal outside `{Entity}Interface`'s `const`s | Silent divergence between schema and code | Always `{Entity}Interface::FIELD` / `::TABLE_NAME` |

Verify before delivering:
```bash
grep -rn "ObjectManager::getInstance" app/code/Webjump/{Module}/Model/
grep -n "_construct" app/code/Webjump/{Module}/Model/ResourceModel/{Entity}/Collection.php
# the second check should show only _init(...), never addFieldToFilter/join/setOrder
grep -n "extends" app/code/Webjump/{Module}/Model/Data/{Entity}.php
# expect AbstractModel or AbstractExtensibleModel
```
Any check failing → don't deliver the file; explain which boundary was hit
and propose the correct alternative (Command/Validator) before continuing.

## 4. Model, relations, and extension attributes

**Model.** Getters cast what they read (`(int)`, `(float)`, `(string)`; `null` stays `null`):
Magento returns database values as strings, and under `strict_types` a `?int` getter that
returns `"1"` throws a `TypeError`.

**Relations (`db_schema.xml`)**
- A foreign key is `<constraint xsi:type="foreign" referenceId="{TABLE}_{COLUMN}_{REFTABLE}_{REFCOLUMN}">`,
  and its column copies the referenced column's `xsi:type`, `unsigned`, and length exactly
  (`store.store_id` is `smallint unsigned`; `customer_entity.entity_id` and
  `sales_order.entity_id` are `int unsigned`): otherwise `setup:upgrade` stops with "Column
  definition ... and reference column definition ... are different".
- `onDelete` is `CASCADE`, `SET NULL` (column `nullable="true"`), or `NO ACTION`, as the
  requester answered in section 1.
- Adobe Commerce staging makes `row_id` the primary key of products, categories, and CMS
  pages and blocks, with one row per scheduled version. Reference
  `sequence_product.sequence_value` (`sequence_catalog_category`, `sequence_cms_page`,
  `sequence_cms_block`), never their `entity_id`, `page_id`, or `block_id`.

**Extension attributes**
- Data that belongs on a core entity's API (order, quote): a column on the core table declared
  with that table's own `resource` (`sales_order`: `sales`; `quote`: `checkout`), or a table of
  your own keyed by the core id, plus `etc/extension_attributes.xml` for the core `Api/Data`
  interface. The plugin fills it in `afterGet` and `afterGetList` (lists don't call `get()`)
  and, when clients write it, saves it in `afterSave`.
- Make this entity extensible only when another module must add fields to it:
  `{Entity}Interface` extends `ExtensibleDataInterface`, `getExtensionAttributes()` and
  `setExtensionAttributes()` use the generated `{Entity}ExtensionInterface`, and the model
  extends `AbstractExtensibleModel`.

## 5. Definition of done

- [ ] Module confirmed to exist (`registration.php` + `etc/module.xml`), or bootstrapped
      if genuinely new
- [ ] Every field, index, and constraint was explicitly confirmed with the requester —
      none guessed
- [ ] `db_schema.xml` written/updated **and** the whitelist regenerated in the same turn
- [ ] DTO/ResourceModel/Collection contain zero business logic, zero `ObjectManager`
      usage, zero string-literal table/column names outside the interface's `const`s
- [ ] `Collection::_construct()` contains only `_init(...)` — no filter/join/sort
- [ ] `Model/Data/{Entity}` extends `AbstractModel`/`AbstractExtensibleModel`; getters cast
- [ ] Each foreign-key column matches the referenced column; `onDelete` confirmed; staged
      catalog/CMS tables referenced through their `sequence_*` table
- [ ] `magento-verify` (skill `adobe-magento-toolbox`) ends in `VERIFY OK`
- [ ] Report names the next expected step (Repository/Controller/Admin UI) as explicitly
      out of this skill's scope, not silently produced

## Antipatterns

- Writing the schema from a "best guess" at fields because the request only described the
  entity at a high level — stop and ask for the full field list first.
- Forgetting to regenerate the whitelist after editing `db_schema.xml` — the failure is
  silent and only shows up after `setup:upgrade` runs.
- Putting a business rule ("max discount", "valid document number") directly in the DTO's
  setter because "it's just an if" — that's hidden technical debt behind what should be a
  pure mapping.
- Accepting a request to also deliver Repository/Controller/Admin UI in the same task —
  each layer is its own skill; mixing them produces partial, hard-to-review deliverables.
