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

