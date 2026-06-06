# `custom` PrimeVue preset

This directory lets a non-`main` theme ship its **own PrimeVue styled-mode
preset** — the primary palette, surface colors, radius, and component tokens
that `@/theme/preset` provides. It ships **empty** on purpose: with no
`preset.ts` here, the build resolves `@/theme/preset` to the base
`resources/js/theme/preset.ts`, so the PrimeVue look is byte-identical to the
stock panel.

This is the JS-preset counterpart to the per-slot CSS override under
`resources/css/theme/themes/custom/`. The two are independent layers:

1. **CSS theme override** — `resources/css/theme/themes/{main,custom}/*` →
   build-time `_active.css` (handled by `scripts/sk-theme-build.mjs`).
2. **PrimeVue preset** — base `resources/js/theme/preset.ts` + optional override
   `resources/js/theme/themes/<name>/preset.ts` → resolved at build time by the
   alias `customResolver` in `vite.config.ts` (the `resolveActivePreset` helper
   exported from `scripts/vite-plugin-sk-theme.mjs`).

## How it works (base in place + per-theme override)

The base preset stays where it is (`resources/js/theme/preset.ts`) — it is the
file you most often customize for your brand color, so the kit never moves it.
The alias resolver in `vite.config.ts` intercepts the `@/theme/preset` import at build time and:

- if `VITE_SK_THEME` is not `main` **and** `themes/<active>/preset.ts` exists →
  resolves to that override,
- otherwise → resolves to the base `theme/preset.ts`.

There is no generated artifact — this is pure module resolution, so no
package.json chain and no `ignore-scripts` exposure. One build, one bundle, no
runtime theme switch.

## Give the `custom` theme its own PrimeVue palette

1. Create `resources/js/theme/themes/custom/preset.ts`.
2. Build it with PrimeVue's `definePreset` (same as the base file). The simplest
   start is to import the base preset and override only what you need:

   ```ts
   import { definePreset } from '@primevue/themes';
   import Aura from '@primevue/themes/aura';
   import AppPreset from '../../preset';

   // Override just the primary palette; everything else inherits the base.
   export default definePreset(Aura, {
       ...AppPreset,
       semantic: {
           ...AppPreset.semantic,
           primary: {
               50: '{emerald.50}',
               // … 100–950
               500: '{emerald.500}',
           },
       },
   });
   ```

3. Activate the theme in `.env`:

   ```dotenv
   VITE_SK_THEME=custom
   ```

   Then run `npm run dev` / `npm run build`. The plugin resolves
   `@/theme/preset` to your override; with no override file present it falls back
   to the base.

## Notes

- Keep the export shape compatible with what `app.ts` feeds PrimeVue
  (`{ preset: AppPreset }`) — export the preset object as the default export.
- The preset's `--p-*` token output is consumed by `resources/css/theme`'s
  `tokens.css`; if you change palette tokens here, keep CSS that reads them in
  sync.
- This skeleton is shipped by the kit; replace this README with your own once you
  start building a real custom preset.
