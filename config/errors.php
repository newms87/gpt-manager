<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Newms87\Danx\Exceptions\ApiRequestException;

/**
 * Check if a 400-level API error should be retried
 */
if (!function_exists('isRetryable400ApiError')) {
    function isRetryable400ApiError(ApiRequestException $exception): bool
    {
        $statusCode = $exception->getStatusCode();

        // Only 400-499 errors
        if ($statusCode < 400 || $statusCode >= 500) {
            return false;
        }

        // Extract error code from response JSON
        try {
            $responseBody = $exception->getContents();
            if ($responseBody) {
                $responseJson = json_decode($responseBody, true);
                $errorCode    = $responseJson['error']['code']  ?? null;
                $errorParam   = $responseJson['error']['param'] ?? null;

                // Retryable 400-level error codes
                $retryableErrorCodes = [
                    'invalid_image_url',
                    'rate_limit_exceeded',
                    'insufficient_quota',
                    'model_overloaded',
                ];

                // Check if error code is in the retryable list
                if (in_array($errorCode, $retryableErrorCodes)) {
                    return true;
                }

                // Special case: invalid_value with param=url (transient URL download errors)
                if ($errorCode === 'invalid_value' && $errorParam === 'url') {
                    return true;
                }
            }
        } catch (Throwable $e) {
            // Ignore parsing errors
        }

        return false;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Retryable Exceptions
    |--------------------------------------------------------------------------
    |
    | Define which exception classes are retryable. These errors won't count
    | toward the user-visible error count as they represent transient failures.
    |
    | Format:
    | - ExceptionClass::class => true  (always retryable)
    | - ExceptionClass::class => [callback1, callback2, ...]  (retryable if ANY callback returns true)
    |
    | Callbacks receive the Throwable and should return bool.
    |
    */
    'retryable' => [
        // Always retryable - connection errors
        ConnectionException::class => true,
        ConnectException::class    => true,

        // Conditionally retryable - Guzzle HTTP request exceptions
        RequestException::class => [
            fn(RequestException $e) => str_contains($e->getMessage(), 'timeout'),
            fn(RequestException $e) => str_contains($e->getMessage(), 'rate limit'),
            fn(RequestException $e) => in_array($e->getCode(), [429, 503, 504]),
        ],

        // Conditionally retryable - API request exceptions
        ApiRequestException::class => [
            // Always retry 5xx errors
            fn(ApiRequestException $e) => $e->getStatusCode() >= 500,
            // Retry 422 only if message suggests it's transient (placeholder until specific cases identified)
            fn(ApiRequestException $e) => $e->getStatusCode() === 422 && stripos($e->getMessage(), 'retry') !== false,
            // Retry specific 400-level errors using helper function
            'isRetryable400ApiError',
        ],

        // Conditionally retryable - generic exceptions with specific codes
        Exception::class => [
            fn(Exception $e) => $e->getCode() === 580, // empty_response
            fn(Exception $e) => $e->getCode() === 581, // invalid_response
        ],
    ],
];
