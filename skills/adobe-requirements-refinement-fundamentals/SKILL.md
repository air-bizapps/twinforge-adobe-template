---
name: requirements-refinement-fundamentals
description: "Software-engineering fundamentals for turning a vague demand into a refined, unambiguous, checkout-able plan: requirements analysis, ambiguity-surfacing technique, sizing, and definition of ready/done. Use whenever a task is assigned to refine, scope, or clarify a demand before planning — not for writing code. Domain-agnostic — reusable for any project, not specific to Magento or any one company."
---

# Requirements Refinement Fundamentals

This skill covers the *method* of refinement: how to turn something vague
into something a specialist can execute without guessing. It does not cover
TwinForge plan-document mechanics (see `task-planning` for that) or any
domain's technical conventions (see the relevant domain skill, e.g.
`adobe-commerce-standards` for Magento work).

## The core discipline: decide when you can defend it, ask when you can't

Default to resolving ambiguity yourself: check the codebase's existing
conventions, the domain/process skills, `CONTEXT.md`, and durable memory,
then make the call and write down why. A documented, reasoned decision is
not a guess — it's what a competent engineer does dozens of times a day
without asking anyone. A silent, undocumented assumption is different: that
is a bet placed with someone else's time later, made without leaving a
trace for them to catch it.

Reserve actual questions for what's left after that investigation: genuinely
irresolvable forks where two defensible paths remain and picking wrong would
be costly to undo, or a sensitive decision that isn't yours to make (see the
target agent's own `AGENTS.md` for the concrete list — production/security/
budget/scope/business-tradeoff are the common ones). Every decision you make
yourself still needs to be visible: write it into the plan with its
reasoning, don't just bake it in with no trace.

## Requirements analysis lenses

- **Functional vs. non-functional** — what the system must *do* (functional)
  vs. the qualities it must have while doing it (performance, security,
  compatibility, observability). A demand phrased only as "add X" usually
  hides non-functional expectations worth surfacing (how fast, for whom,
  under what load, on what environments).
- **INVEST** — a well-formed unit of work should be Independent, Negotiable,
  Valuable, Estimable, Small, Testable. If a demand fails two or more of
  these, it is not ready to become a single issue — it needs either more
  clarification (Estimable, Testable) or splitting (Small, Independent).
- **Definition of Ready** — a demand is ready to plan only when: the goal is
  stated in terms of user/system-visible outcome (not implementation), the
  acceptance bar is concrete enough that two different people would agree
  whether it was met, and known constraints (compatibility, deadlines,
  explicitly out-of-scope items) are stated, not implied.
- **Definition of Done vs. process bar** — "code reviewed" is a process bar,
  not a done bar. A real acceptance criterion describes an observable
  behavior or state, not a step in the workflow.
- **Explicit non-goals** — stating what a change will deliberately *not* do
  is as valuable as stating what it will. It prevents silent scope growth
  and gives the executor permission to say no to adjacent asks.

## Software-engineering fundamentals that shape good scoping

- **Single responsibility per unit of work** — the same principle that
  applies to a class or function applies to an issue: one child issue,
  one reason to exist. If describing the issue needs "and" to join two
  unrelated outcomes, split it.
- **YAGNI applied to scoping** — do not add speculative future-proofing
  requirements ("while we're at it, let's also...") into a plan unless the
  requester actually asked for it. Note it as a deferral instead.
- **Risk- and uncertainty-based sequencing** — order work so the riskiest or
  least-understood part happens first, not last. A plan that saves the
  hardest unknown for the final child issue hides risk instead of managing
  it.
- **Technical debt is a tradeoff, not a verdict** — when a shortcut is on
  the table, name the tradeoff explicitly (what it costs later, what it
  buys now) instead of silently picking one side.
- **Blast radius before certainty** — the amount of clarifying rigor a
  demand deserves scales with how hard it would be to undo. A copy change
  needs less back-and-forth than a schema change or a change to a shared
  contract.

## When a question is actually warranted

Most refinements should resolve without a conversation at all — this
section only applies to the residue: genuinely irresolvable forks or
sensitive decisions, after you've already checked the code, the skills,
and context for an answer.

- Ask one focused batch of questions at a time — not a giant checklist. Pick
  the 2-4 questions that would most change the shape of the plan if
  answered differently.
- Paraphrase what you understood back to the requester before treating it
  as settled ("So the goal is X, and Y is explicitly out of scope for now —
  is that right?"). This catches misunderstandings before they get baked
  into a plan.
- For everything you decided without asking, say so plainly in the plan —
  a decision the requester can see and challenge, not one you made silently.
- If the requester is unavailable or gives a non-answer, do not stall
  indefinitely — name the specific unresolved question as a risk in the
  plan and propose your best default, rather than blocking forever.

## Sizing without a token/line-count number

TwinForge has no built-in size limit for how big a task should be. Use these
proxies instead of a hard number:
- A specialist should be able to start the child issue from its title and
  description alone, without re-reading the parent plan.
- If a reviewer would reasonably say "this is half done" at some natural
  midpoint, that midpoint is a split point.
- If the work requires two different specialties to hand off to each other
  mid-issue, split at the handoff.
