---
name: create-import-entity
description: "Scaffold a custom Magento 2 CSV import entity (a row in System > Data Transfer > Import's Entity Type dropdown) for an entity that already has persistence in a Webjump/* module. Produces etc/import.xml, a Model/Import/{Entity} adapter, one Command class per import behavior, cached Storage lookup adapters, a sample CSV, i18n, and di.xml wiring, in a companion {EntityModule}ImportExport module — row validation delegated to inject-validator once its shared-framework question resolves, never written by hand. Use for bulk CSV upload/delete of a custom entity. Adapted from a sibling team's skill (FTD B2C); dependency on the source's own validator/logger classes replaced with the equivalent inject-validator dependency and a plain PSR logger."
---

# Create Import Entity

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-import-entity`
skill. The source's row-validation delegation and reusable validator catalog live in its
own `FTD_Framework`; here the same delegation principle points at this codebase's own
`inject-validator` skill instead (Tier 2, gated on confirming an equivalent shared
framework exists) — everything else (companion module, Command-per-behavior, cached
Storage) is generic Magento import-flow structure, not FTD-specific.

## 1. Scope contract

**Expected input, all confirmed, never guessed:** the target entity and its module (or
confirmation it's a Magento-native concept, not owned by a Bunge module), the import
module name, the entity code/label, the CSV columns and natural key, which behaviors are
needed (Add/Update, Delete, or both), and the validation rules per column/behavior (framed
for `inject-validator`).

**Produces:** `etc/import.xml`, the `Model/Import/{Entity}` adapter, one Command per
behavior, Storage lookup adapters, a sample CSV, `i18n/pt_BR.csv`, and the full `di.xml`
wiring — in a companion `{EntityModule}ImportExport` module.

**Out of scope:** `etc/webapi.xml` (→ `create-repository`, a separate REST surface);
adding bulk `SaveMultiple`/`DeleteMultiple` to the entity's own module (flag as a
follow-up, never build it silently); hand-writing row validation (→ `inject-validator`);
the entity's own persistence layer if it doesn't exist yet (→ `create-entity`/
`create-repository`); unit tests (→ `create-unit-tests`).

## 2. Golden rules

1. **Import classes live only in the companion `{EntityModule}ImportExport` module,
   never in the entity's own module.** Mixing the domain persistence layer with the
   import/export layer breaks the boundary every import module in a well-structured
   Magento codebase respects.
2. **Row validation is always delegated to `inject-validator` — never hand-written.**
   Writing a parallel `{Entity}ValidatorInterface`/chain duplicates a shared concern this
   skill's whole design exists to centralize.
3. **The `AbstractEntity` adapter never contains business logic or persistence.** Each
   behavior is its own Command class — the adapter only orchestrates.
4. **Never add `etc/webapi.xml`, shadow persistence, or reference an unrelated codebase's
   modules as a convention source.** Import is a UI flow, not a REST surface.
5. **No CSV column, natural key, or behavior is assumed without confirmation.** Guessing
   here forces a migration or rework later.

## 3. Companion module (hard rule)

| Rule | Why | Instead |
|---|---|---|
| Never put import classes (`Model/Import/*`, `Command/*`, `Storage/*`) inside the entity's own module | Mixes the domain persistence layer with import/export, breaking the responsibility boundary | Create (or reuse) the `{EntityModule}ImportExport` module and put all import code there |
| Never invent an import module name other than `{EntityModule}ImportExport` | Breaks discoverability — every import module should follow this suffix | Use exactly `{EntityModule}ImportExport`; for a Magento-native entity with no owning Bunge module, name it after the business concept, still `ImportExport`-suffixed |

Verify before delivering:
```bash
test -f app/code/Webjump/{EntityModule}ImportExport/registration.php
test -f app/code/Webjump/{EntityModule}ImportExport/etc/module.xml
! find app/code/Webjump/{EntityModule} -path "*/Model/Import/*"   # none inside the entity's own module
```

## 4. Validation delegation (hard rule)

| Rule | Why | Instead |
|---|---|---|
| Never write a `{Entity}ValidatorInterface`, a validator chain, or entity-specific "leaf" validator classes | Duplicates the shared validation concern `inject-validator` exists to centralize — a parallel validation hierarchy is exactly the antipattern to avoid | Call `inject-validator` once per behavior that needs distinct rules (Append, Delete) |
| Never put business logic/persistence (a switch-case, insert/delete SQL) inside the `AbstractEntity` adapter | The adapter is the one place every import request reaches — logic there can't be tested in isolation and diverges from the Command pattern used elsewhere | Delegate each behavior to its own Command class |

This dependency on `inject-validator` means this skill can't be fully exercised until
Tier 2's shared-framework question resolves — the structural rules here (companion
module, Command-per-behavior, cached Storage) can still be followed regardless of that
outcome.

## 5. Scope limits (hard rule)

| Rule | Why | Instead |
|---|---|---|
| Never add `etc/webapi.xml` for this entity | Import is a UI flow (`System > Import`), not a REST surface — mixing the two exposes the entity unrequested | A REST API is a separate ask for `create-repository` |
| Never build a "shadow" persistence layer inside the import module for an entity that should own its persistence in its own module | The import module is a thin adapter; persistence belongs to the entity's own module | Point to `create-entity`/`create-repository` first, or scope that as a separate task — never invent the table inside the import module |
| Never add `SaveMultiple`/`DeleteMultiple` to the entity's own module without being asked | A performance improvement to a different module, decided silently | Flag it as a follow-up; meanwhile the Command layer loops over the existing single-row Save/Delete Commands |

## 6. Autonomy bands

**Green:** bootstrapping `{EntityModule}ImportExport` when it doesn't exist and the
entity's module already does; creating `import.xml`, the adapter, behavior source model,
Commands, Storage adapters, sample CSV, i18n, and `di.xml` — once all required input from
section 1 has been answered; reusing existing `SaveMultiple`/`DeleteMultiple` or looping
over existing single-row Commands.

**Yellow — proceed, declare in the report:** naming the import module when the entity has
no obvious owning Bunge module; deciding whether `Import::BEHAVIOR_APPEND` and
`BEHAVIOR_REPLACE` share one Command (the default) or split.

**Red — never, even if asked:** import classes inside the entity's own module; hand-written
validation instead of `inject-validator`; `etc/webapi.xml`, shadow persistence, or an
unrelated codebase used as a convention reference.

## 7. Definition of done

- [ ] Entity/module, import module name, code/label, columns, natural key, and behaviors
      all explicitly confirmed — none guessed
- [ ] All import classes live in `{EntityModule}ImportExport`, none in the entity's own
      module (section 3's checks pass)
- [ ] Row validation delegated to `inject-validator` per behavior — no hand-written
      validator class
- [ ] `AbstractEntity` adapter contains no business logic; each behavior is its own
      Command
- [ ] No `etc/webapi.xml`, no shadow persistence table
- [ ] Sample CSV and `i18n/pt_BR.csv` present
- [ ] `setup:upgrade` + `setup:di:compile` + `cache:flush` succeed; the entity type
      manually confirmed to appear and work in the Import dropdown
- [ ] Report names `create-unit-tests` on the Commands/Storage as the next step, and
      flags any deferred `SaveMultiple`/`DeleteMultiple` follow-up explicitly

## Antipatterns

- Writing an entity-specific validator hierarchy instead of calling `inject-validator`.
- Putting a switch-case or insert/delete SQL directly inside the `AbstractEntity` adapter
  instead of delegating to a Command per behavior.
- Resetting the `Storage` cache between bunches instead of scoping it to the whole run.
- Forgetting the natural key in `_permanentAttributes`, even for a Delete-only entity.
- Treating `Import::BEHAVIOR_APPEND` as insert-only instead of an upsert via
  `insertOnDuplicate` — leads to building an unnecessary separate "Update" behavior.
- Adding `etc/webapi.xml` for the import entity — exposes REST access unrequested.
