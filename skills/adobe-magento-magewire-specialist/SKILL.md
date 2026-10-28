---
name: adobe-magento-magewire-specialist
description: "Magewire 3 components on a Hyvä storefront (Magento 2.4.8): component class and layout binding, wire:model modifiers, actions, events, lifecycle hooks, security of public properties and methods, caching, versions with Hyvä Checkout. Use only when the theme inherits from Hyva/default or Hyva/default-csp and the interaction needs Magento state on the server. Client-only state: adobe-magento-alpine-specialist."
---

# Magewire on Hyvä

Server-rendered components that re-render over AJAX: forms that validate, recalculate or save against
Magento. Only for Hyvä themes (`adobe-magento-hyva-specialist`); state that never leaves the browser
(tabs, toggles) is plain Alpine (`adobe-magento-alpine-specialist`).

## Rules

- **Install the theme integration**: `magewirephp/magewire` plus `magewirephp/magewire-hyva-theme`,
  which coordinates the Alpine loader so only one Alpine instance runs.
- **Version**: 3 is current; use 3.7.2 or later (GHSA-64j9-rg74-hqc7 affects 3.0.0-3.7.1; custom
  components calling inherited helpers from the browser may need changes). With Hyvä Checkout, keep the
  version Hyvä Checkout requires (`composer why magewirephp/magewire`) and add
  `magewirephp/magewire-hyva-checkout` for V3.
- **Component**: a class extending `Magewirephp\Magewire\Component`, bound to a block in layout with
  `<argument name="magewire" xsi:type="object" shared="false">Vendor\Module\Magewire\Foo</argument>`.
  `shared="false"` gives each block its own instance. The template reads `$magewire` and has one root
  element.
- **Public properties are browser-visible and editable; public methods are callable from the browser.**
  Keep ids, prices and permissions out of public properties or recompute them on the server, make
  helpers `protected`/`private`, and validate and authorize inside every public method.
- **Logic stays in services**: the component calls repositories and service contracts (injected in the
  constructor) and holds only form state. A Magewire class with business rules can't be reused by
  REST, GraphQL or the Luma store.
- **`wire:model` is deferred by default** in V3: the value travels with the next action or
  `wire:submit="save"`. Use `wire:model.live` (150 ms debounce on text, change it with
  `.live.debounce.300ms`) only when every change must reach the server, `wire:model.blur` on leaving the
  field. `.defer` and `.delay` are V1 syntax; `.lazy` is now an alias of `.change`.
- **Actions**: `wire:click="increment"`, `wire:submit="save"`; each one is a full request and re-render,
  so don't bind `.live` to fields that trigger heavy recalculation.
- **Events**: `$this->dispatch('vendor-foo-saved', id: $id)`; listeners declare `#[On('vendor-foo-saved')]`
  on a method. Events bubble by default; `->self()` and `->to('other')` narrow them. `emit*()` and
  `dispatchBrowserEvent()` are V1 and deprecated. Alpine listens with `@vendor-foo-saved.window`.
- **Hooks**: `boot()`, `booted()`, `hydrate()`, `dehydrate()`, `updatingXxx()`/`updatedXxx()` per
  property, `rendering()`, `rendered()`, `exception()`. There is no `render()`: change the template in
  `rendering()` with `magewireBlock()->setTemplate(...)`. Generic `updating`/`updated` receive the full
  property path, then the value.
- **Full page cache**: a component on a cacheable page is rendered into the cached HTML. Load
  per-customer or fresh data after page load with `wire:init` or lazy loading, never in the first render.
- **Customer sections**: after a save that changes cart or customer data, tell the storefront to reload
  sections (`reload-customer-section-data`, `adobe-magento-alpine-specialist`).

## Steps

1. Confirm Hyvä and the installed versions (`composer show 'magewirephp/*'`).
2. Component class with typed public form state and validated public actions; services injected.
3. Layout block with the `magewire` argument; template with one root, `wire:model` and `wire:submit`.
4. Cacheable page: per-customer data via `wire:init`.
5. Events to other components or Alpine with `dispatch()`.

## Verify

- `php "$T/magento-verify.php"` (`$T` is the `scripts/` folder of `adobe-magento-toolbox`): layout
  against the XSD and the component class exists.
- `composer show magewirephp/magewire | grep versions` is 3.7.2 or later (or the Hyvä Checkout pin).
- `composer audit` reports nothing for `magewirephp/*`.
- Browser network tab: one request per action, none per keystroke unless `.live` was intended; the
  page works with FPC enabled and a logged-in customer.
- Tamper test: change a public property in the browser devtools and call the action; the server rejects
  or recomputes it.

## Pitfalls

- Magewire trusting a public property (price, customer id) -> recompute on the server.
- Magewire block in full-page cache shows old data -> load with `wire:init` or lazy loading; test with FPC on.
- Two blocks share one component instance -> add `shared="false"`.
- V1 code (`$listeners`, `emit()`, `wire:model.defer`) after an upgrade -> port to V3 syntax; the BC
  rewrites run only on the Hyvä Checkout page.
- Two Alpine instances on the page -> missing `magewire-hyva-theme`.

## Docs

- https://docs.magewirephp.nl
- https://docs.magewirephp.nl/pages/html-directives/wire-model.html
- https://docs.magewirephp.nl/pages/getting-started/upgrade.html
- https://docs.hyva.io/checkout/hyva-checkout/index.html
