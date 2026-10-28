---
name: engineering-memory
description: "Structured, file-based memory for this coding agent, adapted from the PARA memory pattern: one entity per module/known-bug/architecture-decision, a raw per-heartbeat daily note, and a tacit MEMORY.md index of engineering gotchas. Use at the start of a heartbeat (skim MEMORY.md), whenever a situation feels familiar (check for a matching entity before re-deriving a diagnosis), and at the end of a heartbeat (log the note, promote anything durable)."
---

# Engineering Memory

Persistent, file-based memory for this agent, adapted from the PARA memory
pattern (`para-memory-files`) for a coding agent instead of a personal
assistant. Three layers, same as the source pattern, re-scoped to
engineering knowledge. All paths below are relative to `.agent-memory/` at
the root of this workspace's checkout — this directory is gitignored and is
**not** part of the Magento application; it never ships in a commit or a
PR.

## Three memory layers

### Layer 1: Entities (`entities/`)

One file per durable thing worth remembering, split into three kinds:

```text
.agent-memory/entities/
  modules/<ModuleName>.md      # a Webjump/* module: what it does, its quirks
  bugs/<slug>.md                # a known bug: diagnosis, fix, recurrence signature
  decisions/<slug>.md           # an architecture/process decision and its why
```

**When to create an entity** — mirror the source pattern's threshold:

- A bug or symptom recurs (2nd occurrence), OR
- You make or observe a non-trivial architecture/process decision with a
  rationale worth preserving, OR
- A module has a non-obvious quirk (a workaround, an undocumented coupling,
  a "don't touch X because Y") that isn't already evident from reading the
  code.

Otherwise, note it in the daily note (Layer 2) and let it get promoted later
if it recurs. Don't front-load empty entity files "just in case."

**Entity file template** (frontmatter + body, same shape for all three
kinds):

```markdown
---
type: module | bug | decision
status: active | mitigated | resolved | superseded
created: YYYY-MM-DD
updated: YYYY-MM-DD
related_issues: [BUN-xx, ...]
superseded_by: null   # e.g. entities/bugs/other-slug.md
---

# Title

## Summary
One or two sentences — what this is, in plain terms.

## Diagnosis / context
What you observed, and the root cause if known. For a module: what it does
and why it exists (not what the code visibly does — that's for `git blame`
and reading the code).

## Resolution / decision
What was done, or what was decided, and why. For a bug: the concrete fix or
workaround. For a decision: the option chosen and the rejected alternatives.

## When this recurs
How to recognize the same situation again, and what to do — the whole point
is that future-you reads this instead of re-diagnosing from scratch.
```

**Rules:**

- Never delete an entity. When it's outdated, set `status: superseded` and
  `superseded_by`, or `status: resolved` if the underlying issue is gone for
  good (e.g. a platform bug got fixed upstream).
- Keep it to one file per entity — no separate `summary.md`/`items.yaml`
  split. A coding agent's engineering facts are lower-volume and more
  narrative than a personal assistant's atomic life facts; the extra tier
  isn't worth the overhead here.

### Layer 2: Heartbeat notes (`notes/YYYY-MM-DD.md`)

Raw timeline of what happened, one file per day, one entry per heartbeat
appended as you go:

```markdown
## <issue-id> — <title>

What you did, what you found, anything surprising. Doesn't need to be
polished — this is scratch, not the durable record.
```

At the end of a heartbeat, before you finish: if something durable came out
of it (a diagnosed bug, a decision, a module quirk), promote it to a Layer 1
entity and add a line to `MEMORY.md` if it's the kind of thing you'd want to
recall instantly next time. Don't promote everything — most heartbeats leave
nothing behind beyond the day's note, and that's fine.

### Layer 3: `MEMORY.md` — tacit gotchas index

One-line-per-entry index at `.agent-memory/MEMORY.md`, pointing at entity
files. This file is an index, not the memory itself — never write the full
diagnosis here, only a hook plus a link. Keep it short enough to skim in a
few seconds at the start of a heartbeat.

## Read this before you re-derive a diagnosis

If a situation feels like something you've hit before — a test that fails
the same way, an issue that reopens itself, a deploy step that silently
no-ops — check `MEMORY.md` first. If there's a matching entry, read the
entity and follow its "When this recurs" section instead of investigating
from scratch. This is the whole point of the system: without it, the same
diagnosis gets redone from scratch on every recurrence instead of getting
faster each time.

## What NOT to save here

- Anything derivable by reading the code, `git log`, or `git blame` —
  memory is for facts that aren't otherwise recoverable (a diagnosis, a
  decision's rationale, a workaround).
- One-off task specifics that won't recur — those belong in the issue's own
  comments, not in durable memory.
- Anything already covered by `adobe-commerce-standards` (general Magento
  conventions) or `projeto-engineering-practices` (company process) — this
  system is for what's specific to *this* codebase's history, not general
  knowledge those skills already carry.

## Ownership

Unlike the other three skills in this stack, the memory *data* files
(`entities/`, `notes/`, `MEMORY.md`) are yours to write continuously,
without asking — that's the point of memory, and routing every entry
through Kaio for approval would defeat it. The *skill itself* (this file —
the system's structure and rules) follows the same rule as the others:
propose changes in a task comment rather than editing it directly.
