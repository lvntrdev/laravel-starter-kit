# Updating the kit safely (`sk:update` flow)

> `lvntr-starter-kit` skill'inin referans detayı — gerekince okunur.

The kit tracks every published file in `storage/starter-kit/hashes.json`.
On `sk:update`:

1. **SAFE_UPDATE paths** are always overwritten. These are kit-managed base
   files you must NOT customize:
   - `app/Domain/Shared/{Actions,Contracts,DTOs,Pipelines}/`
   - `app/Http/Middleware/{SecurityHeaders,AssignTraceId}.php`
   - `app/Helpers/sk-helpers.php`
   - `app/Traits/`
   - `app/Exceptions/ApiExceptionHandler.php`

2. **Hash-tracked files** (everything else): if your hash matches the original,
   `sk:update` overwrites. If not (you customized), it skips and reports.

3. **Run `--dry-run` first** to see what would change. Run `--force` only if
   you're sure your customizations are safe to lose.

4. **After update**: re-run `npm install && npm run build` to pick up new JS
   deps; check `CHANGELOG.md` of the package for breaking notes.

If you need to customize a SAFE_UPDATE file, **publish it first** with
`sk:publish` (when supported) or extend it via your `app/` layer — never
edit it in place.
