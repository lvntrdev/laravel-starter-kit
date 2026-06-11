# Settings

The settings module centralizes operational configuration inside the admin panel.

## Sections

- `general` — app name, timezone, active interface languages, logo upload/remove, dashboard welcome message (optional WYSIWYG)
- `auth` — registration, email verification, password reset, two-factor availability, login throttle, and password policy (min length, expiry, complexity rules)
- `mail` — mailer, SMTP host/port, credentials, from address/name
- `storage` — media disk selection and S3-compatible / AWS credentials
- `file_manager` — upload size, accepted MIME list, audio/video toggles
- `turnstile` — feature toggle, site key, and secret key
- `api_integrations` — Postman and Apidog sync credentials; two configuration cards in a single tab
- `api_clients` — Passport OAuth2 client listing, creation, update, and delete
- `api_tokens` — Personal Access Token listing, revocation, and one-time token minting
- `system_health` — `sk:doctor` results inside Settings for system admins

## Storage Model

Settings are stored in the database and resolved through the setting service layer.

Sensitive keys can be encrypted through `config/settings.php`.

Current examples:

- `mail.password`
- `storage.spaces_secret`
- `storage.aws_secret`
- `turnstile.secret_key`
- `postman.api_key`
- `apidog.access_token`

Secret values are not exposed back to the frontend. The settings payload uses `*_is_set` booleans so the UI can show whether a value already exists without returning the raw secret string.

## Route Surface

The admin module exposes routes such as:

- `settings.index`
- `settings.update.general`
- `settings.update.auth`
- `settings.update.mail`
- `settings.update.storage`
- `settings.update.fileManager`
- `settings.update.turnstile`
- `settings.update.postman` — `PUT /settings/postman`
- `settings.update.apidog` — `PUT /settings/apidog`
- `settings.testMail`
- `settings.upload.logo` — `POST settings/logo`
- `settings.delete.logo` — `DELETE settings/logo`
- `system-health.run` — `POST /system-health/run` from the **Settings → System Health** tab

## Runtime Notes

- `SettingsController@index` delivers the grouped settings payload plus timezone options and the configured language list
- write operations stay thin: FormRequest -> DTO -> Action
- secret fields in `mail`, `storage`, and `turnstile` return `null` plus `*_is_set` flags instead of the stored values
- submitting an empty secret field keeps the current stored or config-backed value
- logo upload/remove is intentionally handled as a small JSON side flow outside the main `SkForm`
- logo upload/remove now use the standard `ApiResponse` envelope
- disabling two-factor in the Auth tab shows a confirmation before the admin submits the change because it affects user security posture
- the Security tab is divided into three sub-tabs: **Authentication** (registration, email verification, password reset, two-factor, login throttle), **Password Policy** (minimum length, expiry days, complexity toggles), and **Cloudflare Turnstile**
- `auth.login_throttle = '0'` disables the Fortify login rate limiter; default is `'1'` (throttle active); disabling is a deliberate security downgrade exposed only to administrators
- password policy settings (`password_min_length`, `password_require_mixed_case`, `password_require_numbers`, `password_require_symbols`) are applied to every new password via `PasswordValidationRules`; existing passwords are never invalidated
- `auth.password_expiry_days > 0` enables the `EnsurePasswordNotExpired` middleware; users whose `password_changed_at` is older than the configured number of days are redirected to a dedicated, guest-style password-expired screen (route `password.expired`) until they update their password; setting `0` disables expiry
- password expiry exempt routes: the password-expired page (`password.expired`), logout, two-factor challenge, Fortify password endpoints — redirect loop is not possible
- `users.password_changed_at` is stamped on every password write (registration, reset, profile update, admin user create/update); existing users received a `now()` back-fill at migration time
- Turnstile settings drive the auth-form challenge behavior used by login, register, and forgot-password
- API integration settings store Postman and Apidog sync credentials; secret fields are encrypted and use `*_is_set` flags like other secrets
- API client and token management are separate Settings tabs backed by Passport admin routes; newly created secrets/tokens are displayed once and then cannot be recovered
- System Health is rendered as a Settings tab and receives its report from `sk:doctor --json`
- test-mail failures are logged server-side and return a generic flash error instead of exposing raw SMTP exception details
- the **General** tab's `welcome_message` field is authored through `FB.editor()`; content is sanitised through `App\Support\HtmlSanitizer` on write (FormRequest `prepareForValidation` hook) and again on read (DashboardController defense-in-depth pass) before it renders on the admin dashboard
- `SettingService::setValue()` / `setGroup()` run keys listed in the `HTML_SAFE_KEYS` whitelist through `HtmlSanitizer::clean()` — FormRequest, tinker, scheduled commands and queued jobs all go through the same sanitizer, so non-sanitised HTML cannot be persisted via the normal setting API

## Auth setting keys

The `auth` group exposes the following keys. Two default values are relevant for every installation:

- **Seeder (fresh install)** — the value written by `_03_SettingSeeder` during `sk:install`. Only applies to new installations; the seeder never overwrites an existing row.
- **Runtime fallback (key absent from DB)** — the value used by `SettingsDefaultsQuery::auth()` when the key does not exist in the database. This is what upgrading installations get before re-seeding. Fallbacks reflect the hardened baseline introduced in v13.6.0: `email_verification` and `two_factor` default to enabled (`'1'`) on the read path for installations that have never seeded those keys.

| Key | Type | Seeder (fresh install) | Runtime fallback (key absent) | Description |
|---|---|---|---|---|
| `registration` | boolean | `'1'` | `'1'` | Allow new users to self-register |
| `password_reset` | boolean | `'1'` | `'1'` | Allow email-based password reset |
| `email_verification` | boolean | `'0'` | `'1'` | Require email verification before login |
| `two_factor` | boolean | `'0'` | `'1'` | Enable two-factor authentication |
| `login_throttle` | boolean | `'1'` | `'1'` | Enable Fortify login rate limiter |
| `password_min_length` | integer (string) | `'10'` | `10` | Minimum password length |
| `password_expiry_days` | integer (string) | `'0'` | `0` | Days before a password expires; `0` = no expiry |
| `password_require_mixed_case` | boolean | `'1'` | `'1'` | Require upper and lower case in passwords |
| `password_require_numbers` | boolean | `'1'` | `'1'` | Require at least one digit in passwords |
| `password_require_symbols` | boolean | `'1'` | `'1'` | Require at least one symbol in passwords |

All values are stored as strings in the database. The `SettingsDefaultsQuery::auth()` method casts them to the correct PHP types before they reach the frontend or enforcement layer.

## HTML-safe keys

Setting keys that hold rich-text content are listed in `SettingService::HTML_SAFE_KEYS`. Values written to these keys — through any path — are sanitised before hitting the database. Currently tracked:

- `general.welcome_message`

Add new entries when a future setting holds editor-authored HTML. See the [FormBuilder Editor field API](formbuilder.md#editor-field-api) for the frontend side.

## Best Practice

Keep editable operations in dedicated Actions and use request classes for validation. The settings UI should stay thin and reflect the service layer, not replace it.
