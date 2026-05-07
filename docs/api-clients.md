# API Clients & Tokens

The admin panel provides a UI for managing Passport OAuth2 clients and Personal Access Tokens. This covers the `authorization_code` and `client_credentials` grant types as well as PAT minting for API consumers.

## Admin Pages

- `/admin/api-clients` — list, create, update, and delete OAuth2 clients
- `/admin/api-tokens` — list and delete Personal Access Tokens; mint a new PAT for the authenticated user

## Client Types

| Type | Grant | Typical Use |
| --- | --- | --- |
| Web app | `authorization_code` | Third-party integrations acting on behalf of a user |
| Machine-to-machine | `client_credentials` | Backend services calling the API without a user context |

## Security Rules

- Only `confidential=true` clients can be created through the admin UI. Public clients are not supported.
- `authorization_code` clients require at least one redirect URI. HTTPS is enforced for all redirect URIs; `http://localhost` and `http://127.0.0.1` are allowed as exceptions per RFC 8252 §8.3.
- Redirect URIs are validated with the `HttpsOrLocalhostUrl` rule. Any URI that fails this check is rejected before the client is saved.

## One-Time Secret Display

Client secrets and PAT plaintext values are shown exactly once immediately after creation inside `OneTimeSecretModal`. The modal cannot be dismissed until the user explicitly acknowledges they have copied the value. The response carrying the secret sets `Cache-Control: no-store` to prevent browser caching.

After the modal is closed the value cannot be recovered. Rotate the client secret or issue a new PAT if the value is lost.

## Permissions

These permissions gate access to the API client and token admin pages:

| Permission | Controls |
| --- | --- |
| `api-clients.create` | Create a new OAuth2 client |
| `api-clients.read` | View the clients listing |
| `api-clients.update` | Edit an existing client |
| `api-clients.delete` | Delete a client |
| `api-tokens.create` | Mint a PAT for the authenticated user |
| `api-tokens.read` | View the tokens listing |
| `api-tokens.delete` | Revoke a token |

Add these permissions to `config/permission-resources.php` and run `php artisan sk:seed-permissions --fresh` after upgrading.

## PAT Minting

`POST /admin/api-tokens` mints a token **for the currently authenticated admin user only**. The `user_id` field is ignored even if supplied in the request body. To mint a PAT for another user, use the artisan command:

```bash
php artisan passport:client --personal
```

or issue the token programmatically inside a seeder or console command using `$user->createToken(...)`.

## Upgrade Note

API client and token management was added in **v13.5.3**. After upgrading, run the migration and re-seed permissions:

```bash
php artisan migrate
php artisan sk:seed-permissions --fresh
```

See the [CHANGELOG](./CHANGELOG.md) for the full list of changes introduced in v13.5.3.
