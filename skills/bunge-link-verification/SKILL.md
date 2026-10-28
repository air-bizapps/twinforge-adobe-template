---
name: bunge-link-verification
description: "Verify any external link a card depends on (design links, spec docs, external references) is actually reachable and its real content retrievable before refinement or implementation starts. If it isn't, block the card and ask a human to intervene instead of guessing at content nobody could open. Bunge-specific — pairs with bunge-figma-layout-decomposition for figma.com links specifically."
---

# Bunge — Link Verification Before Work Starts

## Why this exists

On 2026-09-11, a real (not test) Bunge demand arrived referencing a Figma
layout link before the card had been refined. Before that pattern repeats
silently — an agent starting refinement or implementation on a card whose
key reference link turns out to be broken, private, or behind a login wall
— verify the link first and block for a human if it fails, rather than
finding out mid-implementation or, worse, guessing at unseen content.

## When this applies

Any card (or subtask) whose description or comments contain a URL whose
*content* is actually needed to plan or implement the work — a design
link, an external spec/requirements doc, a reference API, an asset hosted
elsewhere. Not every link mentioned in passing needs this (e.g. a link to
an already-merged PR added purely for context does not).

Applies to:
- **Tech Lead**, during refinement — before creating or assigning any
  subtask that depends on the link's content.
- **Backend/Frontend Developer**, if a subtask you're about to start
  references a content-bearing link the Tech Lead didn't already verify
  (e.g. it was added after refinement, in a later comment).

## Procedure

0. **Before re-running this check on a card you already blocked, look at
   the issue's attachments and newest comments first** —
   `twinforgeGetIssue`/`issue attachments` — not just the original link in
   the description. A human unblocking a card frequently does it by
   attaching the actual content directly (an exported image, a downloaded
   HTML/CSS/JS bundle, a written spec) rather than by fixing the original
   URL or provisioning a token. Treat any such attachment as the
   verified content for that link from now on — don't re-run the
   original `curl` check against the now-superseded URL and re-block
   without even looking at what was attached.

   **This failed for real on 2026-09-11 (card `14136`, BUN-51, second
   pass):** Kaio attached the Figma Make prototype's actual `index-*.js`
   and `index-*.css` bundle files (downloaded from the site directly) plus
   an image asset, and commented asking for confirmation the content was
   readable. The next run re-blocked the card with no new comment at all —
   it never looked at the attachments, so from Kaio's side it looked like
   nothing happened. If attachments exist that weren't there on a prior
   pass, read them before deciding anything, and always leave a comment
   explaining what you found either way (readable and used, or still not
   enough and why) — going silent while re-asserting the same blocked
   state is itself the failure mode from the first incident above,
   recurring in a new shape.

   A downloaded JS/CSS bundle is still just text from this agent's point
   of view — `grep`/read it like any other file. It will not have the
   Figma REST API's structured frame/style tree, but real UI copy, status
   labels, and structure are often still recoverable as literal strings in
   the bundle (confirmed on this exact bundle: searching it turned up real
   Portuguese domain terms like "pedido", "status", "entrega" as embedded
   string literals) — extract what's really there and say plainly in your
   subtasks' acceptance criteria that spacing/style tokens are not
   available this way (unlike the REST API path), only copy and rough
   structure.

1. For every content-bearing link found on the card:
   a. Run a plain reachability check:
      ```
      curl -sS -o /dev/null -w "%{http_code}" -L --max-time 15 "<url>"
      ```
   b. Classify the result:
      - `200`-`299` (including after following redirects) → tentatively
        OK, but continue to (c) before trusting it.
      - `401`, `403`, `404`, `5xx`, a timeout, or a redirect chain that
        lands on a login/signin page → **not reachable**.
   c. Some platforms return `200` with no real content at all — not just a
      login wall. Two known cases at Bunge:
      - A `figma.com/design/` or `figma.com/file/` link — see
        `bunge-figma-layout-decomposition` for the correct check (verify
        via the Figma REST API + `$FIGMA_TOKEN`, never a bare `curl`
        status).
      - A client-side-rendered app or prototype (e.g. a `*.figma.site`
        "Figma Make" prototype link) — `curl` returns `200` but the HTML
        body is an empty SPA shell (a `<div id="root"></div>` with all
        real content injected by a bundled JS file at runtime). Check the
        response body itself, not just the status: strip tags and see if
        any real text remains beyond a page title. If it's essentially
        empty, this is **not verified** even at `200` — there is no
        browser-rendering tool available to any Bunge dev agent to
        execute that JS and see the real result. Escalate this as its own
        case in the comment (see step 2) rather than treating the `200`
        as proof of access.
      If the URL matches a platform with a documented API-based check
      like Figma's, use it instead of trusting the HTTP status alone.
2. If **any** content-bearing link fails verification, blocking means
   **both** of the following — a comment alone is not blocking, it is
   just a comment that happens to describe a blocker:
   - **Set the card's status to `blocked`.** This is not optional and not
     implied by writing a comment that says "blocked" — the platform's
     own liveness/health checks read the `status` field, not comment
     text. If this is caught during refinement, do not create or assign
     any subtask that depends on it.
   - **Create a real interaction** (`ask_user_questions` or
     `request_confirmation`, per the missing-context rule already in your
     own `CONTEXT.md`) naming Kaio as the person who needs to act, in
     addition to the explanatory comment — the comment is for the human
     reading the thread, the interaction is what makes the ask visible to
     the platform's own notification/health-check surfaces.
   - Add the comment stating precisely: which link, which check was run,
     what the result was, and what a human needs to do to unblock it
     (grant access, provide a missing token/secret binding such as
     `FIGMA_TOKEN`, attach an export directly to the card).
   - Stop there for that link — do not proceed on the assumption the
     content will show up later.

   **This failed for real on 2026-09-11 (card `14136`, BUN-51):** the
   diagnosis itself was correct (a Figma Make prototype link, `curl`
   returning an empty SPA shell, correctly identified as not verifiable
   without a real Figma file link + `$FIGMA_TOKEN`) and the comment
   correctly explained the problem and asked Kaio to act — but the issue
   was left `in_progress` with no interaction created, so the card looked
   like active work rather than a blocker waiting on a human. Kaio caught
   it by inspection, not because anything surfaced it. Both bullets above
   are how this is prevented going forward.
3. If all content-bearing links verify OK, proceed with refinement or
   implementation normally. No need to narrate that the check passed
   unless asked.

## GUARDRAIL — never invent content for a link you could not open

**Prohibition:** Writing subtasks, acceptance criteria, or code based on
assumptions about what an unreachable link "probably" contains.

**Why:** A guessed layout, spec, or reference produces work that has to be
redone once a human unblocks the real content — the exact rework this
check exists to prevent, plus the cost of having produced wrong work in
the meantime.

**Correct alternative:** Block and ask a human, per the procedure above. A
card sitting `blocked` waiting on a human is a normal, cheap, expected
outcome here — inventing content to keep moving is not.
