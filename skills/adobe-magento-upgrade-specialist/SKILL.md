---
name: adobe-magento-upgrade-specialist
description: "Steps to upgrade Magento 2 / Adobe Commerce (patch -pN or minor) on-prem or on Cloud: composer require-commerce, B2B and third-party compatibility, services, quality patches, setup:upgrade/di:compile/static deploy, and a rehearsal on a database copy. Use when a task changes the Magento, B2B, ece-tools or a core-dependent package version."
---

# Magento version upgrade

How to move a project to a new Adobe Commerce / Magento Open Source release without breaking custom code. The deploy itself follows `adobe-magento-deployment-engineer`; what each command invalidates is in `adobe-commerce-standards`.

## Rules

- **Change versions with `composer require-commerce`** (plugin `magento/composer-root-update-plugin`), not by editing the version and running `composer update`: the plugin also updates the root `require-dev`, `autoload` and `conflict` sections the release expects.
  - On-prem: `composer require-commerce magento/product-enterprise-edition <target> --no-update` (or `product-community-edition`).
  - Cloud: `composer require-commerce "magento/magento-cloud-metapackage":">=CURRENT <NEXT>" --no-update` only when the target is outside the current constraint (a `-pN` of the same minor is inside). If the root also pins the edition package, move that pin with `require-commerce` in the same change.
  - Conflicting custom values: rerun with `--interactive-root-conflicts`.
- **Move what is tied to the core version in the same change**: `magento/extension-b2b` declares a narrow `magento/framework` range (1.5.3 accepts only `>=103.0.8 <103.0.10`), plus `magento/ece-tools` and third-party extensions. `composer why-not magento/product-enterprise-edition <target>` lists what blocks the target.
- **Check every third-party package**: `composer show -D` lists direct dependencies; for each non-Magento vendor, confirm in the vendor's release notes that it supports the target, and update it in the same commit.
- **Read the release notes of every version between current and target**, `-pN` included, and the system requirements: PHP, MariaDB/MySQL, OpenSearch, Valkey/Redis, RabbitMQ, Varnish and Composer change between releases. Upgrade the services first (Cloud: `.magento/services.yaml` and `type: php:` in `.magento.app.yaml`).
- **Prune patches**: a quality patch already included in the target fails to apply and breaks the build. Check `vendor/bin/magento-patches status` (on-prem) or `vendor/bin/ece-patches status` (Cloud), then remove included or `Deprecated` ids from `QUALITY_PATCHES` in `.magento.env.yaml`, and redo or drop custom patches (`m2-hotfixes/` on Cloud).
- **Commit the refreshed copies of `magento/magento2-base` files** (`app/etc/di.xml` and the others the installer copies). A stale committed `app/etc/di.xml` keeps old behavior, for example rejecting a DB version the new release supports.
- **Rehearse on a database copy, never on a shared database.** Run `mysqldump --single-transaction --quick --routines --triggers <db>`, strip `DEFINER=` when importing without SUPER, import into a new database, and point a separate checkout's `env.php` (`db/connection/default/dbname`) at it.

## Steps

1. Inventory: `bin/magento --version`, `composer show magento/product-enterprise-edition magento/extension-b2b magento/ece-tools`, `composer show -D`.
2. Read the release notes and system requirements, then plan the service changes.
3. On a branch: `require-commerce` plus B2B and third-party updates `--no-update`; `composer update --dry-run`; `composer update`.
4. Prune patches and refresh the base-file copies. Commit `composer.json`, `composer.lock` and those files.
5. On the database copy: `bin/magento setup:upgrade --dry-run=1` (writes the SQL to `var/log/dry-run-installation.log`), then `bin/magento setup:upgrade`. Use `--safe-mode=1` when columns are dropped (`--data-restore=1` brings the data back).
6. `bin/magento setup:di:compile`, `bin/magento setup:static-content:deploy -f <locales>`, `bin/magento indexer:reindex`, `bin/magento cache:flush`.
7. Run Verify, then deploy through the normal pipeline.

## Verify

- `bin/magento --version` shows the target; `bin/magento setup:db:status` prints "All modules are up to date." (exit code 2 means patches are pending).
- `php "$T/magento-verify.php" --path app/code/<Vendor>` before and after the update (`$T` is the `scripts/` folder of `adobe-magento-toolbox`). Items that only appear afterwards are what the upgrade broke: removed classes and XML outside the new XSD. Signatures: `php "$T/magento-api.php" '<FQCN>'`.
- `vendor/bin/magento-patches status` or `ece-patches status`: nothing `N/A`, no applied `Deprecated` patch.
- `diff app/etc/di.xml vendor/magento/magento2-base/app/etc/di.xml` is empty.
- Smoke test: home, product page, add to cart, checkout, admin login and the critical REST/GraphQL routes (`curl -s -o /dev/null -w '%{http_code}' <url>`).

## Pitfalls

- `composer update` cannot resolve `magento/framework` -> B2B or an extension left on the old version; move it together.
- "Current version of RDBMS is not supported" -> DB server outside `SqlVersionProvider` in `app/etc/di.xml`: check the requirements and refresh the stale copy. Never widen the pattern only in production.
- `setup:upgrade` stops at search engine validation -> OpenSearch unreachable or unsupported version.
- `di:compile` "Incompatible argument type" in a plugin or preference -> the core signature changed; read it with `magento-api`.
- Theme overrides of core templates keep the old markup and miss upstream fixes -> diff each overridden `Magento_*` template against the new vendor file and port the changes.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-operations/upgrade-guide/overview
- https://experienceleague.adobe.com/en/docs/commerce-on-cloud/user-guide/develop/upgrade/commerce-version
- https://experienceleague.adobe.com/en/docs/commerce-operations/release/notes/overview
- https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements
- https://experienceleague.adobe.com/en/docs/commerce-operations/tools/quality-patches-tool/usage
- https://experienceleague.adobe.com/en/docs/commerce-operations/upgrade-guide/upgrade-compatibility-tool/overview
