# Theming

The starter kit ships a **PrimeVue-token-centred** runtime theme system. Instead of separate CSS bundles or build-time switches, the active theme is a named PrimeVue `definePreset` object that is applied at boot from a database setting and can be swapped at runtime without a deployment.

---

## Architecture Overview

```
config/starter-kit.php          ← backend whitelist ('themes' key)
    ↕ must mirror
stubs/resources/js/theme/presets/index.ts   ← frontend preset registry
        |
        ├── default.ts          ← default preset (Material base + custom tokens)
        └── corporate.ts        ← second example preset (teal primary)

SettingsServiceProvider         ← reads appearance.theme from DB → config('starter-kit.theme')
HandleInertiaRequests           ← shares `theme` as Inertia shared prop on every request
app.ts                          ← resolves initial preset from shared prop → PrimeVue init (SSR-safe)
useTheme()                      ← runtime swap after hydration via usePreset()
```

**Two axes are independent:**

- **Color preset** (this guide) — controlled by `useTheme()` and the `appearance.theme` setting.
- **Light / dark mode** — controlled by `useDarkMode()`, which toggles the `.dark` class on `<html>`.

`setTheme()` never touches the `.dark` class. Switching from `default` to `corporate` in dark mode keeps dark mode intact and vice versa.

---

## Preset Registry

The registry at `stubs/resources/js/theme/presets/index.ts` is the single source of truth for selectable presets on the frontend side.

```ts
import defaultPreset from './default';
import corporatePreset from './corporate';

export const presets = {
    default: defaultPreset,
    corporate: corporatePreset,
} as const;

export type SkThemeName = keyof typeof presets;  // 'default' | 'corporate'
export const DEFAULT_THEME: SkThemeName = 'default';

export function resolvePreset(name: string | null | undefined): SkThemePreset { … }
```

`resolvePreset` is prototype-chain-safe (`Object.hasOwn`) — an unknown or prototype-inherited name always collapses to the `default` preset.

---

## Adding a New Preset

The steps below must all be completed together. A drift-guard test (`tests/Feature/Settings/UpdateAppearanceSettingsTest.php`) verifies that `config('starter-kit.themes')` and the frontend registry contain the same keys — adding only one side will cause that test to fail.

**1. Create the preset file**

```
stubs/resources/js/theme/presets/<name>.ts
```

Export a PrimeVue `definePreset` as the default:

```ts
// stubs/resources/js/theme/presets/ocean.ts
import { definePreset } from '@primevue/themes';
import Material from '@primevue/themes/material';

const OceanPreset = definePreset(Material, {
    semantic: {
        primary: {
            500: '#0EA5E9',
            // …
        },
    },
});

export default OceanPreset;
```

**2. Register it in the frontend registry**

```ts
// stubs/resources/js/theme/presets/index.ts
import oceanPreset from './ocean';

export const presets = {
    default: defaultPreset,
    corporate: corporatePreset,
    ocean: oceanPreset,          // add here
} as const;
```

**3. Add the name to the backend whitelist**

```php
// config/starter-kit.php
'themes' => [
    'default'   => 'sk-setting::appearance.themes.default',
    'corporate' => 'sk-setting::appearance.themes.corporate',
    'ocean'     => 'sk-setting::appearance.themes.ocean',   // add here
],
```

The `UpdateThemeSettingsRequest` validates the submitted preset name against `array_keys(config('starter-kit.themes'))`. A name that exists only in the frontend registry but not in this whitelist cannot be saved.

**4. Add translation keys** (optional but recommended)

```php
// stubs/lang/en/sk-setting.php  and  stubs/lang/tr/sk-setting.php
'appearance' => [
    'themes' => [
        'default'   => 'Default',
        'corporate' => 'Corporate',
        'ocean'     => 'Ocean',    // add here
    ],
],
```

---

## `useTheme()` Composable

Import from the composables barrel:

```ts
import { useTheme } from '@/composables';
```

Returned values:

| Name | Type | Description |
|---|---|---|
| `currentTheme` | `Readonly<Ref<SkThemeName>>` | Reactive active preset name. Seeded from the Inertia shared prop `theme`. |
| `themeNames` | `SkThemeName[]` | All selectable preset names from the registry. |
| `setTheme(name)` | `(name: string \| null \| undefined) => SkThemeName` | Applies the preset at runtime; returns the resolved name. Unknown names fall back to `default`. |

```ts
const { currentTheme, themeNames, setTheme } = useTheme();

// Apply a preset at runtime (client-side, not persisted to DB)
setTheme('corporate');

// Read the active preset name
console.log(currentTheme.value); // 'corporate'

// List all available presets
console.log(themeNames); // ['default', 'corporate']
```

`setTheme` is client-only safe — it calls `usePreset()` only when `typeof document !== 'undefined'`, so it does not break SSR.

---

## Appearance Settings — Admin "Appearance" Tab

**Location:** Admin Panel → Settings → Appearance tab

**Permission required:** `settings.update`

The tab provides two distinct actions:

- **Preview** — clicking a theme card calls `setTheme()`, which applies the preset immediately via `usePreset()`. This swap is **not persisted**. Navigating away without saving restores the previously saved theme (handled via `onBeforeUnmount`).
- **Save** — clicking the save button sends `PUT /settings/appearance` with `{ theme: <name> }`. On success the stored theme is updated and the next page load (or any new browser tab) will use it.

Route name: `settings.update.appearance` (`PUT /settings/appearance`).

---

## How the Active Theme Is Selected at Boot

1. `SettingsServiceProvider::boot()` reads `appearance.theme` from the database and sets `config(['starter-kit.theme' => <name>])`. If the setting row is absent the fallback is `'default'`.
2. `HandleInertiaRequests::share()` includes `'theme' => config('starter-kit.theme')` in every Inertia response.
3. `app.ts` receives the shared `theme` prop through `props.initialPage.props.theme`, resolves it via `resolvePreset()`, and passes the resolved preset to `PrimeVue`'s initial config. This happens on **both** the SSR server (which bakes the CSS into the initial HTML) and the client (which hydrates from the identical page) — there is no flash of unstyled content (FOUC).
4. After hydration, `useTheme()` reads the same shared prop to seed `currentTheme`.

---

## Dark Mode Relationship

Theme presets (color) and dark mode (light/dark) are **orthogonal**. Each preset defines both a `light` and a `dark` color scheme inside PrimeVue's `colorScheme` token. `useDarkMode()` toggles the `.dark` class on `<html>`, and PrimeVue applies the matching scheme automatically.

```
Active state = preset × mode

"default + dark"    → default's dark colorScheme tokens
"corporate + dark"  → corporate's dark colorScheme tokens
"corporate + light" → corporate's light colorScheme tokens
```

`setTheme()` does not touch `.dark`. `useDarkMode()` does not touch the preset. They operate independently.

---

## Backward Compatibility

Existing installs that have no `appearance.theme` setting row behave identically to before: `SettingsServiceProvider` falls back to `'default'`, which renders pixel-equivalent to the historical single-preset output. No visual change occurs on upgrade.
