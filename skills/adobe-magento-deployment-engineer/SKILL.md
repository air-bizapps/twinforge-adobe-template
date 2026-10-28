---
name: adobe-magento-deployment-engineer
description: "How Magento 2.4.8 code reaches an environment: application modes, build versus deploy, on-prem production deploy order and rollback, CI pipeline steps, Adobe Commerce Cloud (ece-tools, .magento.app.yaml hooks, .magento.env.yaml, magento-cloud CLI). Use for deploy, release or pipeline tasks. env.php, services and config per environment: adobe-magento-environment-engineer; local setup: adobe-magento-local-environment-specialist."
---

# Magento deployment

Modes, deploy order, pipelines and Cloud. What each command invalidates (`setup:upgrade`, `di:compile`, static deploy, cache types) is in `adobe-commerce-standards`. `config.php`/`env.php`, `app:config:dump` and service connections: `adobe-magento-environment-engineer`. A local machine: `adobe-magento-local-environment-specialist`. A `.magento.app.yaml` at the repo root means Adobe Commerce Cloud.

## Rules

- **Modes**: `developer` locally (errors shown, code and static files generated on demand), `production` everywhere else; never `default`. Check with `bin/magento deploy:mode:show`. `deploy:mode:set production` compiles and deploys static content itself; add `--skip-compilation` when the pipeline already did. On Cloud, ece-tools manages the mode.
- **Build once, deploy the same artifact everywhere.** The pipeline builds `vendor/`, `generated/` and `pub/static` one time and promotes that artifact to staging and production; rebuilding per environment ships code that was never tested.
- **The pipeline runs, in order**: `composer validate`, `composer install --no-dev`, static checks and unit tests (`adobe-run-unit-tests`), `composer audit`, then the build of step 1 below. A failing step stops the release.
- **The build needs no database** once `config.php` has `scopes` and `themes` (`bin/magento app:config:dump scopes themes`); otherwise static deploy can't know the themes and locales.
- **On-prem production deploy, in this order**:
  1. Build where the site isn't running: `composer install --no-dev`, `setup:di:compile`, `composer dump-autoload -o`, `setup:static-content:deploy <locales> --jobs <n>`.
  2. `bin/magento maintenance:enable`, then stop cron and consumers.
  3. Switch the code, then `bin/magento setup:upgrade --keep-generated`. Without that flag it deletes `generated/` in production.
  4. `bin/magento cache:flush`, `bin/magento maintenance:disable`, then restart consumers.
  5. Shortcut: if `setup:db:status` and `app:config:status` are up to date, skip step 3 and the maintenance window.
- **Switch code with a release directory and a symlink**, with `pub/media`, `var/` and `app/etc/env.php` shared outside the release. The switch is atomic and the previous release stays for rollback.
- **Rollback**: switching the symlink back is enough only when the release ran no schema or data patch. Otherwise restore the database backup taken before step 3: patches are not reverted on rollback.
- **On Cloud, never run deploy commands on the server**: the `.magento.app.yaml` hooks run ece-tools.
  - Build: `scenario/build/generate.xml` and `transfer.xml` apply patches, run `di:compile` and static deploy.
  - Deploy: `scenario/deploy.xml` covers maintenance, `setup:upgrade` and cache.
  - Post-deploy: `scenario/post-deploy.xml` warms the cache.
- **Change Cloud only through files and variables**:
  - `.magento.env.yaml`: `SCD_MATRIX`, `SCD_THREADS`, `QUALITY_PATCHES`, `CRON_CONSUMERS_RUNNER`, `SEARCH_CONFIGURATION`, `WARM_UP_PAGES`.
  - `.magento/services.yaml`: service versions.
  - `.magento.app.yaml`: PHP, extensions, hooks, crons, mounts.
  - `magento-cloud variable:create` / `variable:update`: secrets and `CONFIG__` overrides.
  - `env.php` is regenerated at deploy, and only the mounts (`var`, `app/etc`, `pub/media`, `pub/static`) are writable.
- **Cloud static deploy runs in the build phase** (shorter downtime) only if `config.php` has `scopes` (`php vendor/bin/ece-tools config:dump`) and `SCD_ON_DEMAND`/`SKIP_SCD` are off.
- **Quality patches** go through `QUALITY_PATCHES` on Cloud and `vendor/bin/magento-patches apply <id>` on-prem, both committed, so every environment carries the same fixes.

## Steps

1. Confirm target (on-prem or Cloud) and whether the release has patches (`setup:db:status`).
2. Pipeline: checks, tests, build once.
3. Back up the database if patches run; deploy in the order above.
4. Smoke test, then watch logs for the first minutes.

## Verify

- `bin/magento deploy:mode:show`; `bin/magento app:config:status` prints "Config files are up to date."; `bin/magento setup:db:status` prints "All modules are up to date."
- `curl -s -o /dev/null -w '%{http_code}' <base_url>` is 200 after `maintenance:disable`; `bin/magento queue:consumers:list` and `crontab -l` show the restarted runners.
- Cloud: `php vendor/bin/ece-tools cloud:config:validate` (`.magento.env.yaml`), `php vendor/bin/ece-tools error:show` and `var/log/cloud.log` after a deploy, `magento-cloud log error`.

## Pitfalls

- `env.php` edited over SSH on Cloud is lost at the next deploy -> use `.magento.env.yaml` or variables.
- Production `setup:upgrade` without `--keep-generated` -> site down until `di:compile` runs.
- Consumers running through a deploy keep old code in memory -> stop them before and restart after.
- `developer` mode left in production -> slow and exposes errors; check `deploy:mode:show`.
- Static deploy in the pipeline fails with a database error -> `scopes`/`themes` missing from `config.php`.
- Rollback by symlink after a schema change -> old code on a new schema; restore the backup.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/setup/application-modes
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/deployment/technical-details
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/deployment/overview
- https://experienceleague.adobe.com/en/docs/commerce-on-cloud/user-guide/dev-tools/ece-tools/package-overview
- https://experienceleague.adobe.com/en/docs/commerce-on-cloud/user-guide/configure/env/configure-env-yaml
- https://experienceleague.adobe.com/en/docs/commerce-on-cloud/user-guide/dev-tools/cloud-cli/cloud-cli-overview
- https://experienceleague.adobe.com/en/docs/commerce-operations/tools/quality-patches-tool/usage
