---
name: inject-debug-logger
description: "Inject a feature-flag-gated debug logger into a class — the same Handler+Logger virtualType pair as inject-custom-logger, plus a small Handler::isHandling() override checking the module's own Config provider (from create-config), an admin on/off toggle in system.xml, and $this->logger->debug() calls through the class. Stays silent in production until an admin flips the flag. Use when a class needs debug tracing that costs nothing while off. Adapted from a sibling team's skill (FTD B2C); their FeatureFlagHandler/DebugConfiguration classes replaced with a small local Handler override plus the module's own Config provider, since Bunge has no equivalent shared framework."
---

# Inject Debug Logger

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `inject-debug-logger` skill.
The source assembles this entirely from `FTD\Framework`'s own `DebugConfiguration` +
`FeatureFlagHandler` virtualTypes. Bunge has no equivalent, so this version builds the
same behavior from two things already established in this codebase: the
`Handler`/`Logger` pair from `inject-custom-logger` (itself based on the real
`Webjump\BungePriceSimulationLogger` module), plus a small local `Handler::isHandling()`
override that checks the module's own `Config` provider (the `create-config` skill's
pattern) — no new shared infrastructure needed.

## 1. Scope contract

**Expected input:** the target class (already existing), the scope of instrumentation
(whole class vs. specific methods), and the module's existing debug flag path if one
already exists — confirmed by reading the module first, never guessed.

**Produces:** a `DEBUG_ENABLED` config-path constant on the module's `ConfigInterface`, a
gated `Handler` (extends `Base`, overrides `isHandling()`) + `Logger` virtualType pair
(declared once per module), the consumer binding, the `system.xml` admin toggle, the
`LoggerInterface` constructor injection, and `debug()` calls through the class.

**Out of scope:** creating the target class/module; touching any existing
`error`/`warning`/`info` call; unit tests for the instrumented class (→
`create-unit-tests`).

## 2. Golden rules

1. **The consumer depends only on `Psr\Log\LoggerInterface`** — never the concrete gated
   channel class.
2. **The gated logger infrastructure is declared once per module.** A second class to
   instrument only adds a `<type>` binding and its own `debug()` calls — reuse the
   existing channel.
3. **`debug()` is always additive.** Never downgrade or remove an existing
   `error()`/`warning()`/`info()` call to `debug()` — those keep firing regardless of the
   flag.
4. **The config path is always a constant, never a string literal.** Reference
   `ConfigInterface::DEBUG_ENABLED` via `xsi:type="const"`, never an inline path.
5. **Never log a secret or personal data.**

## 3. The pattern to follow

Build on `inject-custom-logger`'s Handler/Logger pair, adding a config-gated
`isHandling()` override:

```php
namespace Webjump\{Module}\Logger;

use Magento\Framework\Logger\Handler\Base;
use Webjump\{Module}\Api\ConfigInterface;

class DebugHandler extends Base
{
    protected $fileName = '/var/log/{module_snake_case}_debug.log';

    public function __construct(
        private readonly ConfigInterface $config,
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        $filePath = null
    ) {
        parent::__construct($filesystem, $filePath);
    }

    public function isHandling(array $record): bool
    {
        return $this->config->isDebugEnabled() && parent::isHandling($record);
    }
}
```

The `Logger` virtualType and `di.xml` wiring are otherwise identical to
`inject-custom-logger`'s pattern, pointed at `DebugHandler` instead of the plain
`Handler`. Add a `system.xml` `debug`/`enabled` field (via `create-config`'s pattern) and
a `isDebugEnabled(): bool` method on the module's `ConfigInterface`/`Config`, with
`DEBUG_ENABLED` as a `const` holding the config path.

## 4. Definition of done

- [ ] Module's existing debug flag path (if any) checked before creating a new one
- [ ] `DEBUG_ENABLED` is a `const` on `ConfigInterface`, referenced via `xsi:type="const"`
      in `di.xml` — never a string literal
- [ ] `DebugHandler::isHandling()` gates on the config, not a hardcoded value
- [ ] No existing `error`/`warning`/`info` call was downgraded to `debug()`
- [ ] Consumer depends only on `Psr\Log\LoggerInterface`
- [ ] `system.xml` toggle present under the module's own ACL resource (per `create-config`)
- [ ] No secret or raw PII logged
- [ ] `setup:di:compile` + `cache:flush` succeed; toggled on → log line appears; toggled
      off → nothing written, confirmed both ways
- [ ] Report names where the admin toggle lives (Stores > Configuration path) and
      `create-unit-tests` as the next step for any new PHP

## Antipatterns

- `new`-ing a Monolog logger or injecting the concrete gated channel class instead of
  `Psr\Log\LoggerInterface`.
- Declaring a second gated-logger infrastructure in a module that already has one.
- Downgrading an existing `error`/`warning`/`info` call to `debug()` instead of adding
  `debug()` as an extra layer.
- Hardcoding the config path as a string instead of referencing the `DEBUG_ENABLED`
  constant.
- Logging a token, password, card data, or any raw PII.
- Creating the target class/module as part of this skill — it only instruments what
  already exists.
