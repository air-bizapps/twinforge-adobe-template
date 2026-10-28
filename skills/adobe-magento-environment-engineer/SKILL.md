---
name: adobe-magento-environment-engineer
description: "Magento 2.4.8 environment configuration: config.php versus env.php, app:config:dump and import, sensitive and per-environment values (config:set --lock-env, CONFIG__ variables), connecting services (MariaDB, OpenSearch, Redis/Valkey, RabbitMQ, Varnish, lock provider) with setup:config:set. Use when setting up or changing an environment's configuration. Deploy order and Cloud: adobe-magento-deployment-engineer; a laptop: adobe-magento-local-environment-specialist."
---

# Magento environments and configuration files

Which configuration lives where, how it moves between environments, and how Magento connects to its
services. Deploy order, modes and Cloud hooks: `adobe-magento-deployment-engineer`. A local machine:
`adobe-magento-local-environment-specialist`. Cache backend tuning: `adobe-magento-cache-analyst`.

## Rules

**Configuration files**
- **`app/etc/config.php` is shared and committed**: module list, scopes, themes and config every
  environment shares. Commit it after `module:enable`/`module:disable`.
- **`app/etc/env.php` is per environment and never committed**: DB, cache, session, queue, lock, crypt
  key, mode, `http_cache_hosts` and environment-specific config.
- **Precedence**: environment variable, then `env.php`, then `config.php`, then the database
  (`core_config_data`). A value set higher is locked (greyed out) in the Admin.
- **Moving Admin config into files**:
  - `bin/magento app:config:dump [scopes themes system i18n]` writes shared values to `config.php` and
    environment-specific ones to `env.php`.
  - It does not export sensitive values; it lists their paths. Set those per environment with
    `bin/magento config:sensitive:set` or an environment variable `CONFIG__DEFAULT__<PATH>`
    (`CONFIG__WEBSITES__<CODE>__<PATH>` for a website), with `/` turned into `__`.
  - `config:set --lock-env` or `--lock-config` writes one value to a file. Dump only what must be
    identical everywhere.
  - `setup:upgrade` runs `app:config:import` itself.
- **Your own secrets** in `system.xml` are declared sensitive so a dump keeps them out of
  `config.php`: `adobe-create-config`.
- **The crypt key is per environment and must survive**: values encrypted in the database are
  unreadable with another key. Copying a database between environments means copying its key or
  re-saving the encrypted values.
- **Base URLs per environment** go in `env.php` (`system/default/web/unsecure/base_url` and
  `secure/base_url`, website scopes where they differ), never in `config.php`.

**Services**: connect with `setup:config:set`, which writes `env.php`; on Cloud ece-tools does it.

| Service | Required | How Magento points to it |
|---|---|---|
| MariaDB/MySQL | yes | `--db-host= --db-name= --db-user= --db-password=` (`db/connection/default`) |
| OpenSearch 2.x | yes, `setup:upgrade` validates it | `config:set catalog/search/engine opensearch`, `catalog/search/opensearch_server_hostname`, `_port`, `_index_prefix` (or `env.php` `system/default/catalog/search`) |
| Redis/Valkey | production | `--cache-backend=redis --page-cache=redis --session-save=redis` with `-redis-server`/`-redis-db` options; details in `adobe-magento-cache-analyst` |
| RabbitMQ | if modules publish to `amqp` | `--amqp-host= --amqp-port= --amqp-user= --amqp-password= --amqp-virtualhost=`; consumers in `adobe-create-queue` |
| Varnish | production | `--http-cache-hosts=<host:port>`, `system/full_page_cache/caching_application` = 2 |
| Lock provider | several web or cron nodes | `--lock-provider=db` (default) or `file` with `--lock-file-path` on shared storage, `zookeeper` |

- **Each environment gets its own database, OpenSearch index prefix, cache `id_prefix` and RabbitMQ
  virtual host** when services are shared; otherwise staging writes reach production indexes or caches.
- **Cron runs on one node** (`bin/magento cron:install` there only), or jobs run twice; consumers run
  from cron (`cron_consumers_runner` in `env.php`) or a process manager, not both.
- **Document root is `pub/`** (`--document-root-is-pub=true`): serving the project root exposes
  `app/etc/env.php`.
- **Non-production environments** get scrubbed database copies (customer emails, payment
  credentials), outgoing email disabled (`system/smtp/disable` = 1) and payment methods in sandbox, so
  tests never reach real customers or charge real cards.

## Steps

1. Decide per value: shared (`config.php`), per environment (`env.php` or variable), sensitive.
2. Connect services with `setup:config:set`; distinct prefixes when shared.
3. Dump or lock config, commit `config.php`, never `env.php`.

## Verify

- `bin/magento app:config:status` prints "Config files are up to date."
- `bin/magento config:show <path>` shows the effective value; `config:show --scope=websites --scope-code=<code> <path>` for a website.
- `curl -s <opensearch-host>:9200` answers; `bin/magento queue:consumers:list`; `bin/magento setup:db:status`.
- `git ls-files app/etc/env.php` prints nothing.

## Pitfalls

- Admin field greyed out -> its value lives in `config.php`, `env.php` or a variable; change it there.
- Secret appeared in `config.php` after `app:config:dump` -> path not declared sensitive; move it and rotate it.
- Encrypted values blank after a database copy -> different crypt key.
- Staging and production share an OpenSearch prefix -> reindex in one overwrites the other.
- Crons duplicated after scaling -> `cron:install` on every node.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/files/config-reference-envphp
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/deployment/technical-details
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/paths/override-config-settings
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/reference/config-reference-sens
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/storage/lock-provider
