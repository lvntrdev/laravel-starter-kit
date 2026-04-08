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

    public static function badRequest(string $message = 'Bad request.'): self
    {
        return new self(400, $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'You are not authorized for this action.'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Record not found.'): self
    {
        return new self(404, $message);
    }

    public static function methodNotAllowed(string $message = 'HTTP method not allowed.'): self
    {
        return new self(405, $message);
    }

    public static function conflict(string $message = 'Record already exists.'): self
    {
        return new self(409, $message);
    }

    public static function unprocessable(string $message = 'Unprocessable entity.'): self
    {
        return new self(422, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please wait.'): self
    {
        return new self(429, $message);
    }

    public static function serverError(string $message = 'A server error occurred.'): self
    {
        return new self(500, $message);
    }
}
