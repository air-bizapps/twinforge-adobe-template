---
name: bunge-figma-layout-decomposition
description: "How the Tech Lead turns a Figma design link into refined dev subtasks without relying on visual/image interpretation — Bunge's dev agents have no vision/multimodal input pipeline, so this uses Figma's REST API for a figma.com/design or /file link, or direct extraction from the published JS bundle for a figma.site (Figma Make prototype) link. Use whenever a card references any figma.com or figma.site link that needs to become implementation work. Bunge-specific."
---

# Bunge — Figma Layout → Dev Subtasks (structured data, not images)

## Why this isn't image-based

As of 2026-09-11, Bunge's dev agents (Tech Lead, Backend Developer,
Frontend Developer) all run on the `crucible_local` adapter. That adapter
has no MCP integration and no image/vision content pipeline — TwinForge
does not feed image attachments into the model as visual input for this
adapter. Treating a Figma link as "open the screenshot and figure out the
layout" will not reliably work and must not be attempted — there is no
mechanism for these agents to actually see a rendered image.

Figma's own recommended integration for AI coding agents is exactly the
alternative used here: structured access to the design (component tree,
layout rules, text content, style tokens) via API, instead of interpreting
rendered pixels — this is what Figma's own Dev Mode MCP Server provides to
tools that support MCP. Bunge's agents don't have MCP wired up, so this
skill uses the equivalent raw REST API calls via `curl`, which every dev
agent here already has through normal shell access.

## Precondition: run `bunge-link-verification` first

For a `figma.com` link specifically, "verified" means more than an HTTP
200 — Figma returns a login wall for unauthenticated requests, which can
still look like a reachable page. Verification for a Figma link is:

1. Confirm `$FIGMA_TOKEN` is set (a project env var/secret binding, set up
   the same way `$GH_TOKEN` was for GitHub). If it is not set at all,
   treat the link as **not verified** — block the card and ask a human to
   provision a Figma personal access token as the `FIGMA_TOKEN` project
   env var before continuing. Do not attempt a bare `curl` against the
   Figma URL as a substitute — it cannot prove real access either way.
2. Extract the file key from the URL: the path segment immediately after
   `/design/` or `/file/`, e.g.
   `https://www.figma.com/design/<FILE_KEY>/...` → `<FILE_KEY>`.
3. Call the Figma REST API with that key and token:
   ```
   curl -sS -H "X-Figma-Token: $FIGMA_TOKEN" \
     "https://api.figma.com/v1/files/<FILE_KEY>" \
     -o /tmp/figma-file.json -w "%{http_code}"
   ```
   A `200` with a real JSON body means verified. A `403`/`404` means the
   token doesn't have access to this specific file — block the card and
   ask a human to confirm sharing/access, not just that a token exists.

## Figma Make prototypes (`*.figma.site` links) — a different, real path

Not every Figma link is a `figma.com/design/` or `/file/` link. A
published **Figma Make** prototype (domain `*.figma.site`) is a different
product entirely: it's a real, deployed React app, not a design file, so
none of the Files REST API above applies (there's no `FILE_KEY` to
extract, and `$FIGMA_TOKEN` doesn't help). Do not treat a missing
`$FIGMA_TOKEN` as the blocker for this kind of link — it's simply the
wrong tool for this URL shape.

**This is not a dead end.** `curl`-ing the page itself returns an empty
SPA shell (`<div id="root"></div>` with all real content injected by a
bundled JS file at runtime) — confirmed for real on 2026-09-11 (card
`14136`). But the app's own JS bundle is a normal static file referenced
from the HTML's `<script src="...">` tag, downloadable the same as any
other asset, and — at least for this real prototype — it was **not**
heavily obfuscated: real UI copy, mock data, and design tokens were still
present as plain, readable string literals once the bundle was
downloaded and read as text. This is real, structured content extraction
from source, not visual interpretation or guessing — it's consistent with
the rest of this skill's approach, just applied to a different artifact
than a Figma Files API response.

1. `curl -sS -L "<figma.site URL>" -o /tmp/site.html` — find every
   `<script src="...">` and `<link rel="stylesheet" href="...">` URL in
   it (they'll typically be `<same-origin>/assets/index-<hash>.js` and
   `.css`).
2. Download each one (`curl -sS -L "<asset URL>" -o /tmp/asset.js`, etc.).
3. Read the JS file as plain text — do not try to execute or render it.
   Look for the app's own literal strings: arrays of objects defining
   steps/statuses/labels (e.g. a literal array of objects with `id`,
   `label`, and `status` fields, one entry per step), section headings,
   form/table field labels, and a color-token object (commonly a single
   object literal near the top mapping short keys to hex color values).
   These are the real content and design tokens — extract them the same
   way you'd extract a Figma REST API's `document` tree, and cite them
   the same way in subtasks' acceptance criteria (exact copy, exact
   status names, exact hex/token values).
4. **This can fail, and that's fine — it's a best-effort tier, not a
   guarantee.** If the bundle is too obfuscated to find readable strings,
   or the real content clearly comes from a live API call at runtime
   rather than embedded literals, say so explicitly and fall back to
   asking a human (per `bunge-link-verification`): either a static export
   (PNG/PDF) attached to the card, or a written description. Don't spend
   excessive effort reverse-engineering a bundle that isn't cooperating —
   a few minutes of grep/read is worth it, a deep static-analysis effort
   is not.
5. If a human has already attached files instead of a link (an exported
   image, or the site's own downloaded JS/CSS/assets) — see
   `bunge-link-verification`'s step 0 — use those directly instead of
   re-fetching from the URL.

## Decomposition procedure (once verified)

Work entirely from the JSON returned above — never from a rendered image
or a description of what the design "looks like."

1. In the JSON's `document` node tree, identify the top-level frames under
   each page. These almost always correspond 1:1 with the "screens" a
   designer intends as one implementation unit.
2. For each frame that maps to real dev work, create one subtask, and pull
   its content straight from the JSON:
   - Frame/component **names** → map to the page/component names used in
     the codebase.
   - **Text node contents** → real copy for the subtask's acceptance
     criteria. Never invent or paraphrase labels — copy them exactly.
   - **Layout data** (`layoutMode`, `itemSpacing`, `padding`, or absolute
     `x`/`y`/`width`/`height` when there's no auto-layout) → spacing and
     positioning acceptance criteria.
   - **Style refs** (`fills`, `strokes`, typography nodes) — resolve
     against `GET /v1/files/<FILE_KEY>/styles` for named design tokens
     when available, so the subtask references an actual token (e.g.
     `Body/Regular`, `color/brand/500`) instead of a raw hex value copied
     out of context.
3. If a component carries a Figma Code Connect mapping (check
   `componentPropertyDefinitions`/`componentSets` in the response), note
   the real code component it maps to directly in the subtask instead of
   asking the developer to guess which existing component it corresponds
   to.
4. Write every subtask's acceptance criteria in terms of this structured
   data — e.g. "frame `Checkout/Summary`: vertical auto-layout, 16px gap,
   text style `Body/Regular`" — never "matches the design," which a dev
   agent with no vision input has no way to verify.
5. If a visual reference is still useful for a human reviewer (e.g.
   embedded in the PR description), export a static image via
   `GET /v1/images/<FILE_KEY>?ids=<NODE_ID>&format=png` and attach it as
   an asset or comment. This is a convenience for the human, never the
   mechanism the agent itself uses to derive implementation details.

## GUARDRAIL — never ask a dev subtask to "interpret" a design image

**Prohibition:** Writing a subtask that instructs a developer agent to
look at a Figma frame, screenshot, or exported image and infer layout,
spacing, or copy from it.

**Why:** Bunge's dev agents run on `crucible_local`, which has no
vision/multimodal input. An agent "looking at" an image has no actual way
to do so — it would either hallucinate a plausible-sounding but wrong
interpretation, or silently fail to act on the instruction at all.

**Correct alternative:** Always decompose from the Figma REST API's
structured JSON per the procedure above, and write every acceptance
criterion in terms of that structured data, not visual description.
