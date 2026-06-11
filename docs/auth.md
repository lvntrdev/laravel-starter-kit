# Authentication

The starter kit combines Laravel Fortify for web authentication and Passport for API authentication.

## Web Authentication

Built-in flows include:

- login
- register
- forgot password
- reset password
- email verification
- confirm password
- two-factor challenge

These screens live under `resources/js/pages/Auth/`.

When Turnstile is enabled from the settings panel, the login, register, and forgot-password forms render the shared `TurnstileWidget` and validate `cf_turnstile_response` server-side.

## Profile Security

Authenticated users also get profile security tools:

- profile info update
- password update
- two-factor settings
- recovery code display and regeneration behind password confirmation
- browser session management
- avatar upload and removal

These flows are mounted from the profile screen and related routes in `routes/web/profile-route.php`.

## Password Policy

The password policy is driven by the **Settings → Security → Password Policy** admin tab. Rules are stored as `auth.*` setting keys and applied at runtime by `PasswordValidationRules`.

| Setting key | What it enforces |
|---|---|
| `auth.password_min_length` | Minimum character count (default: `8`) |
| `auth.password_require_mixed_case` | Upper and lower case required |
| `auth.password_require_numbers` | At least one digit required |
| `auth.password_require_symbols` | At least one symbol required |

Every Fortify flow picks up the active rules automatically — registration, password reset, password confirmation, and profile password update. Admin user create/update flows also apply the same rules.

Existing users' passwords are not invalidated when the policy changes — only newly submitted passwords are measured against the current rules.

### Password expiry

Setting `auth.password_expiry_days` to a value greater than `0` enables the `EnsurePasswordNotExpired` middleware. Authenticated users whose `password_changed_at` timestamp is older than the configured number of days are redirected to a dedicated, guest-style password-expired screen (route `password.expired`) until they set a new password. Setting `0` (the default) disables expiry entirely.

`password_changed_at` is stamped automatically on every password write: registration, password reset, profile update, and admin user create/update. Existing users received a `now()` back-fill when the migration ran, so they start the expiry clock from the deployment date rather than being immediately expired.

## Runtime Rules

- inactive users cannot start a web session; the Fortify login pipeline blocks accounts whose status is not `active`
- login is rate-limited by IP and by email/IP combinations when `auth.login_throttle = '1'` (the default); setting it to `'0'` in Settings → Security disables the Fortify rate limiter
- the two-factor challenge flow has its own limiter
- the two-factor challenge is **single-use** — any wrong code, empty submit, or invalid recovery code immediately invalidates the challenge id; the client must re-login to obtain a fresh one
- the forgot-password POST route receives Turnstile middleware dynamically when the route is matched
- **self-delete is blocked on the API.** `UserPolicy::delete` returns `false` when actor === target, so `DELETE /api/v1/users/{self}` returns 403 even for users holding `users.delete`. The only supported self-removal flow is the password-confirmed Fortify path in the Profile UI.

## API Authentication

Passport powers the API side:

- personal access tokens
- `POST /api/v1/auth/register` and `POST /api/v1/auth/login` are public and throttled
- `POST /api/v1/auth/two-factor-challenge` is public and throttled
- `POST /api/v1/auth/logout` and `GET /api/v1/auth/me` require `auth:api`

### API Auth Flow

- `register` returns `201` with `{ user, token }` only when email verification is disabled
- when email verification is enabled, `register` returns `201` with `{ user, requires_verification: true }` and no token
- `login` can return `{ user, token }`, `{ requires_verification: true }`, or `{ requires_two_factor: true, challenge }`
- `two-factor-challenge` completes the API 2FA flow with either `code` or `recovery_code` and returns `{ user, token }` on success
- clients should branch on `requires_verification` and `requires_two_factor` instead of assuming every successful auth response contains a token

## API Clients & Tokens

The admin panel provides a UI for managing Passport OAuth2 clients and Personal Access Tokens (PATs):

- `/admin/api-clients` — list, create, update, and delete OAuth2 clients
- `/admin/api-tokens` — manage Personal Access Tokens

Client secrets and PAT values are shown exactly once in a dismissal-blocked modal at creation time and are never stored in plaintext. See [API Clients & Tokens](./api-clients.md) for the full reference.

## Notes

- use Fortify for the browser-facing auth experience
- use Passport for external or token-based API consumers
- keep web and API auth concerns separate even when they share the same user model
