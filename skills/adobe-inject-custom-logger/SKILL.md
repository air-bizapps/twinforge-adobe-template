---
name: inject-custom-logger
description: "Inject a dedicated, always-on custom log file into a class, following the real Webjump\\BungePriceSimulationLogger pattern already in this codebase — a Handler (extends Magento\\Framework\\Logger\\Handler\\Base) + a Logger (extends Monolog\\Logger) virtualType pair, wired via di.xml, injected as plain Psr\\Log\\LoggerInterface. Use whenever a class needs its own log file that always writes, no admin toggle. If tracing should stay silent until an admin flips a flag, use inject-debug-logger instead. Adapted from a sibling team's skill (FTD B2C) — this one already matches Bunge's own real pattern exactly, no framework dependency at all."
---

# Inject Custom Logger

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `inject-custom-logger`
skill. **No FTD-Framework dependency here at all** — this is vanilla Magento
(`Magento\Framework\Logger\Handler\Base` + `Monolog\Logger`), and Bunge already has a
real, working example of exactly this pattern:
`Webjump\BungePriceSimulationLogger\Logger\{Handler,Logger}.php`. Use that module as the
worked example instead of anything from the FTD source.

## 1. Scope contract

**Expected input:** the target class (already existing), whether it needs its own file or
can share an existing channel, and what's actually worth logging — confirmed, since
nothing gates this logger and every call is permanent production output.

**Produces:** the module-wide `di.xml` logger infrastructure (file handler + Monolog
channel, declared once per log file), the per-class consumer binding, the
`LoggerInterface` constructor injection, and log calls at genuinely high-signal points.

**Out of scope:** creating the target class/module; adding an admin on/off toggle (→
`inject-debug-logger` instead — ask if ambiguous which one is wanted); unit tests (→
`create-unit-tests`).

## 2. Golden rules

1. **The consumer depends only on `Psr\Log\LoggerInterface`** — never `new Monolog\Logger(...)`
   directly, never the concrete channel class.
2. **Never add a feature-flag/config toggle here.** That's `inject-debug-logger`'s job —
   use it instead if the request wants silence-until-admin-flips-a-flag behavior.
3. **Never let a virtualType name collide silently.** Magento doesn't error on a
   duplicate name — the most recent declaration simply wins for every consumer.
4. **This logger is unconditional — log with restraint.** Every call is permanent
   production output with no way to silence it short of a code change.
5. **Never log a secret or personal data.** Token, password, card data, raw PII — log an
   identifier or boolean, never the secret itself.

## 3. The real pattern to follow

```php
// Webjump\{Module}\Logger\Handler
namespace Webjump\{Module}\Logger;

use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    protected $fileName = '/var/log/{module_snake_case}.log';
}
```
```php
// Webjump\{Module}\Logger\Logger
namespace Webjump\{Module}\Logger;

class Logger extends \Monolog\Logger
{
}
```
```xml
<type name="Webjump\{Module}\Logger\Handler">
    <arguments>
        <argument name="filesystem" xsi:type="object">Magento\Framework\Filesystem\Driver\File</argument>
    </arguments>
</type>
<type name="Webjump\{Module}\Logger\Logger">
    <arguments>
        <argument name="name" xsi:type="string">{module_snake_case}</argument>
        <argument name="handlers" xsi:type="array">
            <item name="system" xsi:type="object">Webjump\{Module}\Logger\Handler</item>
        </argument>
    </arguments>
</type>
```
This is the exact shape of `Webjump\BungePriceSimulationLogger\Logger\{Handler,Logger}`
already in this codebase (writes to `var/log/price_simulation.log`) — read that module as
the worked example rather than reconstructing this from memory each time.

Bind the channel to the target consumer's `LoggerInterface $logger` constructor argument
via a `<type name="Webjump\{TargetModule}\{Path}\{Consumer}">` argument override, same as
any other constructor-argument binding.

## 4. Definition of done

- [ ] Checked whether the module already has a `Logger\Monolog` channel (this pattern or
      `inject-debug-logger`'s) before declaring a new one — no silent name collision
- [ ] Handler + Logger virtualTypes declared with an explicit channel `name`
- [ ] Consumer depends only on `Psr\Log\LoggerInterface`, bound via `di.xml`
- [ ] Log calls placed at genuinely high-signal points (operation entry/exit, external
      call result, inside a catch) — not exhaustive, not on every trivial statement
- [ ] No secret or raw PII logged
- [ ] `setup:di:compile` + `cache:flush` succeed; a log line manually confirmed written
      to the expected file
- [ ] Report names `create-unit-tests` as the next step if new PHP was written beyond the
      Handler/Logger pair (which themselves need no test — pure configuration classes)

## Antipatterns

- `new`-ing a Monolog logger or injecting the concrete channel class instead of depending
  on `Psr\Log\LoggerInterface`.
- Adding a feature-flag/config toggle to this channel — that's `inject-debug-logger`'s job.
- Redeclaring a `Logger\Monolog` virtualType a module already has instead of reusing or
  giving it a disambiguated name.
- Omitting the explicit channel `name` — it silently falls back to `"main"`.
- Logging every trivial statement with no branch or external call — defeats the purpose.
- Logging a token, password, card data, or any raw PII.
