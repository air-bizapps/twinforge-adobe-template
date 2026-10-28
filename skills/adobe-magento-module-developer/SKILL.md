---
name: adobe-magento-module-developer
description: "Create or restructure a Magento 2.4.8 module: naming, registration.php, composer.json, module.xml, directory layout by area, enabling and app/etc/config.php, renaming and removing a module. Use when starting a module or deciding where a class belongs. Which layers a feature needs: adobe-magento-feature-developer; framework rules: adobe-commerce-standards."
---

# Magento module developer

The module skeleton and where each kind of code lives. Rules for `module.xml` `<sequence>`,
`setup_version` and XML merging are in `adobe-commerce-standards` ("Module structure"); the layers a
feature must build are in `adobe-magento-feature-developer`. `$T` is the `scripts/` folder of
`adobe-magento-toolbox`.

## Rules

- **Reuse the module that owns the domain before creating one.** Search first:
  `grep -rl '<keyword>' app/code/*/*/etc/module.xml app/code/*/*/etc/di.xml`. One module per business
  capability; one module per task fragments sequence, config paths and ACL trees.
- **One name everywhere.** `Vendor_Module` in `registration.php` and `etc/module.xml`, path
  `app/code/Vendor/Module`, PSR-4 prefix `Vendor\\Module\\` in `composer.json`. A mismatch registers a
  module whose classes never autoload, or a module under a name nobody searches for
  (`bunge-backend-code-quality`, "module dependency declarations").
- **`composer.json` is the real dependency list**: `"type": "magento2-module"`, `autoload.files`
  with `registration.php`, `autoload.psr-4`, and `require` with `php` plus every Magento package whose
  classes the module uses (`magento/module-catalog`). `<sequence>` only orders loading.
- **Where code goes:**

  | Code | Folder |
  |---|---|
  | Service contracts | `Api/`, `Api/Data/` |
  | Business logic, models | `Model/`, `Model/ResourceModel/` |
  | Interception, events | `Plugin/`, `Observer/` |
  | Entry points | `Controller/` (`Controller/Adminhtml/` for admin), `Console/Command/`, `Cron/` |
  | Template data | `ViewModel/` (a `Block/` only when it renders something a ViewModel can't) |
  | Patches | `Setup/Patch/Data/`, `Setup/Patch/Schema/` |
  | Config XML | `etc/` (global) and `etc/<area>/` (`frontend`, `adminhtml`, `webapi_rest`, `graphql`, `crontab`) |
  | Views | `view/<frontend\|adminhtml\|base>/{layout,templates,web,ui_component}` |
  | Translations, tests | `i18n/<locale>.csv`, `Test/Unit/` |

- **Controllers implement `HttpGetActionInterface`/`HttpPostActionInterface`.**
  `Magento\Framework\App\Action\Action` is `@deprecated` since 103.0.0 in favor of composition
  (`adobe-create-controller`).
- **No `Helper\Data` catch-all.** Put each responsibility in a named service under `Model/`, so it can
  be injected, intercepted and tested alone.
- **Commit `app/etc/config.php`** with the module enabled. Production deploys read that file; a
  module missing from it stays off.
- **Never rename a shipped module.** `setup_module` and `patch_list` key on the module and patch
  class names, so a renamed module reapplies or loses its patches. Create a new module and migrate
  data instead.
- **Remove in two releases.** First release: drop the module's tables and columns through
  `db_schema.xml` and the whitelist, and remove its config. Second: `module:disable` and delete the
  code. `module:uninstall` only works for Composer-installed modules, not `app/code`.

## Steps

1. Confirm no existing module owns the domain (Rules).
2. Copy the skeleton: `php "$T/magento-example.php" module` (`registration.php`, `composer.json`,
   `etc/module.xml`), replacing the reference names.
3. Add `<sequence>` only for modules whose tables, layout or config this one extends.
4. Build the layers (`adobe-magento-feature-developer`), each with its `adobe-create-*` skill.
5. `bin/magento module:enable Vendor_Module && bin/magento setup:upgrade && bin/magento setup:di:compile`.
6. Commit `app/etc/config.php` together with the module.

## Verify

- `bin/magento module:status Vendor_Module` prints it as enabled.
- `composer validate app/code/Vendor/Module/composer.json` passes.
- `bin/magento setup:db:status` reports everything up to date after `setup:upgrade`.
- `php "$T/magento-verify.php"` ends in `VERIFY OK`.

## Pitfalls

- `Class ... does not exist` for a new class -> module not enabled, or folder and namespace differ
  from the PSR-4 prefix; check `module:status` and the path.
- New plugin or preference ignored -> stale `generated/`; run `setup:di:compile`.
- Layout or config from your module overridden by core -> missing `<sequence>` on the module that
  declares the original.
- Module works locally but is off in staging -> `app/etc/config.php` not committed.
- Business logic in a `Block` or template -> move it to a service or ViewModel
  (`adobe-commerce-standards`, anti-patterns).

## Docs

- https://developer.adobe.com/commerce/php/development/build/component-file-structure/
- https://developer.adobe.com/commerce/php/development/build/component-registration/
- https://developer.adobe.com/commerce/php/development/build/composer-integration/
- https://developer.adobe.com/commerce/php/development/build/component-load-order/
