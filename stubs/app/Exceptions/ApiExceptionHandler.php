<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Centralized exception handler for API requests.
 *
 * Returns all API errors in a consistent JSON format:
 * {
 *     "success": false,
 *     "status": 404,
 *     "message": "Record not found.",
 *     "data": null,
 *     "errors": { ... },       // only on validation errors
 *     "trace_id": "uuid",      // request correlation ID
 *     "debug": { ... }         // only when APP_DEBUG=true
 * }
 */
class ApiExceptionHandler
{
    /**
     * Register the exception handler in bootstrap/app.php.
     */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            // Only handle API requests or requests expecting JSON
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return self::handle($e, $request);
        });
    }

    /**
     * Convert an exception to a JsonResponse.
     */
    private static function handle(Throwable $e, Request $request): JsonResponse
    {
        // 1. Trace ID — always server-generated to prevent log / header injection.
        //    Any client-supplied X-Request-ID is accepted as correlation metadata
        //    only after being sanitised and length-capped.
        $traceId = (string) Str::uuid();
        $clientRequestId = self::sanitizeClientRequestId($request->header('X-Request-ID'));

        // 2. Status + Message mapping
        [$status, $message] = self::resolve($e);

        // 3. Logging — 500+ non-validation errors
        if ($status >= 500 && ! ($e instanceof ValidationException)) {
            Log::error("[API {$status}] {$message}", [
                'trace_id' => $traceId,
                'client_request_id' => $clientRequestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);
        }

        // 4. Build the response
        $response = ApiResponse::error($message, $status)
            ->traceId($traceId);

        // Validation errors
        if ($e instanceof ValidationException) {
            $response->errors($e->errors());
        }

        // Debug info — only in development environment
        if (config('app.debug', false) && $status >= 400) {
            $response->debug([
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->map(fn ($frame) => [
                    'file' => ($frame['file'] ?? '?').':'.($frame['line'] ?? '?'),
                    'call' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()',
                ])->all(),
            ]);
        }

        return $response
            ->header('X-Request-ID', $traceId)
            ->toResponse(request());
    }

    /**
     * Resolve [status, message] pair from the exception type.
     *
     * @return array{int, string}
     */
    private static function resolve(Throwable $e): array
    {
        return match (true) {
            // Our custom API exception — carries its own message and status code
            $e instanceof ApiException => [
                $e->getStatusCode(),
                $e->getMessage(),
            ],

            // Model not found (findOrFail, firstOrFail)
            $e instanceof ModelNotFoundException => [
                404,
                self::modelNotFoundMessage($e),
            ],

            // Route not found — check for nested exception
            $e instanceof NotFoundHttpException => [
                404,
                $e->getPrevious() instanceof ModelNotFoundException
                    ? self::modelNotFoundMessage($e->getPrevious())
                    : 'Endpoint not found.',
            ],

            // Validation error
            $e instanceof ValidationException => [
                422,
                'Validation error.',
            ],

            // HTTP method not allowed
            $e instanceof MethodNotAllowedHttpException => [
                405,
                'This HTTP method is not allowed for this endpoint.',
            ],

            // Authentication
            $e instanceof AuthenticationException => [
                401,
                'Authentication required.',
            ],

            // Authorization
            $e instanceof AuthorizationException => [
                403,
                'You are not authorized for this action.',
            ],

            // Rate limiting
            $e instanceof ThrottleRequestsException => [
                429,
                'Too many requests. Please try again after '
                    .($e->getHeaders()['Retry-After'] ?? '?')
                    .' seconds.',
            ],

            // Other Symfony HttpExceptions (abort() calls)
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $e->getMessage() ?: self::defaultMessageForStatus($e->getStatusCode()),
            ],

            // Unexpected errors — never leak the raw exception message into
            // the API response; detailed info goes into the debug block when
            // APP_DEBUG is on.
            default => [
                500,
                'A server error occurred.',
            ],
        };
    }

    /**
     * Accept a client-provided X-Request-ID only if it matches a safe charset
     * (letters, digits, dash, underscore, dot) and is ≤ 128 chars long.
     * Anything else is discarded to avoid log / header injection.
     */
    private static function sanitizeClientRequestId(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $trimmed = substr($value, 0, 128);

        return preg_match('/^[A-Za-z0-9._-]+$/', $trimmed) === 1 ? $trimmed : null;
    }

    /**
     * Extract a human-readable model name from ModelNotFoundException.
     */
    private static function modelNotFoundMessage(ModelNotFoundException|Throwable $e): string
    {
        if (! ($e instanceof ModelNotFoundException)) {
            return 'Record not found.';
        }

        return 'The requested resource was not found.';
    }

    /**
     * Default message for a given HTTP status code.
     */
    private static function defaultMessageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'You are not authorized for this action.',
            404 => 'Not found.',
            405 => 'HTTP method not allowed.',
            408 => 'Request timeout.',
            409 => 'Conflict — record already exists.',
            422 => 'Unprocessable entity.',
            429 => 'Too many requests.',
            500 => 'A server error occurred.',
            502 => 'Bad gateway.',
            503 => 'Service unavailable.',
            default => 'An error occurred.',
        };
    }
}
