<?php

declare(strict_types=1);

namespace LeapOCR\Internal;

use GuzzleHttp\Exception\GuzzleException;
use LeapOCR\Exceptions\ApiException;
use LeapOCR\Exceptions\AuthenticationException;
use LeapOCR\Exceptions\InsufficientCreditsException;
use LeapOCR\Exceptions\LeapOCRException;
use LeapOCR\Exceptions\NetworkException;
use LeapOCR\Exceptions\RateLimitException;
use LeapOCR\Exceptions\ValidationException;
use LeapOCRGenerated\ApiException as GeneratedApiException;
use Throwable;

final class ErrorMapper
{
    public static function normalize(Throwable $throwable): LeapOCRException
    {
        if ($throwable instanceof LeapOCRException) {
            return $throwable;
        }

        if ($throwable instanceof GeneratedApiException) {
            $statusCode = $throwable->getCode() > 0 ? $throwable->getCode() : null;
            $details = self::decodeBody($throwable->getResponseBody());
            $message = self::extractMessage($details) ?? $throwable->getMessage();

            return match ($statusCode) {
                400, 422 => new ValidationException($message, $statusCode, $details, $throwable),
                401 => new AuthenticationException($message, $statusCode, $details, $throwable),
                402 => new InsufficientCreditsException($message, $statusCode, $details, $throwable),
                429 => new RateLimitException(
                    $message,
                    self::extractRetryAfter($throwable->getResponseHeaders()),
                    $statusCode,
                    $details,
                    $throwable,
                ),
                null, 0 => new NetworkException($message, null, $details, $throwable),
                default => new ApiException($message, $statusCode, $details, $throwable),
            };
        }

        if ($throwable instanceof GuzzleException) {
            return new NetworkException($throwable->getMessage(), null, null, $throwable);
        }

        return new ApiException($throwable->getMessage(), null, null, $throwable);
    }

    public static function isRetriable(LeapOCRException $exception): bool
    {
        if ($exception instanceof NetworkException || $exception instanceof RateLimitException) {
            return true;
        }

        if (!$exception instanceof ApiException) {
            return false;
        }

        $statusCode = $exception->statusCode ?? 0;
        return $statusCode === 408 || $statusCode >= 500;
    }

    /**
     * @param array<string, array<int, string>>|null $headers
     */
    private static function extractRetryAfter(?array $headers): ?int
    {
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $name => $values) {
            if (strtolower($name) !== 'retry-after' || $values === []) {
                continue;
            }

            $value = trim($values[0]);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private static function extractMessage(mixed $details): ?string
    {
        if (!is_array($details)) {
            return null;
        }

        $error = $details['error'] ?? null;
        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }

        return null;
    }

    private static function decodeBody(mixed $body): mixed
    {
        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}

