<?php

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

/*
|--------------------------------------------------------------------------
| Vendor Helper Functions
|--------------------------------------------------------------------------
|
| This file is NOT autoloaded via composer's `files` directive. It is
| `require_once`'d by `StarterKitServiceProvider::register()` AFTER the
| consumer-published copy at `app/Helpers/sk-helpers.php` (when present), so
| every declaration below is wrapped in a `function_exists` guard — the
| consumer's definitions always win.
|
*/

if (! function_exists('to_api')) {
    /**
     * Wrap data with ApiResponse and return.
     *
     * Automatically detects paginators (including simplePaginate / cursor) and
     * extracts pagination meta. Returns an ApiResponse on every branch except
     * 204, where a raw JsonResponse with an empty body is emitted — that keeps
     * controllers typed `: JsonResponse` working while allowing the fluent
     * chain on every other status.
     *
     * Usage:
     *   return to_api($user);
     *   return to_api($user, 'User retrieved.');
     *   return to_api(User::paginate(15));
     *   return to_api($user, 'Created.', 201);
     *   return to_api(null, 'Error', 400);  // success: false
     *   return to_api(status: 204);         // empty 204 body
     */
    function to_api(mixed $data = null, string $message = 'Operation successful.', int $status = 200): ApiResponse|JsonResponse
    {
        // Error responses (4xx, 5xx) — data is ignored on purpose.
        if ($status >= 400) {
            return ApiResponse::error($message, $status);
        }

        // 204 No Content — for delete/update operations with no response body.
        if ($status === 204) {
            return ApiResponse::noContent();
        }

        // Auto-detect paginators before the 201/202 branches so a paginator
        // dispatched with a non-200 status (e.g. 201 batch-create) still
        // receives pagination meta instead of being serialised as a raw object.
        if ($data instanceof LengthAwarePaginator || $data instanceof CursorPaginator || $data instanceof Paginator) {
            $response = ApiResponse::paginated($data, $message);
        } elseif ($data instanceof AnonymousResourceCollection && (
            $data->resource instanceof LengthAwarePaginator
            || $data->resource instanceof CursorPaginator
            || $data->resource instanceof Paginator
        )) {
            $response = ApiResponse::paginatedCollection($data, $message);
        } elseif ($status === 201) {
            return ApiResponse::created($data, $message);
        } else {
            // 200 OK (default) and any other 2xx we treat as success + status override.
            $response = ApiResponse::success($data, $message);
        }

        if ($status !== 200) {
            $response->status($status);
        }

        return $response;
    }
}

if (! function_exists('definition')) {
    /**
     * Get a single definition record by key and value.
     *
     * Usage:
     *   definition('transportPlanStatus', 'active')  → Definition object
     *   definition('transportPlanStatus', 'active')?->label  → 'Aktif'
     */
    function definition(string $key, mixed $value): ?object
    {
        $service = app(DefinitionService::class);
        $items = $service->get($key);

        foreach ($items as $item) {
            if ((string) ($item['value'] ?? '') === (string) $value) {
                return (object) $item;
            }
        }

        return null;
    }
}

if (! function_exists('definitionLabel')) {
    /**
     * Get the label for a definition key+value pair.
     *
     * Usage:
     *   definitionLabel('identityType', 1)  → 'Türkiye'
     */
    function definitionLabel(string $key, mixed $value): ?string
    {
        return definition($key, $value)?->label;
    }
}

if (! function_exists('sk_locale_keys')) {
    /**
     * @return list<string> Aktif locale kodları (örn. ['tr', 'en'])
     */
    function sk_locale_keys(): array
    {
        return array_keys(config('app.languages', []));
    }
}

if (! function_exists('sk_default_locale')) {
    /**
     * Aktif diller arasından default (ilk) locale kodu.
     */
    function sk_default_locale(): string
    {
        $locales = sk_locale_keys();

        // Honour the admin-chosen default language when it is still active;
        // SettingsServiceProvider pushes it into `app.default_locale` at boot.
        $configured = config('app.default_locale');
        if ($configured && in_array($configured, $locales, true)) {
            return $configured;
        }

        return $locales[0] ?? config('app.fallback_locale', 'en');
    }
}

if (! function_exists('format_money')) {
    /**
     * Format a numeric amount with the application's configured currency.
     *
     * The active currency is pushed into `app.currency` by
     * SettingsServiceProvider from the General settings.
     *
     * Usage:
     *   format_money(1234.5)          → '₺1.234,50'  (when currency = TRY)
     *   format_money(99, 'USD')       → '$99,00'
     */
    function format_money(int|float|string|null $amount, ?string $currency = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $currency ??= config('app.currency', 'TRY');

        $symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
        $symbol = $symbols[$currency] ?? ($currency.' ');

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}

if (! function_exists('resolve_display_timezone')) {
    /**
     * Resolve the display timezone for a user or the current request.
     *
     * Usage:
     *   resolve_display_timezone($user)  → 'Europe/Istanbul'
     *   resolve_display_timezone()       → current user, site, app, or 'UTC'
     */
    function resolve_display_timezone(?object $user = null): string
    {
        $user ??= auth()->user();

        // The memo key is the user identity, never the object handle: a
        // spl_object_id is reused after the object is collected, so an id-less
        // user would inherit a dead peer's timezone within the same request.
        // No id → no memo; correctness outranks the saved lookup.
        $userId = $user?->id;
        $memoKey = $user !== null && $userId === null
            ? null
            : 'starter-kit.display-timezone.'.($user === null ? 'guest' : $user::class.':'.$userId);
        $request = $memoKey !== null && app()->bound('request') ? app('request') : null;

        if ($request instanceof Request && $request->attributes->has($memoKey)) {
            return $request->attributes->get($memoKey);
        }

        static $validIdentifiers;
        $validIdentifiers ??= array_fill_keys(DateTimeZone::listIdentifiers(), true);

        $candidates = [
            $user?->timezone,
            config('app.display_timezone'),
            config('app.timezone'),
            'UTC',
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '' || ! isset($validIdentifiers[$candidate])) {
                continue;
            }

            if ($request instanceof Request) {
                $request->attributes->set($memoKey, $candidate);
            }

            return $candidate;
        }

        return 'UTC';
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date/datetime to the resolved display timezone.
     *
     * Usage:
     *   format_date($model->created_at)          → '14-03-2026 08:36'
     *   format_date($model->created_at, 'date')  → '14-03-2026'
     *   format_date($model->created_at, 'time')  → '08:36'
     *   format_date($model->created_at, timezone: 'UTC')  → explicit override
     */
    function format_date(Carbon|string|null $value, string $type = 'datetime', ?string $timezone = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);

        // Explicit overrides win; otherwise the resolver checks the supplied
        // or authenticated user's valid IANA timezone, then the site setting,
        // app timezone, and finally UTC. Invalid stored values fall through,
        // so setTimezone() never receives a stale identifier from the chain.
        $carbon = $carbon->setTimezone($timezone ?? resolve_display_timezone());

        // Admin-configured date format (General settings) drives the date part;
        // defaults preserve the kit's historical 'd-m-Y' output.
        $dateFormat = config('app.date_format', 'd-m-Y');

        return match ($type) {
            'date' => $carbon->format($dateFormat),
            'time' => $carbon->format('H:i'),
            default => $carbon->format($dateFormat.' H:i'),
        };
    }
}

if (! function_exists('to_api_date')) {
    /**
     * Format a date/datetime as ISO-8601 in the resolved display timezone.
     *
     * The offset is carried because the client formats with Intl.DateTimeFormat
     * and must not re-derive the timezone from a separate setting.
     *
     * Usage:
     *   to_api_date($model->created_at)  → '2026-03-14T08:36:00+03:00'
     */
    function to_api_date(Carbon|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $carbon->setTimezone(resolve_display_timezone())->toIso8601String();
    }
}
