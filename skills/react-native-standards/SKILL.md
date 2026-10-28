---
name: react-native-standards
description: "Framework-level React Native / Expo conventions: WebView integration patterns, native basics (icon/splash via Expo config), testing layers, build/release via EAS, and common anti-patterns. Use for any React Native code change or review. Client-agnostic — applies to any React Native project, not just Bunge Mobile; company-specific process (git, review routing, workspace) lives in bunge-mobile-flow instead."
---

# react-native-standards

Framework-level conventions for React Native work. Client-agnostic — applies
to any React Native project, not just Bunge Mobile. Company-specific process
(git, review routing, workspace) lives in `bunge-mobile-flow`.

## Project setup

- Use **Expo** (managed workflow) unless a specific native module requires
  bare React Native — Expo gives you OTA updates, a managed build pipeline
  (EAS Build), and far less native-toolchain maintenance, which matters more
  for a small team than low-level native access. Only eject / go bare if a
  concrete native dependency doesn't support Expo's managed workflow.
- TypeScript by default for new RN projects — catches prop/navigation-param
  mismatches at compile time instead of at runtime on a device.
- One app, one repo. Don't split Android/iOS-specific logic into separate
  repos — platform differences belong in `Platform.OS` branches or
  `.ios.tsx`/`.android.tsx` file suffixes, in the same codebase.

## WebView integration (the core of a webview-wrapper app)

- Use `react-native-webview` (the community-maintained, Expo-compatible
  package) — not a hand-rolled native WebView bridge.
- Handle these explicitly, they are not automatic:
  - **Loading state**: show a native splash/spinner while the WebView's
    first page loads — a blank white screen on cold start reads as a crash
    to a user.
  - **Navigation**: `onShouldStartLoadWithRequest` to decide whether a link
    tapped inside the WebView opens in-app vs. in the system browser (e.g.
    external payment gateway redirects, PDF downloads) — don't let every
    link silently navigate inside the WebView by default without this
    decision being deliberate.
  - **Error/offline state**: `onError`/`onHttpError` need a real retry UI,
    not a silent blank page, since the WebView is the entire app surface.
  - **Back button (Android)**: hardware back button must navigate the
    WebView's own history (`webViewRef.current.goBack()`) when there's
    history to go back to, and only exit the app when there isn't — the
    default Android behavior (exit app) is wrong for a WebView-first app.
- Never hardcode the wrapped site's URL in more than one place — one config
  constant (or environment-driven) that both the WebView `source` and any
  deep-link handler reference.

## Native basics (icon, splash screen)

- App icon and splash screen are configured once in `app.json`/`app.config.js`
  (Expo) — `icon`, `splash.image`, `splash.backgroundColor`, plus the
  Android adaptive-icon pair (`foregroundImage`/`backgroundColor`). Generate
  all required resolutions via `npx expo-doctor`/EAS build, don't hand-place
  PNGs at guessed sizes.
- Splash screen must visually match the app's real loading state — if the
  WebView takes a few seconds on first load, keep the native splash visible
  (via `expo-splash-screen`'s `preventAutoHideAsync`/`hideAsync`) until the
  WebView actually finishes loading, not just until the JS bundle is ready.

## Testing layers — pick the cheapest one that proves the work

1. **Unit tests** (Jest + `@testing-library/react-native`) — pure logic,
   small components, no device/simulator needed. Default choice.
2. **Component/integration tests** — a screen with its real navigation and
   state wiring, still no device (Jest + RTL, mocked native modules).
3. **E2E on a simulator/device** (Detox or Maestro) — reserve for the
   actual user-facing flow (app opens → WebView loads → navigation works),
   not for logic already covered by layer 1/2.

## Build and release

- **EAS Build** (if using Expo) for both Android and iOS — don't require a
  local Xcode/Android Studio toolchain for every build; that's what makes a
  cloud-built MVP tractable for a web-focused team.
- Android: a release build needs a real keystore, not the Expo-managed debug
  one, before any real store submission — flag this explicitly as a
  not-yet-done step at MVP stage rather than silently using a dev keystore
  long-term.
- iOS: a real Apple Developer account + provisioning profile is required
  before any TestFlight/App Store step — same flag-it-explicitly rule.

## Common anti-patterns to flag, not silently fix

- Embedding business logic (pricing, cart rules, catalog data) in the
  React Native app when the WebView already renders the real storefront —
  a WebView-wrapper MVP's native code should stay thin: chrome, navigation,
  loading/error states. Business logic lives in Magento, not duplicated
  natively.
- Using `WebView`'s `injectedJavaScript` to reach into the Magento page's
  DOM for anything beyond cosmetic adjustments — fragile, breaks silently
  on every storefront frontend change, and duplicates behavior the backend
  already owns.
- Committing `.env`/signing credentials/API keys into the repo — use EAS
  Secrets or a `.env` file that's gitignored, same discipline as any other
  codebase.
- Testing only on one platform (usually Android, since it's easier to spin
  up without a Mac) and assuming iOS behaves the same — WebView edge cases
  (back gesture, safe-area insets, keyboard handling) differ by platform.
