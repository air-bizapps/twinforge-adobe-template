---
name: adobe-create-cronjob
description: "Scaffold a Magento 2 cron job in a Webjump/* module — etc/crontab.xml entry, the Cron/{Name}.php class (orchestrates, never decides), optional admin enable/schedule toggle (system.xml + Provider), di.xml wiring, cron group choice, and safe re-runs. Use for a scheduled/recurring/batch task — 'add a cron job', 'schedule this every N minutes', 'clean up old X on a schedule'."
---

# Create Cronjob

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-cronjob` skill. No
Bunge-specific rewrite needed for the mechanics — this is core Magento cron behavior, not
FTD-specific. The source cites two real bugs from their own codebase as concrete evidence
of the silent-failure modes below; kept as illustrative examples of the failure pattern,
not as claims about what has happened in Bunge's own code.

## 1. Scope contract

**Expected input, all confirmed, never assumed:** target module, the job name, which
existing service/command/repository the job should call, the schedule mechanism (fixed
cron expression vs. config-path-driven), whether it needs an admin on/off gate, and which
cron group.

**Produces:** `etc/crontab.xml`, `Cron/{Name}.php`, and — when gated or
admin-configurable — a `Model/Provider/{ConfigInterface,Config}` pair + `system.xml` +
the matching `di.xml` preference.

**Out of scope:** the business logic behind the delegated service/command/repository (→
`adobe-create-repository`/`adobe-create-entity`); a queue consumer for per-item async work (→
`adobe-create-queue`); the unit tests themselves (→ `adobe-create-unit-tests`, as a follow-up).

## 2. Golden rules

1. **Never assume the schedule mechanism, gate, or cron group — always ask.** Fixed
   expression and config-path-driven schedules are both common; guessing silently
   produces the wrong shape roughly half the time.
2. **The cron class orchestrates, it doesn't decide.** Business logic lives in the
   injected service/command/repository — the cron class only gate-checks and delegates.
3. **`config_path` and `di.xml`'s `<type name>` must match exactly** what actually exists
   in `system.xml` and `crontab.xml` respectively — see section 3, the single most
   dangerous class of bug here.
4. **`execute()` never hides a failure.** Catch per item (log and continue); a failure of
   the whole run is logged and rethrown, so `cron_schedule` records it as `error` — a
   swallowed exception is recorded as `success`.

## 3. Silent misconfiguration — no exception, no log, just wrong behavior

Magento's cron config format has two failure modes that produce **zero error output** —
the job just runs wrong, indefinitely, until someone notices by accident.

| Rule | Why | Instead |
|---|---|---|
| Never write `<config_path>{section}/{group}/{field}</config_path>` without first confirming that exact path exists in the target module's `system.xml` | If the path resolves to empty, Magento falls back to the `<schedule>` declared next to it or, without one, never schedules the job — no error anywhere (`ProcessCronQueueObserver::getCronExpression()`, 2.4.8). It reads the path without a store, so the field is `showInDefault="1"` only | `grep -r 'field id=' app/code/Webjump/{Module}/etc/adminhtml/system.xml` to confirm the exact `section/group/field` before writing `<config_path>` |
| Never add/rename a `di.xml` `<type name="...">` targeting the cron class without re-checking it matches the exact class in `crontab.xml`'s `instance` attribute | A stale reference after a rename silently does nothing — the override never applies, no error surfaced | Diff the FQCN in `di.xml`'s `<type name>` against `crontab.xml`'s `instance="..."` character-for-character before considering the wiring done |

Verify before delivering:
```bash
grep -A2 'field id="' app/code/Webjump/{Module}/etc/adminhtml/system.xml
grep 'instance=' app/code/Webjump/{Module}/etc/crontab.xml
grep '<type name=' app/code/Webjump/{Module}/etc/di.xml
```

## 4. Execution safety (hard rules)

| Rule | Why | Instead |
|---|---|---|
| Process items in pages with a `try`/`catch` per item | Magento isolates whole jobs from each other (it catches what escapes `execute()`, marks it `error` and runs the next job), but one bad record must not stop the batch, and loading a whole table exhausts memory | `setPageSize()` on the search criteria; `foreach (...) { try { ... } catch (\Exception $e) { log item id; } }` |
| The `catch` block must log the exception, not just swallow it | A swallowed failure is recorded as `success` in `cron_schedule` and is indistinguishable from success until unrelated symptoms show up downstream | Log message and context with the injected logger; for a failure of the whole run, rethrow after logging |
| Never use an impossible cron expression (e.g. February 30th) as the admin on/off toggle | That's a one-way, deploy-time escape hatch, not an admin-configurable gate — flipping it back needs a code change and deploy | Use a `ConfigInterface::isEnabled()` gate for any job that needs a runtime on/off switch |

Verify: `grep -A15 "public function execute" Cron/{Name}.php | grep -E "try|catch|logger"`.

## 5. Group, overlap and re-runs

| Rule | Why | Instead |
|---|---|---|
| Keep the job in `default` unless it runs for minutes, uses a lot of memory, or needs other timings — then propose its own group (red band) | Groups without `use_separate_process` run one job after another in the `cron:run` started each minute; a group with `use_separate_process=1` runs in its own background `cron:run --group=<id>` (core `index`, `consumers`). A pending row not started within `schedule_lifetime` (15 min in `default`) becomes `missed` | `etc/cron_groups.xml` with `schedule_generate_every`, `schedule_ahead_for`, `schedule_lifetime`, `history_success_lifetime`, `history_failure_lifetime` (minutes) and `use_separate_process`; copy `vendor/magento/module-indexer/etc/cron_groups.xml` |
| No custom lock just to stop the job overlapping itself | Magento already locks each job (lock per group+job and `pending` → `running` on the row), so the same job never runs twice at once | Use `Magento\Framework\Lock\LockManagerInterface::lock($name, 0)` + `unlock()` in `finally` only when the same work can also start elsewhere (CLI command, consumer, admin button) |
| The job is safe to run twice over the same data | It re-runs after an `error`, a manual run, or a crash mid-batch (the dead `running` row becomes `error` and the next run starts over) | Mark progress per item (status/`processed_at` column, or select only unprocessed rows), so a re-run never resends an e-mail or re-posts to SAP |
| Read store-scoped settings per store | Cron has no current store; without an id Magento reads the default store view | Loop `StoreManagerInterface::getStores()` and pass the id to the Provider |

Verify where there is a database (stage verificador, laptop): `bin/magento cron:run --group=<group>`
generates the rows and runs what is due; to run now, insert
`INSERT INTO cron_schedule (job_code, status, created_at, scheduled_at) VALUES ('<job>', 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
and run the group again; read `SELECT status, messages, executed_at, finished_at FROM cron_schedule WHERE job_code = '<job>' ORDER BY schedule_id DESC LIMIT 5`
and `var/log/cron.log`. Core has no `cron:status`/`cron:schedule` command.

## 6. Autonomy bands

**Green — proceed directly:** creating `Cron/{Name}.php`/Provider files in a module that
doesn't already declare them; creating `crontab.xml` from scratch; adding a `<job>` to an
existing `crontab.xml`'s existing `<group>`.

**Yellow — proceed, declare the decision in the report:** adding a field to an existing
`system.xml` (name which section/group was reused); adding a `<type name>` block to an
existing `di.xml` (name the target class); adding a `<sequence>` to `module.xml` (name the
dependency and why).

**Red — stop, need explicit confirmation first:**

| Action | Why | Instead |
|---|---|---|
| Reusing/renaming an existing `crontab.xml` job `name` | Job names are unique across the whole Magento instance, not just the module — a silent collision overwrites another module's schedule row generation | Always create a new, module-prefixed job name |
| Creating a custom `etc/cron_groups.xml` group | An operational decision (retry/history tuning, process isolation) that needs explicit sign-off, not a default | Confirm isolation is genuinely needed; otherwise use the `default` group |
| Fabricating a `<sequence>` dependency from name similarity | Can mask a real bug or introduce one where none existed | Only add one when the cron class's own dependencies genuinely require the other module loaded first |

Verify before writing: `grep -r "name=\"{proposed_job_name}\"" app/code/Webjump/*/etc/crontab.xml`.

## 7. Definition of done

- [ ] Schedule mechanism, gate, and cron group were confirmed, not assumed
- [ ] `config_path` (if used) confirmed to exist verbatim in `system.xml`, and `di.xml`'s
      `<type name>` matches `crontab.xml`'s `instance` exactly
- [ ] Items processed in pages with a per-item try/catch that logs; a whole-run failure
      is logged and rethrown (ends as `error` in `cron_schedule`)
- [ ] Safe to run twice over the same data; store-scoped settings read per store
- [ ] No impossible-date cron expression used as an admin toggle
- [ ] No job name reused, no custom cron group introduced, and no `<sequence>` fabricated
      without explicit confirmation
- [ ] `setup:upgrade` + `setup:di:compile` + `cache:flush` + `cron:install` succeed;
      `cron_schedule` inspected and/or `cron:run` exercised manually
- [ ] Report names `adobe-create-unit-tests` on the cron class and Provider as the next step

## Antipatterns

- Writing `<config_path>` or `di.xml`'s `<type name>` without first grepping the target
  module's real `system.xml`/`crontab.xml` — the two silent-failure patterns in section 3
  both start exactly this way.
- Putting business logic inside `Cron/{Name}::execute()` instead of delegating to an
  injected service/command/repository.
- Creating a custom `cron_groups.xml` group or reusing an existing job name without
  explicit confirmation.
- Implementing the delegated service's business logic as part of this skill — that's
  `adobe-create-repository`/`adobe-create-entity`/another sibling skill's scope.
