---
name: po-issue-recovery
description: "PO-only playbook for diagnosing and unblocking stuck/blocked/SLA-watchdog issues on the project board via twinforgecli — distinguishes legitimate blocking from stranded runs and orphaned ready work, and hard-stops before anything that would touch dev-process rules or specialty routing owned by the Tech Lead."
---

# PO Issue Recovery

Scope: this skill covers *board mechanics* (status, assignee, recovery
actions) on issues that are stuck, `blocked`, or the subject of a
platform-generated SLA/stranded-issue watchdog issue. It does **not** cover
Magento development itself, branch/PR policy, or task decomposition — those
belong to `projeto-engineering-practices` and the Tech Lead respectively. If a
diagnosis leads you anywhere near those, stop and escalate (see "Hard stops"
below) instead of acting.

## When to use

- You (PO) are assigned or woken on an issue whose title/body says an issue
  has been `blocked` past SLA (a platform watchdog issue), or a human asks
  you directly to unblock a specific issue.
- A card's parent issue has sat `blocked` with no visible progress and
  nobody has explained why.

## When not to use

- The wake names a specific dev/design/planning task with no blocking
  signal — that's normal work, not recovery.
- The issue is `blocked` waiting on a human decision (priority, budget,
  scope) — that's for the human/PO-as-business-owner, not this playbook's
  mechanics.

## Step 1 — Read before touching anything

For the issue named in the watchdog (or the one reported stuck):

```bash
twinforgecli issue get <identifier> --json
```

Read, in this order:
- `status`, `assigneeAgentId`, `blockedAt`
- `activeRecoveryAction` — if non-null, this is a platform-detected
  stranded/failed run, not a normal dependency block. Read
  `evidence.latestRunErrorCode`, `evidence.failureSummary`,
  `previousOwnerAgentId`, `returnOwnerAgentId`.
- `blockerAttention` — `state: "covered"` with `reason: "active_child"` means
  the issue is correctly waiting on child issues; this is **not a bug**, do
  not force-unblock it. `state: "needs_attention"` means at least one
  blocker itself needs a look — open each id in `blockedBy`.
- `blockedBy` / `blocks` — the real dependency graph. Walk every blocker
  that is not `done`/`cancelled`.

Classify into exactly one of:

1. **Correctly blocked (waiting on children)** — `blockerAttention.state ==
   "covered"`. No action. Comment only if asked; otherwise leave it.
2. **Stranded/failed run** — `activeRecoveryAction` is set, `cause` is
   `stranded_assigned_issue` (or similar), caused by an environment/adapter
   failure (`adapter_failed`, timeouts, workspace unreachable). This is an
   infrastructure problem, not a process problem — go to Step 2.
3. **Orphaned ready work** — a blocker in the chain is `done`/`cancelled`
   but the blocked issue itself is still `backlog`/`todo` with no assignee
   or no wake, i.e. nobody picked it up after it became ready. Go to Step 3.
4. **Anything else** (scope unclear, blocker is itself stuck for a reason
   you can't explain in under a minute, or the blocker is a design/priority
   question) — do not guess. Go to "Hard stops".

## Step 2 — Stranded/failed run (infra recovery)

Only after confirming the underlying infra is actually back (don't take
"it's fixed" on faith — check the concrete resource this run depends on:
`twinforgecli workspace get <id> --json` / `twinforgecli environment probe
<id> --json` for the project's execution workspace and environment; if you
cannot confirm the resource is healthy, do not resolve as `restored` —
escalate instead):

```bash
twinforgecli issue recovery:resolve <issueId> \
  --outcome restored \
  --source-issue-status todo \
  --resolution-note "<what you verified, concretely>"
```

This automatically hands the issue back to `returnOwnerAgentId` (the
original developer) — never override that to a different specialty. Then
wake them explicitly:

```bash
twinforgecli issue comment <issueId> --resume --body "<what to resume, one line>"
```

Valid `--outcome` values: `restored` (fixed, resume), `false_positive` (the
platform misdetected a stall — nothing was actually wrong), `blocked` (still
genuinely blocked — pair with `--source-issue-status blocked`), `cancelled`
(the issue itself is being cancelled — this is a scope decision, escalate to
Tech Lead first, don't decide it yourself).

## Step 3 — Orphaned ready work

If a subtask's only blockers are `done`/`cancelled` but it's still sitting
`backlog` with `assigneeAgentId: null` (or an assignee who never picked it
up), the fix is mechanical: move it forward to the **exact assignee the
Tech Lead already intended for it**, never a different specialty.

**Before touching assignment, run the exclusivity check — this is
non-negotiable, see `projeto-engineering-practices`, "Exclusividade de
execução": only one dev subtask (Backend, Frontend, or DevOps) may be
active company-wide at a time, because they share one Magento
installation/branch. A second one starting concurrently can corrupt the
first developer's in-progress work, not just contend for resources.**

```bash
twinforgecli issue list --status in_progress --assignee-agent-id <backendId> --json
twinforgecli issue list --status in_progress --assignee-agent-id <frontendId> --json
twinforgecli issue list --status in_progress --assignee-agent-id <devopsId> --json
```

- If **any** of the three returns a result: do **not** move the orphaned
  issue to `todo` or assign/wake anyone yet. Leave it in place and note in a
  comment that it's waiting for the currently active dev subtask to finish
  — the active one's own completion will let the normal wake cycle pick
  this one up, or you can re-check next time you're woken.
- If **all three are empty**: safe to proceed.

```bash
twinforgecli issue update <issueId> \
  --status todo \
  --assignee-agent-id <same specialty as before, or the id the parent/sibling subtasks already used> \
  --comment "<blocker X done, moving to todo>"
twinforgecli issue comment <issueId> --resume --body "<one-line: what to start>"
```

Never invent a different assignee than the one the card's own pattern
implies (e.g. a `[BACK]`-tagged subtask goes to the Magento Backend
Developer, matching the sibling subtasks on the same parent — check
`parentId`'s other children for the pattern if unsure).

## Step 4 — Close the loop

- If a platform SLA/watchdog issue (e.g. "Unblock <PREFIXO>-X: stuck in blocked
  for Nh") triggered this pass, comment on it with the root cause and the
  concrete fix applied, then mark it `done`.
- Always leave a comment on the issue you actually fixed explaining what
  was wrong and what you did — this is the audit trail the Tech Lead reads
  later; never silently change status/assignee.

## Step 5 — Notify humans (Slack + e-mail)

After Step 4 (and also when you stop at a hard stop), tell the humans. The
issue comment stays the audit trail; the notification is how they find out.
Two levels, never mixed:

| Level | When | Message |
|---|---|---|
| **ALERTA** | Classification 4 or any hard stop — a human must decide | Issue id + title, how long it has been `blocked`, why you could not act, who must decide (Tech Lead / human), link to the issue |
| **RESUMO** | Classification 2 or 3 — you fixed it | Issue id + title, root cause, the fix applied, confirmation that the owner was woken, link to the issue |

Classification 1 (correctly waiting on children) → no notification. (These
rules mirror `policies.notifications` in `twinforge/workflow.yaml`, the single
source; if they ever disagree, the workflow file wins.)
One notification per issue per pass; do not repeat if you are woken again
on the same unchanged state.

**Slack** — Web API (`chat.postMessage`) with the company's bot token, to
the channel `#<canal-slack-do-projeto>`. The token is the company secret
`slack_bot_token`, injected into your environment as `SLACK_BOT_TOKEN`. If
that variable is empty, fetch the granted secret on demand instead (see
"Reading Granted Secrets" in the `twinforge` skill). Then post:

```bash
curl -s -X POST https://slack.com/api/chat.postMessage \
  -H "Authorization: Bearer $SLACK_BOT_TOKEN" \
  -H 'Content-type: application/json; charset=utf-8' \
  --data '{"channel":"#<canal-slack-do-projeto>","text":"<ALERTA|RESUMO> <PREFIXO>-X — <titulo>\n<motivo ou fix, 1-3 linhas>\n<link da issue>"}'
```

Slack always answers HTTP 200; success is `"ok":true` in the body. Only
write "notificado no Slack" in the issue comment **after** you have read
`"ok":true` in that response, and quote the `ts` Slack returned. Never
announce the notification beforehand or on the strength of the `curl` exit
code. On `"ok":false` (or if `curl` itself fails) read the `error` field
(`not_in_channel`, `channel_not_found`, `invalid_auth`…) and write "Slack
não notificado: <error>" in the comment. Never print the token or paste it
into a comment; if the secret is not granted to you, say so in the issue
comment and continue — a failed notification never undoes or blocks the
recovery itself.

**E-mail** — mechanism not configured yet (no provider/sender defined for
this company). Until a section here names a provider, a granted secret and
the recipients, **do not improvise one**: skip e-mail and add one line to
the issue comment ("e-mail não enviado: canal não configurado"). ALERTA is
the level that most needs it, so flag the gap to the Tech Lead the first
time you hit it.

Never put tokens, secret values or anything from outside the board (code,
diffs, logs) in the message — issue id, status, cause, fix and link only.

## Hard stops — escalate to Tech Lead instead of acting

Do not decide any of the following yourself; comment on the issue
`@`-explaining the situation is fine, but the decision belongs to the Tech
Lead (owns planning/decomposition and all dev-process rules — see
`projeto-engineering-practices` and the requirements-refinement skill chain):

- Reassigning across specialties (backend ↔ frontend ↔ devops), or to
  anyone other than the id the card's own pattern already implies.
- Anything that changes scope, acceptance criteria, task breakdown, or
  priority — even if it looks like the "obvious" fix.
- Cancelling an issue, or resolving a recovery action with `--outcome
  cancelled`.
- A blocker that is itself stuck for a reason outside this skill's four
  classifications (Step 1).
- Anything that would require running `git` commands, touching code, or
  pushing — this skill only ever moves issue metadata (status, assignee,
  comments), never the codebase. That boundary belongs entirely to
  `projeto-engineering-practices` and the developers/Tech Lead.
- Skipping or shortcutting the Tech Lead's refinement process
  (`/opsx:propose` → review → `/opsx:apply`) for a *new* piece of work —
  this skill is about restoring already-approved work, not creating or
  reshaping it.

## Don't-do list

- Do not resolve a recovery action as `restored` without concretely
  verifying the underlying resource (workspace/environment) — "the user
  said it's back" is a valid trigger to *check*, not a substitute for
  checking.
- Do not run the exclusivity check only once and assume it still holds
  later in a long pass — re-check immediately before each assignment you
  make in Step 3, not just at the top of the pass.
- Do not close a platform SLA/watchdog issue without a comment naming the
  concrete root cause and fix — "resolved" alone is not enough evidence for
  this board's own standard (mirrors the verification-before-done rule in
  `projeto-engineering-practices`).
