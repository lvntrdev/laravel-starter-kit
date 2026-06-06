# `custom` theme

This directory is the entry point for a theme that overrides `main` on a
per-slot basis. It ships **empty** on purpose — with no files here and the
default `VITE_SK_THEME=main`, the build is byte-identical to the stock panel.

## How it works (full-replacement + fallback)

The theme resolver (`scripts/sk-theme-build.mjs`) walks `themes/main/` to get
the canonical slot list and import order, then for each slot loads
`themes/<active>/<slot>` **if it exists**, otherwise falls back to
`themes/main/<slot>`. The result is written to `theme/_active.css` (a generated,
gitignored artifact). Override is **whole-file replacement**, not a diff.

A custom theme can only **replace** existing slots. A file whose path has no
match in `main/` is never imported — the resolver iterates the `main` slot list.

## All overridable slots

| Slot | File | Notes |
|---|---|---|
| tokens | `tokens.css` | CSS custom properties (light + dark) |
| fonts | `fonts.css` | `@font-face` declarations |
| base | `_base.scss` | Reset / typography — `.scss` extension required |
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
| auth | `_auth.scss` | Auth layout styles — `.scss` extension required |
| utilities | `utilities.css` | Tailwind utility overrides; unlayered, emitted last |

Cascade order is: `tokens → fonts → _base → layout/* → components/* → _auth → utilities`.

## Activate

Set in `.env`:

```dotenv
VITE_SK_THEME=custom
```

Then run `npm run dev` / `npm run build`. The resolver runs as an explicit step
in both scripts and regenerates `_active.css` for the active theme before Vite
starts. `npm run theme:build` is available for generating `_active.css` on demand
without a full build.

## Example — override just the fonts

```bash
cp resources/css/theme/themes/main/fonts.css \
   resources/css/theme/themes/custom/fonts.css
```

Edit `themes/custom/fonts.css` with your own `@font-face` declarations. On the
next build, `_active.css` will import your file (marked `/* override */`) and
keep `main` for everything else.

## Example — override just the datatable

```bash
cp resources/css/theme/themes/main/components/datatable.css \
   resources/css/theme/themes/custom/components/datatable.css
```

Copy `themes/main/components/datatable.css` here as a starting point and edit it.

## Example — override auth styles

```bash
cp resources/css/theme/themes/main/_auth.scss \
   resources/css/theme/themes/custom/_auth.scss
```

The `.scss` extension is required — the resolver is extension-aware.

## Notes

- `_active.css` is **generated** — never edit it and never commit it.
- Keep the same selectors/class names; the kit's Vue components target them.
- Slots with a `.scss` extension (`_base.scss`, `_auth.scss`) must use the same
  extension in `themes/custom/`.
- This skeleton is shipped by the kit; replace this README with your own once you
  start building a real custom theme.
