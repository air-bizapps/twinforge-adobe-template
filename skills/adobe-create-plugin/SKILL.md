---
name: create-plugin
description: "Scaffold a Magento 2 plugin (interceptor) in a Webjump/* module — the Plugin/{...}/{ClassName}.php class (before/after/around), its etc/di.xml wiring, and (only when genuinely needed) an etc/module.xml sequence. Use to intercept, extend, or hook into an existing public method without editing its source — 'add a plugin to intercept X::save', 'validate arguments before they reach this method', 'short-circuit this action'. Adapted from a sibling team's skill (FTD B2C) — content is core Magento interception mechanics, no shared-framework dependency."
---

# Create Plugin

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-plugin` skill. No
Bunge-specific rewrite needed for the mechanics — plugin eligibility and interception are
core Magento behavior. Naming convention changed to `webjump_{module}_{description}`
(from the source's `ftd_{module}_{description}`); dropped the source's prohibition on
using a specific other team's modules as reference (not applicable to Bunge).

## 1. Scope contract

**Expected input, all confirmed, never guessed:** target module, the exact class/interface
and public method being intercepted (confirmed eligible by reading the real class, never
inferred from its name), the logic the plugin implements, before/after/around, the
`sortOrder` relative to any existing plugin on the same method, and the area scope.

**Produces:** the plugin class, the `etc/di.xml` (or area-specific) `<type>`/`<plugin>`
block, and — only when genuinely needed — an `etc/module.xml` `<sequence>` entry.

**Out of scope:** the business logic being intercepted itself, beyond what's described;
a new route/controller (→ `create-controller`) or admin config (→ `create-config`) used
as a substitute for a plugin when a plugin is really what's needed; unit tests (→
`create-unit-tests`).

## 2. Golden rules

1. **Only a public, non-final method of a non-final class/interface is an eligible
   target.** The interceptor is a generated subclass — it can't override `final`,
   private/protected, static, a constructor/destructor, a virtualType, or a class
   implementing `NoninterceptableInterface`.
2. **The target must be reached via dependency injection — never `new`'d directly in the
   path that matters.** This is the most common reason a plugin "doesn't fire."
3. **`before*` returns an array of arguments in the original order; `after*` always
   returns a value; `around*` forwards every original parameter to `$proceed(...)`** and
   only skips calling it for a deliberate short-circuit.
4. **Never fabricate an `etc/module.xml` `<sequence>` entry.** Only real, user-confirmed
   load-order coupling justifies one — never name similarity between modules.
5. **Check existing plugins on the same method before choosing `sortOrder`.** Stack
   consistently with what's already there instead of guessing.

## 3. Eligibility (hard rules) — check by reading the real class, never inferring

| Rule | Why | Instead |
|---|---|---|
| Never plug a **final class** or **final method** | The interceptor is a generated subclass — it cannot override anything `final` | Confirm the target isn't final first; if it is, consider a preference, an observer, or a different approach |
| Never plug a **private or protected method** | Only `public` methods are interceptable | Target the class/interface's public contract |
| Never plug a **static method** | Plugins only observe instance calls | Propose an instance-method refactor or a different extension point |
| Never plug `__construct()`/`__destruct()` | Constructors/destructors can never be intercepted, by design | Use a different extension point |
| Never plug a **virtualType** | Not a concrete class Magento can subclass | Target the concrete class the virtualType wraps |
| Never plug a class implementing `NoninterceptableInterface` | The class explicitly opts out of interception | No plugin is possible; use whatever extension point that class exposes |
| Never scaffold a plugin against a target reached via `new` instead of DI in the path that matters | The interceptor never sits between the caller and a `new`'d object — the plugin exists, compiles, and silently never fires | Verify how the target is actually obtained (`grep -rn "new {ClassName}(" app/code`) before assuming a plugin is the right tool |

Verify before delivering:
```bash
grep -n "final class\|final function\|final public function" <path-to-target-class>
grep -rn "new {ClassName}(" app/code
```
Read the actual target class — never infer eligibility from its name or how a similar
class behaves elsewhere. Any check failing → don't write the plugin; name which
eligibility rule failed and offer the eligible alternative.

## 4. Never fabricate coupling (hard rule)

Adding an `etc/module.xml` `<sequence>` entry because two module names "seem related" is
forbidden — a wrong load order silently breaks DI wiring for other developers and is hard
to trace back. Only add one for coupling the requester has explicitly confirmed (e.g. this
plugin's constructor genuinely needs config/data another module sets up).

Disabling another module's plugin by guessing its `name` is also forbidden — a mismatched
`name`/`<type name>` silently does nothing while the plugin keeps running. Find and match
the exact original declaration first: `grep -rn "plugin name=\"{name}\"" vendor/ app/code`.

## 5. Autonomy tiers

**Green — proceed directly:** a new plugin + `di.xml` wiring against a confirmed-eligible
target no existing plugin touches yet; a `sortOrder` chosen after inspecting existing
plugins on the same method, leaving a gap for future insertions; mirroring the intercepted
class's namespace inside `Plugin/`.

**Yellow — proceed, declare the decision in the report:** adding a plugin on a method
already targeted by another plugin (report the `sortOrder` chosen and why); choosing
`around` instead of `before`/`after` (report why a short-circuit/wrap was genuinely
necessary); scoping the plugin to a specific area instead of global `di.xml`.

**Red — never without explicit confirmation:** adding an `etc/module.xml` `<sequence>`;
scaffolding against a target that fails section 3's eligibility; disabling another
module's plugin.

## 6. Definition of done

- [ ] Target confirmed eligible by reading the real class (section 3) — not inferred
- [ ] Target confirmed reached via DI, not `new`'d in the relevant path
- [ ] `before`/`after`/`around` chosen correctly, with `around` only when genuinely
      justified and declared in the report
- [ ] Plugin class namespace mirrors the intercepted class's FQCN inside `Plugin/`
- [ ] `sortOrder` chosen consistently with any existing plugin on the same method
- [ ] No `<sequence>` fabricated from name similarity; no plugin disabled by guessing
- [ ] `setup:di:compile` + `cache:flush` succeed; the plugin's effect manually confirmed
      (a temporary debug log, or the actual behavior change observed)
- [ ] Report names `create-unit-tests` on the new plugin as the next step

## Antipatterns

- Writing a plugin against a `final`, private, static method, or a constructor —
  compiles or fails silently, but never fires.
- Assuming the target is reached via DI without checking `grep -rn "new {ClassName}("` —
  the plugin exists, compiles, and never runs.
- Defaulting to `around` instead of `before`/`after` — adds unneeded complexity when no
  short-circuit or wrap is actually required.
- Adding `<sequence>` to `etc/module.xml` "just in case" — only real, confirmed coupling.
- Disabling another module's plugin by guessing its `name` — the original keeps running
  while the developer believes it's off.
