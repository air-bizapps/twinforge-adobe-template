---
name: adobe-magento-xml-specialist
description: "Magento 2.4.8 configuration XML: schema URN per file, how files from all modules merge by identifying attribute, changing or disabling another module's node without copying it, di.xml argument types, layout instructions, and validation (production does not validate XSD). Use when writing or debugging di, events, routes, acl, webapi, db_schema, system, config or layout XML. Framework rules: adobe-commerce-standards; checks: magento-verify."
---

# Magento XML specialist

Every `etc/*.xml` and layout file is merged with the same file from every enabled module, in
`<sequence>` order, then validated against its XSD. Module-prefixed names and area files are in
`adobe-commerce-standards` ("Module structure", "Dependency injection"). `$T` is the `scripts/`
folder of `adobe-magento-toolbox`.

## Rules

- **Declare the schema URN of the file**, copied from a core module, never a path into `vendor/`:

  | File | `xsi:noNamespaceSchemaLocation` |
  |---|---|
  | `module.xml` | `urn:magento:framework:Module/etc/module.xsd` |
  | `di.xml` | `urn:magento:framework:ObjectManager/etc/config.xsd` |
  | `events.xml` | `urn:magento:framework:Event/etc/events.xsd` |
  | `routes.xml` | `urn:magento:framework:App/etc/routes.xsd` |
  | `acl.xml` | `urn:magento:framework:Acl/etc/acl.xsd` |
  | `webapi.xml` | `urn:magento:module:Magento_Webapi:etc/webapi.xsd` |
  | `db_schema.xml` | `urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd` |
  | `adminhtml/system.xml` | `urn:magento:module:Magento_Config:etc/system_file.xsd` |
  | `config.xml` | `urn:magento:module:Magento_Store:etc/config.xsd` |
  | `crontab.xml` | `urn:magento:module:Magento_Cron:etc/crontab.xsd` |
  | `adminhtml/menu.xml` | `urn:magento:module:Magento_Backend:etc/menu.xsd` |
  | layout handle | `urn:magento:framework:View/Layout/etc/page_configuration.xsd` |

- **Nodes merge by their identifying attribute**; the module later in `<sequence>` wins on conflicts:

  | File | Identifier |
  |---|---|
  | `di.xml` | `preference@for`; `type`/`virtualType@name`; `plugin`, `argument`, `item@name` |
  | `events.xml` | `event@name`, then `observer@name` |
  | `routes.xml` | `router@id`, `route@id`, `module@name` |
  | `acl.xml` | `resource@id` (nested) |
  | `webapi.xml` | `route@url` + `route@method` |
  | `db_schema.xml` | `table@name`, `column@name`, `constraint`/`index@referenceId` |
  | `system.xml` | `section`/`group`/`field@id` |
  | `config.xml` | node path |
  | layout | block and container `name` |

- **Change another module's node by redeclaring only what changes**, with the same identifier, from a
  module that has the original in `<sequence>`. Never copy the whole file: copies stop following
  upgrades.
- **Disable instead of deleting**: `<plugin name="x" disabled="true"/>` under the same `<type>`,
  `<observer name="x" disabled="true"/>` under the same `<event>`,
  `<referenceBlock name="x" remove="true"/>` in layout. In `db_schema.xml`, `disabled="true"`
  removes a column or index that another module declared (your own: delete the declaration).
- **`di.xml` argument types** (`xsi:type`): `string` (`translate="true"` for labels), `boolean`,
  `number`, `const`, `init_parameter`, `null`, `object`, `array`. Array `<item>`s merge across modules
  by name, so you add to a core pool (modifiers, command lists) without replacing it. An `object`
  argument can name `Vendor\Module\Model\Heavy\Proxy` to defer a costly dependency.
- **A `virtualType` needs a name different from its `type`** and is referenced only from XML; PHP
  cannot type-hint it (`magento-verify`: `DI-VIRTUALTYPE-SELF`, `CLASS-VIRTUALTYPE-IN-PHP`).
- **Layout extends, never overrides**: one file per handle (`default`, `catalog_product_view`,
  `<route id>_<controller>_<action>`), with `referenceBlock`, `referenceContainer`, `move` and
  `<update handle>`. Pass data through a ViewModel argument (`xsi:type="object"`,
  `adobe-magento-frontend-standards`). `cacheable="false"` on any block makes the whole page
  uncacheable (`adobe-magento-cache-analyst`).
- **File-specific rules live with their skill**: `db_schema.xml` in `adobe-create-entity` and
  `adobe-commerce-standards` ("Schema changes"); `webapi.xml` and `acl.xml` in `adobe-create-repository`;
  `system.xml`/`config.xml` in `adobe-create-config`; `routes.xml`/`menu.xml` in `adobe-create-controller`;
  `crontab.xml` in `adobe-create-cronjob`; queue XML in `adobe-create-queue`.
- **Validate before done.** Magento checks config XML against the XSD only in developer mode
  (`ValidationState`); production merges an invalid file without the error, and it breaks later.

## Steps

1. Copy the file's shape from `php "$T/magento-example.php" <topic>` or a core module.
2. Choose the scope: global `etc/` or `etc/<area>/`.
3. Prefix new identifiers with the module; reuse an identifier only to change that node.
4. Run the commands the file needs (`setup:upgrade`, `setup:di:compile`, `cache:clean config layout`).

## Verify

- `php "$T/magento-verify.php"` ends in `VERIFY OK` (`XML-MALFORMED`, `XML-SCHEMA-URN`, `XML-XSD`).
- `bin/magento dev:di:info 'Vendor\Module\Model\Foo' adminhtml` shows the merged preference,
  arguments and plugins for that class in that area.
- `php "$T/magento-trace.php" --handle <handle>` shows the merged layout for a handle.
- `bin/magento config:show <path>` confirms a `config.xml` default or a saved value.
- `bin/magento dev:urn-catalog:generate .idea/misc.xml` lets PhpStorm validate URNs while you type.

## Pitfalls

- Your plugin replaced another module's plugin -> same `name` on the same type; prefix it.
- Change to a core node has no effect -> your module loads first; add `<sequence>` on the owner.
- XML works locally, breaks in production -> invalid against the XSD; run `magento-verify`.
- New `di.xml` entry ignored -> stale `generated/`; run `setup:di:compile`.
- Block removed but a child template still needed -> `move` the child before removing the parent.

## Docs

- https://developer.adobe.com/commerce/php/development/build/dependency-injection-file/
- https://developer.adobe.com/commerce/php/development/build/xml-schema-validation/
- https://developer.adobe.com/commerce/frontend-core/guide/layouts/xml-instructions/
- https://developer.adobe.com/commerce/php/development/components/declarative-schema/configuration/
