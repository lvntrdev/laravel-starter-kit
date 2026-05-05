# Activity Logs

The activity log module provides audit visibility for admin actions and model changes.

## Foundation

It is built on `spatie/laravel-activitylog`. The project wraps Spatie's `LogsActivity` concern inside a project-owned trait, `App\Traits\HasActivityLogging` (`app/Traits/HasActivityLogging.php`). Add this trait to any model that should be audited — do not use Spatie's `LogsActivity` directly.

The custom trait selects its logging strategy automatically: it calls `logFillable()` for models that define `$fillable`, or `logUnguarded()` otherwise. It always skips empty change sets.

## Routes

The activity log module registers three named routes:

- `activity-logs.index` — `GET /activity-logs`
- `activity-logs.dtApi` — `GET /activity-logs/dt`
- `activity-logs.show` — `GET /activity-logs/{activity}`

## What It Usually Tracks

- created records
- updated records
- deleted records
- actor information
- changed fields when available

User create, update, and delete actions also dispatch dedicated domain events now. That means admin-side user management changes reliably feed the audit log flow again, including role changes and delete events, while no-op updates are skipped.

## UI Layer

The admin panel includes:

- datatable-based list view
- detail dialog or detail screen
- filters for event type and related entity data

## Why It Matters

This module is useful for support, auditing, debugging, and tracing operational mistakes in admin-heavy projects.
