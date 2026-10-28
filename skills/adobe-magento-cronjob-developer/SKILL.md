---
name: adobe-magento-cronjob-developer
description: "How Magento 2.4.8 cron runs and how to operate a job: system crontab, timezone, schedule and group overrides per environment, store emulation in the crontab area, bounded runs, cron versus queue, cron_schedule diagnosis. Use when designing, tuning or debugging a scheduled job. To scaffold a new job (crontab.xml, class, gate, groups, locks, idempotency): adobe-create-cronjob."
---

# Magento cron jobs

The runtime around a job: what starts it, when it fires, what environment it runs in and how to
read its history. Building the job itself (class shape, `config_path`, per-item `try`/`catch`,
groups, locks, safe re-runs) is `adobe-create-cronjob` §2 to §5; this skill does not repeat it.

## Rules

**What starts cron**
- One system crontab line per server runs `bin/magento cron:run` every minute; `bin/magento
  cron:install` writes it between `#~ MAGENTO START` and `#~ MAGENTO END` (`--force` to rewrite,
  `cron:remove` to delete). Without that line no job, indexer by schedule or cron-spawned consumer
  runs, and nothing reports it.
- Each `cron:run` generates `pending` rows in `cron_schedule` ahead of time and executes the due
  ones. Groups with `use_separate_process` start their own `cron:run --group=<id>` in the
  background. Status flow: `pending` → `running` → `success` | `error`; `missed` when not started
  within the group's `schedule_lifetime`.

**When it fires**
- Expressions in `crontab.xml` and in config are read in the default-scope timezone
  (`general/locale/timezone`); `cron_schedule` stores UTC. `0 2 * * *` with timezone
  `America/Sao_Paulo` runs at 05:00 in the table.
- Change a job's schedule per environment without a deploy through config path
  `crontab/<group>/jobs/<job_code>/schedule/cron_expr` (default scope: `env.php` under
  `system/default`, or `core_config_data`), then `cache:clean config`. `config:set` rejects paths
  not declared in some `system.xml`; an admin-editable schedule is the `config_path` pattern of
  `adobe-create-cronjob`. Pending rows of the old expression are removed on the next run.
- Tune a group per environment with `system/cron/<group>/<setting>` (`schedule_lifetime`,
  `history_success_lifetime`, `use_separate_process`, ...), which overrides `cron_groups.xml`.

**What the job runs in**
- Area `crontab`: no session, no customer, no current store, no theme. Area-only DI goes in
  `etc/crontab/di.xml`.
- Anything that renders a template, builds a storefront URL, sends a transactional e-mail or reads
  a price for a store wraps the work in `Magento\Store\Model\App\Emulation::startEnvironmentEmulation($storeId,
  Area::AREA_FRONTEND, true)` and calls `stopEnvironmentEmulation()` in `finally`. Without it, e-mails
  carry the default store's URLs, locale and design. Only one emulation level is allowed.
- Keep each run shorter than the interval: stop after a fixed number of items or seconds and leave
  the rest for the next run. A run still holding the job lock makes the next due row skip and
  eventually go `missed`.

**Cron or queue**
- Time-based sweep (expire quotes, nightly export, retry failed syncs) → cron.
- Work triggered by an event, per record (send to SAP after order save) → message queue
  (`adobe-create-queue`). Cron's `consumers_runner` job then starts consumers each minute per the
  `cron_consumers_runner` block in `env.php` (`cron_run`, `max_messages` default 10000,
  `consumers` allowlist); a consumer missing from a non-empty allowlist never runs.

## Steps

1. Confirm the trigger is time, not an event (else queue).
2. Scaffold with `adobe-create-cronjob` (`php "$T/magento-example.php" cron`, `$T` = scripts of
   `adobe-magento-toolbox`).
3. Decide group, schedule in the default timezone, time budget per run and store emulation; write
   each in the report.
4. Run it by hand and read its history (below), then `magento-verify`.

## Verify

```bash
crontab -l | sed -n '/#~ MAGENTO START/,/#~ MAGENTO END/p'      # the line exists
bin/magento cron:run --group=<group>                            # generates and runs what is due
mysql -e "SELECT status, scheduled_at, executed_at, finished_at, LEFT(messages,200)
  FROM cron_schedule WHERE job_code='<job_code>' ORDER BY schedule_id DESC LIMIT 10"
mysql -e "SELECT job_code, status, COUNT(*) FROM cron_schedule GROUP BY job_code, status
  HAVING status IN ('missed','error')"                          # jobs failing or starved
bin/magento config:show general/locale/timezone
tail -n 50 var/log/cron.log                                     # exceptions that escaped execute()
php "$T/magento-verify.php"
```

To force a run now, insert a `pending` row as in `adobe-create-cronjob` §5.

## Pitfalls

- Job never runs and `cron_schedule` has no row → crontab line missing, job name typo, or the
  expression resolves empty; check `crontab -l` and the merged config.
- Many `missed` rows → the group is blocked by a long job; bound the run or move it to its own group.
- Runs at the wrong hour → expression written in UTC; it is read in the default timezone.
- E-mail from cron with wrong links or language → no store emulation.
- Rows stuck in `running` after a deploy or crash → the next run marks dead ones `error`; stop cron
  during deploy (`adobe-magento-deployment-engineer`).

## Docs

- https://developer.adobe.com/commerce/php/development/components/cron/
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/message-queues/manage-message-queues
