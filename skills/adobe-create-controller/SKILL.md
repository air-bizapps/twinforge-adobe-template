---
name: create-controller
description: "Scaffold a new Magento 2 controller (routed URL/endpoint) in a Webjump/* module — routes.xml declaration, the Http{Get,Post}ActionInterface-typed controller action class(es), and, for admin actions, etc/acl.xml plus an optional menu.xml entry. Use for a new URL/page/endpoint — 'add an admin page for managing X', 'create a controller for this AJAX request', 'wire up a new route'. Not for pure REST exposure with no controller (create-repository's webapi.xml) or intercepting a method without a new URL (create-plugin). Adapted from a sibling team's skill (FTD B2C); ACL root and shared-base-class references adapted since Bunge has no equivalent shared framework."
---

# Create Controller

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-controller` skill.
Two deliberate changes: the source nests admin ACL under its own fixed
`FTD_AdminMenu::menu` root — Bunge has no equivalent, so ACL nests under **the module's
own root ACL resource** instead (declared once per module, reused by every controller in
it); and the source's frontName-vs-route-id bug story cites their own codebase's real
incident — kept here as the general rule without that specific unverified claim about
this codebase.

## 1. Scope contract

**Expected input, all confirmed before writing anything:** target module, frontend or
admin, what the URL does (delegate to existing business logic — never invented here),
brand-new route vs. extending an existing one vs. an outright override, single action vs.
a full CRUD set, what it returns, and — for admin — new ACL branch vs. reuse, and whether
it needs a nav entry.

**Produces:** `routes.xml` (or a `di.xml` preference for an outright override with no new
URL), the controller class(es), and — for admin actions — `etc/acl.xml` plus, optionally,
`etc/adminhtml/menu.xml`.

**Out of scope:** `etc/webapi.xml` (→ `create-repository`); admin grid/form UI Components
(→ `adobe-magento-ui-component-developer`); the business logic itself beyond wiring to an existing
Repository (→ `create-entity`/`create-repository`); unit tests (→ `create-unit-tests`).

## 2. Golden rules

1. **A route's `id` (layout handle namespace) and its `frontName` (the real runtime URL
   segment) are not interchangeable.** They're often the same string, which is exactly
   why it's easy to use one where the other belongs once they ever need to differ — this
   fails silently (a 404 in the browser, not a compile error).
2. **No class uses `ObjectManager`.** Constructor injection, typed by interface.
3. **A controller never injects a `Command` directly — always the
   `*RepositoryInterface`.** The Command is the Repository's own implementation detail.
4. **Never implement `CsrfAwareActionInterface` without a specific, confirmed reason.**
   Core's CSRF/form-key validation already runs on every POST action that doesn't opt
   out — bypassing it is a rare, justified exception, not a default.
5. **Never fabricate a `<sequence>` entry in `etc/module.xml`** just because two module
   names look related — only a confirmed real dependency justifies one.
6. **Never generate `etc/webapi.xml` or admin UI Components** — both are other skills'
   scope, not this one's.

## 3. Routing integrity — the #1 way an admin route silently 404s

| Rule | Why | Instead |
|---|---|---|
| Never use the route `id` in a runtime URL — `getUrl()`, `menu.xml action=`, a literal redirect | The `id` is an internal identifier (layout handle namespace); Magento builds the real URL from `frontName`. Using `id` compiles fine and 404s in the browser — `setup:di:compile` won't catch it | Always use the `frontName` declared in `routes.xml` for any URL path |
| Never use `frontName` in a layout XML filename | Layout filenames are `{route_id}_{controller_folder}_{action_name}.xml`, based on the route `id`, not `frontName` | Use the route `id` in the layout filename |

When `id` and `frontName` are **not** identical (route extension, or a deliberate naming
choice), confirm explicitly which value goes where before writing `menu.xml` or any
`getUrl()` call.

Verify before delivering:
```bash
grep -n 'route id=\|frontName=' app/code/Webjump/{Module}/etc/*/routes.xml
grep -n 'action=' app/code/Webjump/{Module}/etc/adminhtml/menu.xml   # must match frontName, not id
```
`setup:di:compile` does not catch this class of error — confirm in the browser that the
menu entry resolves to a real page, not a 404.

## 4. Security and scope boundaries (hard rules)

| Rule | Why | Instead |
|---|---|---|
| No `ObjectManager` in any generated class | Project-critical prohibition, no exception | Constructor injection, typed by interface |
| No `CsrfAwareActionInterface` without a specific, confirmed reason | Disables core's default CSRF/form-key validation on POST — an unjustified bypass is a silently-introduced vulnerability | Don't implement the interface; let core's default validation run |
| No `Command` class injected directly into a controller | Breaks the Repository pattern — Commands are internal to the Repository | Inject `*RepositoryInterface` instead |
| Never generate `etc/webapi.xml` from this skill | Out of scope — pure REST exposure is `create-repository`'s job | Run/point to `create-repository` |
| Never generate Admin Grid/Form UI Components from this skill | Out of scope — the UI Component layer is `adobe-magento-ui-component-developer`'s job | Run/point to `adobe-magento-ui-component-developer` |
| Never add a `<sequence>` entry to `etc/module.xml` without a confirmed real dependency | An invented dependency (by name similarity) silently and unpredictably breaks load order | Only add one with a concretely confirmed reason |

Verify before delivering:
```bash
grep -rn "ObjectManager::getInstance" app/code/Webjump/{Module}/Controller/ || echo "OK: no ObjectManager"
grep -rln "CsrfAwareActionInterface" app/code/Webjump/{Module}/Controller/   # expect empty, unless a justified exception
grep -rn "Model\\\\Command\\\\" app/code/Webjump/{Module}/Controller/       # expect empty
```

## 5. ACL and menu conventions

- Nest new ACL resources under the module's own root ACL resource — declared once per
  module and reused by every controller in it, not under a shared cross-module root.
- A menu entry's `action=` attribute is the `frontName`-based URL path, never the route
  `id` — see section 3.
- Only add a `menu.xml` entry for a page meant to appear in the admin nav (typically the
  `Index` action) — not for every controller in the module.

## 6. Definition of done

- [ ] Module confirmed to exist before writing anything
- [ ] `frontName` and route `id` correctly assigned to their respective use — verified
      per section 3, including a manual browser check for any admin nav entry
- [ ] No `ObjectManager`, no `Command` injected into a controller, no
      `CsrfAwareActionInterface` without a confirmed reason
- [ ] No `etc/webapi.xml` or UI Component generated by this skill
- [ ] Any `<sequence>` addition to `etc/module.xml` has a confirmed real reason, not a
      name-similarity guess
- [ ] ACL nested under the module's own root resource; menu entry (if any) present only
      for the nav-facing action
- [ ] `setup:di:compile` succeeds; the route/nav entry manually confirmed working in the
      browser, not just compiled
- [ ] Report names `create-unit-tests` on the new controller(s) as the next step

## Antipatterns

- Confusing a route's `frontName` with its `id` in a URL/redirect/`menu.xml action=` —
  silent until it 404s in the browser.
- Writing a shared abstract controller base class "by default" — Bunge has no equivalent
  to a project-wide base class; only introduce one when two sibling actions in the same
  module genuinely share logic beyond boilerplate.
- Implementing both `HttpGetActionInterface` and `HttpPostActionInterface` on an action
  that doesn't actually need to accept both methods.
- Adding a `<sequence>` entry "just in case" — only a confirmed real dependency justifies
  one.
- Modeling a new controller after legacy code in this repo that skips
  `declare(strict_types=1)`, `Http*ActionInterface`, or promoted constructor properties —
  legacy patterns already in the codebase are not a reference to imitate.
