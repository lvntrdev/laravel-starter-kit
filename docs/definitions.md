# Definitions

Definitions are a shared lookup system for label/value pairs used across forms, filters, and tags.

## Storage and Management

Definitions are database-backed records. There is no admin CRUD UI for them — they are managed via seeders and migrations.

- Migration: `database/migrations/2026_03_12_001950_create_definitions_table.php`
- Seeder: `database/seeders/_02_DefinitionSeeder.php`

## Database Columns

The `definitions` table has the following columns:

| Column | Type | Notes |
|---|---|---|
| `key` | string | indexed; groups related definitions |
| `value` | string | the stored value |
| `label` | string | human-readable display label |
| `explanation` | text | nullable; additional description |
| `severity` | string | nullable; e.g. `info`, `warning`, `danger` |
| `icon` | string | nullable; icon identifier |
| `is_active` | boolean | defaults to `true` |
| `order` | integer | defaults to `0`; controls sort order |
| `visibility` | boolean | defaults to `true` |
| `lang` | string | defaults to `en`; supports i18n |

A unique constraint is enforced on `(key, value, lang)`.

## Access Points

- web service route: `/definitions`
- API route: `/api/v1/definitions`
- frontend composable: `useDefinition()`

## Frontend Benefits

Definitions make it easy to:

- populate select options
- render status tags consistently
- share the same meaning across pages and modules

## Common Methods

From `useDefinition()`:

- `load(keys)`
- `loadAll()`
- `list(key)`
- `options(key)`
- `find(key, value)`
- `clearCache()`

