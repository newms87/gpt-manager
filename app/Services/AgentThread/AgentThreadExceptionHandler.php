<?php

namespace App\Services\AgentThread;

use App\Traits\HasDebugLogging;
use GuzzleHttp\Exception\ConnectException;
use Newms87\Danx\Exceptions\ApiRequestException;
use Newms87\Danx\Services\Error\RetryableErrorChecker;
use Throwable;

class AgentThreadExceptionHandler
{
    use HasDebugLogging;

    // Track retries by exception type
    protected array $exceptionRetries = [];

    /**
     * Reset retry counters for a new thread run
     */
    public function reset(): void
    {
        $this->exceptionRetries = [];
    }

    /**
     * Handle exceptions with type-specific retry logic and exponential backoff
     * Returns true if the operation should be retried, false otherwise
     */
    public function shouldRetry(Throwable $exception): bool
    {
        // Check if exception is retryable using centralized config
        if (!RetryableErrorChecker::isRetryable($exception)) {
            return false;
        }

        $exceptionType = $this->getExceptionType($exception);

        // Initialize retry counter for this exception type if not set
        if (!isset($this->exceptionRetries[$exceptionType])) {
            $this->exceptionRetries[$exceptionType] = 0;
        }

        // Check if we've exceeded max retries for this exception type
        if ($this->exceptionRetries[$exceptionType] >= 3) {
            static::logDebug("Max retries (3) reached for exception type: $exceptionType");

            return false;
        }

        // Increment retry counter
        $this->exceptionRetries[$exceptionType]++;
        $attemptNumber = $this->exceptionRetries[$exceptionType];

        // Calculate exponential backoff delay
        $baseDelay        = $this->getBaseDelayForExceptionType($exceptionType);
        $exponentialDelay = $baseDelay * pow(2, $attemptNumber - 1) + random_int(1, 3);

        static::logDebug("Retrying exception type '$exceptionType' (attempt $attemptNumber/3) after {$exponentialDelay}s delay: {$exception->getMessage()}");

        sleep($exponentialDelay);

        return true;
    }

    /**
     * Get exception type for retry tracking
     */
    protected function getExceptionType(Throwable $exception): string
    {
        if ($exception instanceof ConnectException) {
            if (str_contains($exception->getMessage(), 'timed out')) {
                return 'connection_timeout';
            }

            return 'connection_error';
        }

        if ($exception instanceof ApiRequestException) {
            $statusCode = $exception->getStatusCode();

            if ($statusCode >= 500) {
                return 'server_error_' . $statusCode;
            }

            if ($statusCode >= 400 && $statusCode < 500) {
                // Check for specific error codes in response
                $errorCode = $this->extractErrorCodeFromApiException($exception);
                if ($errorCode) {
                    return 'client_error_' . $errorCode;
                }

                return 'client_error_' . $statusCode;
            }
        }

        $code = $exception->getCode();

        return match ($code) {
            580     => 'empty_response',
            581     => 'invalid_response',
            default => 'unknown_error',
        };
    }

    /**
     * Extract error code from API exception response
     */
    protected function extractErrorCodeFromApiException(ApiRequestException $exception): ?string
    {
        try {
            $responseBody = $exception->getContents();
            if ($responseBody) {
                $responseJson = json_decode($responseBody, true);

                return $responseJson['error']['code'] ?? null;
            }
        } catch (Throwable $e) {
            // Ignore parsing errors
        }

        return null;
    }

    /**
     * Get base delay in seconds for different exception types
     */
    protected function getBaseDelayForExceptionType(string $exceptionType): int
    {
        return match (true) {
            str_starts_with($exceptionType, 'connection_')   => 5,
            str_starts_with($exceptionType, 'server_error_') => 3,
            str_starts_with($exceptionType, 'client_error_') => 2,
            default                                          => 1
        };
    }

    /**
     * Get current retry counts for debugging
     */
    public function getRetryCounters(): array
    {
        return $this->exceptionRetries;
    }
}
