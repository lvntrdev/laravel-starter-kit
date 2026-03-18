<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Custom API Exception — can be thrown from controllers or services.
 *
 * Usage:
 *   throw ApiException::notFound('User not found.');
 *   throw ApiException::forbidden();
 *   throw ApiException::badRequest('Invalid parameter.');
 *   throw ApiException::conflict('This record already exists.');
 *   throw new ApiException(422, 'Custom message');
 */
class ApiException extends HttpException
{
    public function __construct(int $statusCode = 400, string $message = 'An error occurred.', ?\Throwable $previous = null)
    {
        parent::__construct($statusCode, $message, $previous);
    }

    public static function badRequest(string $message = 'Bad request.'): static
    {
        return new static(400, $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): static
    {
        return new static(401, $message);
    }

    public static function forbidden(string $message = 'You are not authorized for this action.'): static
    {
        return new static(403, $message);
    }

    public static function notFound(string $message = 'Record not found.'): static
    {
        return new static(404, $message);
    }

    public static function methodNotAllowed(string $message = 'HTTP method not allowed.'): static
    {
        return new static(405, $message);
    }

    public static function conflict(string $message = 'Record already exists.'): static
    {
        return new static(409, $message);
    }

    public static function unprocessable(string $message = 'Unprocessable entity.'): static
    {
        return new static(422, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please wait.'): static
    {
        return new static(429, $message);
    }

    public static function serverError(string $message = 'A server error occurred.'): static
    {
        return new static(500, $message);
    }
}
