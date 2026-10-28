---
name: adobe-magento-local-environment-specialist
description: "Running Magento 2.4.8 on a developer machine without assuming Docker: native services, PHP built-in server with the router, a database copy, developer mode settings, Xdebug, a second checkout for verification, and fixing local setup errors. Use when setting up or repairing a local environment or proving code locally. Service flags and env.php in general: adobe-magento-environment-engineer."
---

# Local Magento environment

A working Magento on a laptop, with whatever services the machine has: native packages, a managed
service, or containers if the team already uses them. Nothing here requires Docker. How `env.php` and
`setup:config:set` work in any environment: `adobe-magento-environment-engineer`.

## Rules

- **Find what is already running before installing anything**: `php -v` and `php -m`, `mysql --version`,
  `curl -s 127.0.0.1:9200`, `redis-cli ping`. Point `env.php` at those; install only what is missing.
- **Match production versions where it changes behavior**: PHP 8.3 with the `ext-*` listed in
  `composer.json` (`composer check-platform-reqs`), MariaDB/MySQL and OpenSearch 2.x majors as in
  production. A different PHP minor hides deprecations; a different OpenSearch major changes search.
- **Required services are database, OpenSearch and PHP.** Redis is optional (file cache works),
  RabbitMQ only if a module publishes to `amqp` (otherwise the `db` queue connection serves), Varnish
  only to test purges.
- **Web server without nginx**: `php -S 127.0.0.1:8080 -t pub phpserver/router.php` from the project
  root. Without the router, static files and media return 500. Raise memory for big pages:
  `php -d memory_limit=2G -S ...`.
- **Base URL matches the server**: `system/default/web/unsecure/base_url` and `secure/base_url` in
  `env.php` (`http://127.0.0.1:8080/`); a wrong one redirects every request to the old host.
- **Use a copy of a real database, not production itself**: dump without `DEFINER` clauses
  (`` mysqldump --no-tablespaces ... | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' ``), or the import needs SUPER. Scrub
  customer data if the dump comes from production.
- **Developer mode locally** (`bin/magento deploy:mode:set developer`): templates, layout and JS are
  read on demand. Keep `full_page` and `block_html` cache enabled when testing caching
  (`adobe-magento-cache-analyst`); disable them only while editing templates.
- **Admin access locally**: `bin/magento admin:user:create`, and disable two-factor auth only on the
  local copy (`module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth`). That change
  lands in `config.php`; never commit it.
- **Xdebug only when stepping through**: `php -d xdebug.mode=debug -d xdebug.start_with_request=yes`
  for one command, or `XDEBUG_SESSION` cookie for web. Left on, it slows every request several times.
- **A second checkout for verification** (another branch, an automated check) gets its own database,
  OpenSearch `_index_prefix` and cache `id_prefix`. Share `vendor/` with hardlinks (`cp -al`), not a
  symlink: the autoloader would register the modules of the other checkout.
- **Modules under test are copied into `app/code`**, not symlinked: Magento refuses templates whose real
  path is outside the project root.

## Steps

1. Inventory services and versions; install what is missing.
2. `composer install`; `app/etc/env.php` from `setup:config:set` (or the team template) pointing at
   local services.
3. Import a database copy; set base URLs and OpenSearch prefix.
4. `bin/magento setup:upgrade`, `deploy:mode:set developer`, `indexer:reindex`.
5. Start the PHP server with the router; create an admin user.

## Verify

```bash
composer check-platform-reqs
bin/magento setup:db:status            # "All modules are up to date."
bin/magento indexer:status
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/          # 200
curl -s http://127.0.0.1:8080/ | grep -o 'http[^"]*/static/[^"]*\.css' | head -1 | xargs curl -s -o /dev/null -w '%{http_code}\n'   # 200, not 500
```

## Pitfalls

- `setup:upgrade` fails on "Could not validate a connection to the OpenSearch" -> OpenSearch not running or
  wrong host/port in `catalog/search`.
- Every static file answers 500 under `php -S` -> started without `phpserver/router.php`.
- Redirect loop to another host -> base URL in the database copy; override it in `env.php`.
- Storefront without products after the import -> indexes empty; run `indexer:reindex`.
- `Area code is not set` or stale classes after switching branches -> `rm -rf generated/code generated/metadata` and retry.
- Import fails with "Access denied; you need SUPER" -> `DEFINER` clauses in the dump; strip them.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements
- https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/prerequisites/search-engine/overview
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/set-mode
- https://developer.adobe.com/commerce/php/development/build/component-file-structure/
- https://xdebug.org/docs/step_debug
