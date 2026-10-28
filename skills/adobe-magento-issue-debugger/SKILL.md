---
name: adobe-magento-issue-debugger
description: "Method to diagnose a Magento 2.4.8 error or wrong behavior: reproduce, read the log line for that reproduction (exception.log, system.log, debug.log, var/report), isolate the layer, fix the cause and prove it. Use for error pages, HTTP 500, REST/GraphQL errors, DI errors, changes that don't show up, failing cron or consumers. Infinite admin spinner: adobe-magento-uicomponent-debug."
---

# Magento debugging

Find the cause before changing code: one reproduction, the log entry it produces, the layer it comes
from. For the silent infinite spinner of an admin grid or form use `adobe-magento-uicomponent-debug`;
to see how a URL reaches controller, layout, template and JS use `magento-trace` from
`adobe-magento-toolbox` (`$T` below is that skill's scripts folder).

## Rules

- **Reproduce first** with the smallest command that fails (curl, a `bin/magento` command, one REST
  call). Without it the fix can't be proven.
- **Read only the entries of your reproduction**: note the time, then `tail`. Old entries mislead.
- **One hypothesis, one change, same reproduction.** Several changes at once hide the cause.
- **Fix the cause.** A try/catch that swallows, `@`, or disabling a module or plugin moves the failure
  elsewhere.
- **Remove debug aids before commit**: `var_dump`, temporary log lines, query log, profiler. They leak
  data and fill the disk.
- **On a shared environment, ask its owner before** `deploy:mode:set`, `cache:flush`, deleting
  `generated/` or full reindexes: they affect everyone and take minutes.

## Where to look

| Symptom | Look at |
|---|---|
| "There has been an error processing your request", record number N | `find var/report -name N`: stack trace |
| HTTP 500 or blank page, no report | `var/log/exception.log`, then the PHP-FPM or web server error log (fatal before Magento boots) |
| REST "Internal Error ... Report ID: webapi-..." | grep that id in `var/log/exception.log` |
| GraphQL `errors[]` | `message` and `extensions.category` in the response; internal errors in `exception.log` |
| Warning, failed cron, consumer or e-mail | `var/log/system.log` |
| Your `->debug()` lines | `var/log/debug.log`: written outside production mode, or after `bin/magento setup:config:set --enable-debug-logging=true` |
| A module's own log | the file of its logger virtualType (`adobe-inject-custom-logger`) |
| "does not exist", "Cannot instantiate interface", "Incompatible argument type" | DI: `magento-verify` gives file:line; `bin/magento setup:di:compile` lists all DI errors |
| Change doesn't show up | cache, stale generated code, compiled DI, static files (Pitfalls) |
| "Base table or view not found", unknown column | `bin/magento setup:db:status`, then `setup:upgrade` |
| Saved in admin, unchanged on storefront | `bin/magento indexer:status`, cron, full page cache |
| JS error, RequireJS "Script error for" | browser console; `magento-trace` marks the broken link with `X` |
| Slow page | `adobe-magento-performance-analyst` |

## Steps

1. Reproduce with one command; keep its output.
2. Read the matching entry. The first `app/code` frame is usually the place.
3. Is it your change? Run the same reproduction on the base branch. Fails there too: it's
   pre-existing; report it instead of fixing it silently.
4. Narrow the layer: `php "$T/magento-trace.php" <url>` for route, layout, template and JS;
   `bin/magento dev:query-log:enable` for SQL (`var/debug/db.log`); `php "$T/magento-api.php" 'FQCN'`
   for the real signature when a call doesn't match.
5. Suspect module, on a local or verification environment only: `bin/magento module:disable
   Vendor_Module`, reproduce, enable again.
6. Fix, rerun the same reproduction, and put the before and after output in the report.

## Verify

```bash
bin/magento deploy:mode:show
tail -n 50 var/log/exception.log
bin/magento setup:di:compile             # every DI error at once
bin/magento setup:db:status
bin/magento indexer:status
bin/magento queue:consumers:start CONSUMER_NAME --max-messages=1   # consumer error in the terminal
bin/magento dev:query-log:enable --include-all-queries=false --query-time-threshold=0.1
bin/magento dev:query-log:disable
curl -s -o /dev/null -w '%{http_code}\n' "$BASE_URL/path"
```

## Pitfalls

- Constructor changed on a class that has a plugin → the generated Interceptor copies the old
  constructor: delete `generated/code/Vendor/Module` (developer mode regenerates it).
- `di.xml` change ignored → if `generated/metadata/global.php` exists, Magento uses compiled DI in any
  mode: run `setup:di:compile` again or remove `generated/metadata`.
- XML change (layout, `system.xml`, `events.xml`) ignored → `bin/magento cache:clean config layout
  block_html full_page`, not a blanket flush.
- Customer data empty in a block only when the page cache is on → cacheable pages drop the customer
  session; see private content in `adobe-magento-performance-analyst`.
- "Area code is not set" in a CLI command or cron → the code needs an area: use
  `Magento\Framework\App\State::emulateAreaCode()` around the call.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/enable-logging
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/set-mode
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/code-compiler
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/manage-cache
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/manage-indexers
