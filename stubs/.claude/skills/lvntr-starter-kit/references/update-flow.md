# Updating the kit safely (`sk:update` flow)

> Reference detail for the `lvntr-starter-kit` skill — read on demand.

The kit tracks every published file in `storage/starter-kit/hashes.json`
(format v2). On `sk:update`:

1. **Hash-tracked files** (the published scaffold): if your file's hash still
   matches what the kit shipped, `sk:update` overwrites it with the new
   version. If not (you customized it), it is **skipped and reported**.

2. **SAFE_UPDATE paths** are always refreshed regardless of local edits.
   Since v13.6.x this list is intentionally tiny:
   - `app/Enums/PermissionEnum.php` — regenerated to stay in sync with the
     package's permission constants. Don't hand-edit it.

3. **NEVER_UPDATE paths** are installed once and never overwritten:
   - `config/permission-resources.php` (your resource matrix)
   - `config/settings.php` (your setting groups + `sensitive_keys` whitelist)

4. **Vendor-resident paths** (domain runtimes, kit middleware, helpers,
   `ApiException(Handler)`, FileManager HTTP layer, …) are **not copied at
   all** — they run from the vendor package. If an old app copy exists it is
   only *reported* (never auto-deleted), and that app copy keeps winning via
   the alias-skip invariant. Exception: the six kit migration app copies are
   force-deleted (safe — their basenames are already in the `migrations`
   table).

5. **Skipped-at-install paths** (`--without-ai-skill`) are recorded with a
   `__skipped__` sentinel and never re-added by update.

6. **Run `--dry-run` first.** Use `--force` only if your customizations are
   safe to lose — it ignores the registry and overwrites everything tracked.

7. **After update:** re-run `npm install && npm run build`; read the package
   `CHANGELOG.md` and `docs/UPGRADE.md` for breaking notes (e.g. the
   v13.5.11 → v13.6.0 theme-tree migration).

To customize something that runs from vendor: **publish it** (`sk:publish` —
components, composables, plugins, lang, config, helpers…) or **eject the
domain** (`sk:eject {Domain}`) and edit the app copy. Never edit vendor files
in place.
