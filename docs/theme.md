# Theme System — Lvntr Starter Kit

This document covers:

- The `AppShell` / `AdminLayout` composition model and how to build a new layout
- The `themes/main` + `themes/custom` CSS structure, the build-time resolver, and the full-replacement + fallback model
- `VITE_SK_THEME` activation
- Step-by-step recipe for adding a custom theme or overriding a single component's styles

---

## Layout architecture

### AppShell — structural shell

`resources/js/layouts/AppShell.vue` is the structural backbone of the admin panel. It owns:

- The `.admin-layout` root and `.admin-main` / `.admin-content` regions
- Sidebar collapse and mobile-open state via `useSidebar` (single owner — do not import `useSidebar` again in child components)
- Responsive class modifiers (`admin-main--expanded`, `admin-main--collapsed`, `admin-main--mobile`) on `.admin-main`

`AppShell` exposes five named slots:

| Slot | Receives (scoped) | Purpose |
|---|---|---|
| `#sidebar` | `{ collapsed, mobileOpen, isMobile, closeMobile }` | Sidebar region |
| `#header` | `{ collapsed, isMobile, toggle }` | Top header bar |
| `default` | — | Page content (`.admin-content` wrapper) |
| `#footer` | — | Footer strip |
| `#overlays` | — | Global overlays (dialogs, toasts, confirm) |

`AppShell` carries no page props, no flash-to-toast bridge, and no `<Head>`. It is a pure structural wrapper.

### AdminLayout — thin composition

`resources/js/layouts/AdminLayout.vue` wraps `AppShell` and fills every region with the standard admin components:

- `#sidebar` → `AdminSidebar`
- `#header` → `AdminHeader` (forwards dark-mode toggle)
- default → `AdminPageHeader` + `<slot />` (page content) + `<slot name="page-actions" />`
- `#footer` → `AdminFooter`
- `#overlays` → `ConfirmDialogComponent`, `ToastComponent`, `AppDialog`, `ImageLightbox`

It also handles the flash-to-toast bridge (`router.on('finish', …)`) and `<Head :title="title" />`.

**External contract (unchanged):**

```vue
<AdminLayout title="Users" subtitle="Manage users" :back-url="route('admin.users.index')">
    <template #page-actions>
        <Button label="New User" />
    </template>

    <!-- page content -->
</AdminLayout>
```

Props: `title?: string`, `subtitle?: string`, `backUrl?: string | boolean`.
Slots: `default` (page body), `page-actions` (rendered inside `AdminPageHeader`'s `#actions` slot).

All existing pages continue to import `@/layouts/AdminLayout.vue` — the public contract is identical.

### Building a new layout

To create a layout that shares the shell structure but arranges regions differently, compose `AppShell` directly:

```vue
<!-- resources/js/layouts/MinimalLayout.vue -->
<script setup lang="ts">
    import AppShell from '@/layouts/AppShell.vue';
    import MinimalSidebar from '@/layouts/components/MinimalSidebar.vue';
    import MinimalHeader from '@/layouts/components/MinimalHeader.vue';
</script>

<template>
    <AppShell>
        <template #sidebar="{ collapsed, mobileOpen, isMobile, closeMobile }">
            <MinimalSidebar
                :collapsed="collapsed"
                :mobile-open="mobileOpen"
                :is-mobile="isMobile"
                @close-mobile="closeMobile"
            />
        </template>
        <template #header="{ collapsed, isMobile, toggle }">
            <MinimalHeader :collapsed="collapsed" :is-mobile="isMobile" @toggle-sidebar="toggle" />
        </template>
        <slot />
    </AppShell>
</template>
```

Pages using the new layout import it by path — `AdminLayout` pages are unaffected.

---

## Two style layers

The theme system has two independent, complementary layers. Both are keyed off the same `VITE_SK_THEME` environment variable and resolved at build time — there is no runtime theme switch.

| Layer | What it covers | Resolver | Artifact |
|---|---|---|---|
| **CSS theme override** | Layout dimensions, colours, shadows, component CSS classes | `scripts/sk-theme-build.mjs` writes `_active.css` | Generated file (`_active.css`), gitignored |
| **PrimeVue preset** | Primary palette, surface colours, border radius, component tokens (`--p-*` variables) | `resolveId` hook in `scripts/vite-plugin-sk-theme.mjs` | None — pure JS module resolution |

The two layers are independent: you can override the CSS without touching the preset and vice versa. A `tokens.css` override typically reads the `--p-*` tokens the preset emits — see [Dependency chain](#dependency-chain-tokens-and-preset) below.

---

## CSS theme system

### Directory structure

```
resources/css/theme/
├── theme.css              # Entry: imports _active.css only
├── _active.css            # GENERATED — do not edit; gitignored
└── themes/
    ├── main/              # Built-in base theme (source of truth for all slots)
    │   ├── tokens.css     # CSS custom properties: layout dims, colours, shadows
    │   ├── fonts.css      # Web font declarations
    │   ├── _base.scss     # Base reset / typography
    │   ├── layout/
    │   │   ├── shell.css        # .admin-layout, .admin-main, Vue transitions
    │   │   ├── sidebar.css      # .admin-sidebar*, .admin-overlay
    │   │   ├── header.css       # .admin-header*
    │   │   ├── page-header.css  # .admin-page-header*
    │   │   └── footer.css       # .admin-footer*
    │   ├── components/
    │   │   ├── card.css
    │   │   ├── confirm.css
    │   │   ├── datatable.css
    │   │   ├── dialog.css
    │   │   ├── editor.css
    │   │   ├── formbuilder.css
    │   │   ├── menus.css
    │   │   ├── navigation.css
    │   │   ├── primevue.css
    │   │   ├── tabs.css
    │   │   ├── tag.css
    │   │   └── toast.css
    │   ├── _auth.scss     # Auth layout styles
    │   └── utilities.css  # Tailwind utility overrides
    └── custom/            # Your override theme (ships empty)
        └── README.md
```

### How the resolver works

`scripts/sk-theme-build.mjs` is invoked explicitly as part of the `dev` and `build` scripts and writes `theme/_active.css`. The model is **full-replacement + fallback**:

1. `themes/main/` is the canonical slot list and import order.
2. For each slot, the resolver loads `themes/<active>/<slot>` **if the file exists**; otherwise it loads `themes/main/<slot>`.
3. The result is a single `_active.css` with one `@import` per slot in the correct cascade order.

Import order (preserved from the original cascade):

```
tokens → fonts → _base → layout/* → components/* → _auth → utilities
```

Every layer is a slot. A custom theme that ships only `components/datatable.css` overrides just the datatable. Everything else falls through to `main`. A file in `custom/` whose path does not match any `main/` slot is ignored — the resolver iterates `main`'s slot list, not the custom directory.

Note: `_base.scss` and `_auth.scss` are `.scss` files — their extension is preserved. The resolver is extension-aware; Sass compilation is handled by the same pipeline as before.

### `VITE_SK_THEME` activation

Set the active theme in `.env`:

```dotenv
VITE_SK_THEME=custom
```

The default is `main`. If the variable is absent or blank, `main` is used. Run `npm run dev` or `npm run build` — the resolver runs as an explicit step in those scripts and regenerates `_active.css` before Vite starts. This approach is safe under `ignore-scripts=true` because it does not rely on npm lifecycle hooks.

To preview the resolved manifest without a full build:

```bash
node scripts/sk-theme-build.mjs
# [sk-theme-build] theme="custom" → resources/css/theme/_active.css (22 slots, 1 override)
```

Open `resources/css/theme/_active.css` to see exactly which file each slot resolved to. Overridden slots are annotated with `/* override */`.

### `_active.css` — generated artifact

`_active.css` is a **build artifact**:

- Listed in `.gitignore` — never commit it.
- Not hash-tracked by `sk:update` — it is regenerated on every `npm run dev` / `npm run build`.
- Always present before Vite starts because the resolver is an explicit step in both scripts.

---

## Custom theme recipe

### Override a single component

To restyle the datatable without touching anything else:

1. Copy the slot you want to override:

   ```bash
   cp resources/css/theme/themes/main/components/datatable.css \
      resources/css/theme/themes/custom/components/datatable.css
   ```

2. Edit `themes/custom/components/datatable.css`. Keep the same class names — the Vue components target them.

3. Set `VITE_SK_THEME=custom` in `.env`.

4. Run `npm run dev`. The resolver runs as an explicit step in the `dev` script and regenerates `_active.css`; `themes/custom/components/datatable.css` replaces `themes/main/components/datatable.css` in the import list. All other slots remain from `main`.

Verify the resolved manifest:

```
@import './themes/main/tokens.css';
@import './themes/main/fonts.css';
@import './themes/main/_base.scss';
@import './themes/main/layout/footer.css';
…
@import './themes/custom/components/datatable.css'; /* override */
@import './themes/main/components/dialog.css';
…
@import './themes/main/_auth.scss';
@import './themes/main/utilities.css';
```

### Override the token set

To change layout dimensions, colours, or shadows globally, override `tokens.css`:

```bash
cp resources/css/theme/themes/main/tokens.css \
   resources/css/theme/themes/custom/tokens.css
```

Edit the custom properties. All layout regions and components read from these tokens, so a token change propagates everywhere without touching individual component files.

### Override a layout region

```bash
cp resources/css/theme/themes/main/layout/sidebar.css \
   resources/css/theme/themes/custom/layout/sidebar.css
```

Edit as needed. Only the sidebar slot is replaced; the rest of the layout comes from `main`.

### Override fonts or utilities

The font declarations and Tailwind utility overrides are now slots like any other. To swap in your own fonts without touching layout or components:

```bash
cp resources/css/theme/themes/main/fonts.css \
   resources/css/theme/themes/custom/fonts.css
```

Edit the `@font-face` declarations in `themes/custom/fonts.css`. On the next build, `_active.css` will use your font file instead of the `main` version; all other slots remain from `main`.

Similarly, to override Tailwind utility overrides:

```bash
cp resources/css/theme/themes/main/utilities.css \
   resources/css/theme/themes/custom/utilities.css
```

Because `utilities.css` is unlayered and emitted last in the cascade, it wins over every layered rule — the same precedence behaviour as before. Edit freely.

### Override auth styles

```bash
cp resources/css/theme/themes/main/_auth.scss \
   resources/css/theme/themes/custom/_auth.scss
```

Edit `themes/custom/_auth.scss`. The `.scss` extension is required — the resolver and Sass compiler are extension-aware.

### Complete slot reference

| Slot | File | Notes |
|---|---|---|
| tokens | `tokens.css` | CSS custom properties (light + dark) |
| fonts | `fonts.css` | `@font-face` declarations |
| base | `_base.scss` | Reset / typography; `.scss` extension required |
| layout/footer | `layout/footer.css` | `.admin-footer*` |
| layout/header | `layout/header.css` | `.admin-header*` |
| layout/page-header | `layout/page-header.css` | `.admin-page-header*` |
| layout/shell | `layout/shell.css` | `.admin-layout`, `.admin-main`, Vue transitions |
| layout/sidebar | `layout/sidebar.css` | `.admin-sidebar*`, `.admin-overlay` |
| components/card | `components/card.css` | |
| components/confirm | `components/confirm.css` | |
| components/datatable | `components/datatable.css` | |
| components/dialog | `components/dialog.css` | |
| components/editor | `components/editor.css` | |
| components/formbuilder | `components/formbuilder.css` | |
| components/menus | `components/menus.css` | |
| components/navigation | `components/navigation.css` | |
| components/primevue | `components/primevue.css` | |
| components/tabs | `components/tabs.css` | |
| components/tag | `components/tag.css` | |
| components/toast | `components/toast.css` | |
| auth | `_auth.scss` | Auth layout styles; `.scss` extension required |
| utilities | `utilities.css` | Tailwind utility overrides; unlayered, emitted last |

### Notes

- A custom theme replaces slots **whole-file** — there is no cascading diff. Copy the `main` file as a starting point.
- Custom themes cannot add new slots. A file in `custom/` that has no matching path in `main/` is never imported.
- `VITE_SK_THEME=main` (the default) produces a build byte-identical to the stock panel — no custom files are loaded.
- Remove `themes/custom/` files you no longer need; the resolver falls back to `main` automatically.
- Slots with a `.scss` extension (`_base.scss`, `_auth.scss`) must keep that extension in `themes/custom/` too.

---

## PrimeVue preset layer

### How it works

`resources/js/theme/preset.ts` is the base PrimeVue styled-mode preset — it defines the primary palette, surface colours, border radius, and per-component tokens. `app.ts` imports it as `@/theme/preset`. The import path never changes.

The `resolveId` hook in `scripts/vite-plugin-sk-theme.mjs` intercepts the `@/theme/preset` specifier at build time:

- If `VITE_SK_THEME` is not `main` **and** `resources/js/theme/themes/<active>/preset.ts` exists → resolves to that override file.
- Otherwise → resolves to the base `resources/js/theme/preset.ts`.

The base file stays where it has always been — the kit never moves it because it is the most commonly customised file in a consumer project.

### Directory layout

```
resources/js/theme/
├── preset.ts                      # Base preset (consumer-customizable; stays here)
└── themes/
    └── custom/
        ├── .gitkeep
        ├── README.md              # Override recipe
        └── preset.ts              # (you create this — see recipe below)
```

The `themes/custom/` directory ships empty (only the `.gitkeep` and `README.md`). The `custom` theme's preset is absent by default, so the base preset is used and the PrimeVue look is byte-identical to the stock panel.

### Give a custom theme its own PrimeVue palette

1. Create `resources/js/theme/themes/custom/preset.ts`. The simplest approach is to import the base preset and override only the palette:

   ```ts
   import { definePreset } from '@primevue/themes';
   import Aura from '@primevue/themes/aura';
   import AppPreset from '../../preset';

   export default definePreset(Aura, {
       ...AppPreset,
       semantic: {
           ...AppPreset.semantic,
           primary: {
               50:  '{emerald.50}',
               100: '{emerald.100}',
               200: '{emerald.200}',
               300: '{emerald.300}',
               400: '{emerald.400}',
               500: '{emerald.500}',
               600: '{emerald.600}',
               700: '{emerald.700}',
               800: '{emerald.800}',
               900: '{emerald.900}',
               950: '{emerald.950}',
           },
       },
   });
   ```

2. Set `VITE_SK_THEME=custom` in `.env`.

3. Run `npm run dev` or `npm run build`. The plugin resolves `@/theme/preset` to your override; with no override file present it falls back to the base.

### Notes

- Export the preset object as the **default export** — this is what `app.ts` passes to PrimeVue's `preset` option.
- The override applies only when `VITE_SK_THEME=custom` and the file exists. Any other value falls back to the base.
- There is no generated artifact. This is pure module resolution — no `_active` file to gitignore, no npm chain to maintain.

### Dependency chain — tokens and preset

`resources/css/theme/themes/main/tokens.css` (and any `themes/custom/tokens.css` override) defines the `--admin-*` custom properties the layout and components use. These are **live references** to the `--p-*` variables the PrimeVue preset emits at runtime:

```css
/* tokens.css — admin roles point at PrimeVue's preset output */
--admin-sidebar-bg: var(--p-surface-900);
--admin-sidebar-item-active-bg: var(--p-primary-color);
```

Because they are live `var()` references, changing the primary/surface palette in your preset override **flows through automatically** — the `--admin-*` roles re-resolve to the new `--p-*` values; there is nothing to keep "in sync".

Override `tokens.css` only if you want to re-map which PrimeVue token an admin role points at (e.g. make the sidebar read a different surface step):

```bash
cp resources/css/theme/themes/main/tokens.css \
   resources/css/theme/themes/custom/tokens.css
```

Then edit the `--admin-*` properties to reference your chosen `--p-*` tokens.

---

## Dark mode

CSS custom properties for dark mode are declared inside `.dark { … }` blocks in `themes/main/tokens.css` (and in the per-region layout files where layout-specific dark overrides exist). If you override `tokens.css`, copy those `.dark` blocks too.

Dark mode is toggled by `useDarkMode` — it adds / removes the `dark` class on `<html>`. No build step or separate CSS file is needed.

---

## Related docs

- `docs/install.md` — first-time setup
- `docs/update.md` — `sk:update` and hash-tracked stubs
- `docs/UPGRADE.md` — migration guide for existing projects
