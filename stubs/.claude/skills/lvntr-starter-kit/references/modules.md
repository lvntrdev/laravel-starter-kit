# Built-in modules

> `lvntr-starter-kit` skill'inin referans detayı — gerekince okunur.

You don't build these — you extend or configure them.

### File Manager

- Pluggable contexts (e.g. `users.avatar`, `products.gallery`) — register via
  the file manager service so uploads land in the right collection
- Disk-aware: configure DigitalOcean Spaces / S3 in `config/filesystems.php`
  (kit injects the `spaces` disk during install)
- Frontend page lives at `resources/js/pages/Admin/FileManager/` — uses
  Spatie MediaLibrary under the hood via the `HasMediaCollections` trait

### Activity Log

- Powered by `spatie/laravel-activitylog` — config at `config/activitylog.php`
- Models log automatically when they use the `HasActivityLogging` trait
- The activity logs panel at `/admin/activity-logs` is filterable / browsable

### Settings panel

- Groups defined in `config/settings.php` (general / auth / mail / storage /
  file-manager). Add a key there and the UI renders the field automatically
- Backed by a settings table; values cached and accessed via `setting('key')`

### Definitions

- DB-backed enums shared between forms and tables. Two consumers:
  `FB.select().definitionOptions('key')` and
  `DB.column().tag('definition').tagKey('key')`
- Fetched once via `useDefinition()` and cached client-side
- Add new definitions via the seeder or the Definitions admin page

### OAuth2 API (Laravel Passport)

- Personal access tokens, authorization code, client credentials, device flow
- Token TTLs configured in `config/starter-kit.php` under `passport.*`
- Optional scopes: populate `passport.scopes` then attach `middleware('scope:foo')`
  to API routes you want restricted
