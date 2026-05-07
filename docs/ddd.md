# DDD

This document explains the domain-first structure used by the starter kit. It is a DDD-style organization centered around domains instead of controllers.

## Goal

The main goal is to keep business rules out of large controllers and move them into predictable domain folders.

## Domain Structure

Typical structure after installation:

```text
app/Domain/
├── ActivityLog/
├── ApiRoute/
├── Auth/
├── FileManager/
├── Media/
├── Role/
├── Session/
├── Setting/
├── Shared/
└── User/
```

Inside a domain you will usually see these layers:

- `Actions` for write operations and use-case orchestration
- `DTOs` for carrying validated data
- `Queries` for list and datatable query logic
- `Events` for domain events
- `Listeners` for side effects such as logging
- `Repositories` or `Contracts` when the domain needs abstraction

## Request Flow

Typical flow:

1. Controller receives the request.
2. Form Request validates it.
3. DTO normalizes the payload.
4. Action performs the business operation.
5. Event is fired if side effects are needed.
6. Listener reacts without bloating the controller.
7. Response returns through `to_api()` or an Inertia redirect.

## Rules Of Thumb

- keep controllers thin
- keep validation in Form Requests
- keep complex writes in Actions
- keep reusable list logic in Queries
- keep side effects in Listeners
- keep shared cross-domain code under `app/Domain/Shared`

## Why It Helps

- better readability in large admin projects
- easier testing of business operations
- safer refactoring over time
- less duplication between web and API controllers

## Related Commands

The domain structure is supported by scaffolding commands, but the command reference lives in [artisan-commands.md](./artisan-commands.md). This file exists specifically to keep DDD guidance separate from command documentation.

### `make:sk-domain` v2 opt-in flags

Calling the command without any flags preserves the v13.5.x behavior (backward compatible).

**Individual flags:**

| Flag | What it generates |
| --- | --- |
| `--with-policy` | Policy class |
| `--with-factory` | Factory |
| `--with-seeder` | Seeder |
| `--with-test` | Feature test |
| `--with-relations` | Relation scaffold (use together with `--relations`) |

**Bulk syntax** — pass multiple opt-ins in one flag:

```bash
php artisan make:sk-domain Article --with=policy,factory,test
```

**Relations syntax:**

```bash
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment,morphTo:commentable"
```

Supported relation types: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`, `morphTo`, `morphMany`.

**Examples:**

```bash
# Domain only — v13.5.x behavior, backward compatible
php artisan make:sk-domain Article

# Policy and factory
php artisan make:sk-domain Article --with-policy --with-factory

# Bulk syntax
php artisan make:sk-domain Article --with=policy,factory,test

# With relations
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment"

# Full
php artisan make:sk-domain Article --with=policy,factory,seeder,test,relations --relations="belongsTo:User,morphTo:commentable"
```

