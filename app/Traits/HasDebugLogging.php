<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasDebugLogging
{
    public static function log(string $message, array $data = []): void
    {
        $loggerName = preg_replace('/.*\\\\/', '', static::class);

        if (empty($data)) {
            Log::debug("[$loggerName] $message");
        } else {
            $formattedMessage = "[$loggerName] $message\n" . json_encode($data, JSON_PRETTY_PRINT);
            Log::debug($formattedMessage);
        }
    }
}
