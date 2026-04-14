<?php

use App\Domain\Shared\Services\DefinitionService;
use App\Http\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

/*
|--------------------------------------------------------------------------
| User-Published Override Hook
|--------------------------------------------------------------------------
|
| When loaded from `vendor/lvntr/laravel-starter-kit/src/sk-helpers.php`,
| the dirname() walk lands at the consumer application's base path. If the
| user has published a customised copy via `php artisan sk:publish
| --tag=helpers`, route through it so their definitions win. The
| `require_once` is idempotent, so this stays safe in every load order.
|
| When this same file is loaded as the user's published copy, the realpath
| guard short-circuits the recursion and the function declarations below
| run normally.
|
*/

$skPublishedHelpers = dirname(__DIR__, 4).'/app/Helpers/sk-helpers.php';
if (is_file($skPublishedHelpers) && realpath($skPublishedHelpers) !== realpath(__FILE__)) {
    require_once $skPublishedHelpers;
    unset($skPublishedHelpers);

    return;
}
unset($skPublishedHelpers);

if (! function_exists('to_api')) {
    /**
     * Wrap data with ApiResponse and return.
     *
     * Automatically detects paginators and extracts pagination meta.
     *
     * Usage:
     *   return to_api($user);
     *   return to_api($user, 'User retrieved.');
     *   return to_api(User::paginate(15));
     *   return to_api($user, 'Created.', 201);
     *   return to_api(null, 'Error', 400);  // success: false
     */
    function to_api(mixed $data = null, string $message = 'Operation successful.', int $status = 200): ApiResponse|JsonResponse
    {
        // Error responses (4xx, 5xx)
        if ($status >= 400) {
            return ApiResponse::error($message, $status);
        }

        // 201 Created
        if ($status === 201) {
            return ApiResponse::created($data, $message);
        }

        // 202 Accepted — job queued, not yet completed
        if ($status === 202) {
            return ApiResponse::success($data, $message ?: 'Operation queued.')->status(202);
        }

        // 204 No Content — for delete/update operations with no response body
        if ($status === 204) {
            return ApiResponse::noContent();
        }

        // Auto-detect paginators
        if ($data instanceof LengthAwarePaginator || $data instanceof CursorPaginator) {
            return ApiResponse::paginated($data, $message);
        }

        // ResourceCollection wrapping a paginator (e.g. UserResource::collection($paginator))
        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $resolved = $data->resolve(request());

            return ApiResponse::success($resolved, $message)->meta([
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]);
        }

        // 200 OK (default)
        return ApiResponse::success($data, $message);
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

if (! function_exists('format_date')) {
    /**
     * Format a date/datetime to the application's display timezone.
     *
     * Usage:
     *   format_date($model->created_at)          → '14-03-2026 08:36'
     *   format_date($model->created_at, 'date')  → '14-03-2026'
     *   format_date($model->created_at, 'time')  → '08:36'
     */
    function format_date(Carbon|string|null $value, string $type = 'datetime'): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);
        $carbon = $carbon->setTimezone(config('app.display_timezone'));

        return match ($type) {
            'date' => $carbon->format('d-m-Y'),
            'time' => $carbon->format('H:i'),
            default => $carbon->format('d-m-Y H:i'),
        };
    }
}
