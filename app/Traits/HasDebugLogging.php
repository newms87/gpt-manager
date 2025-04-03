<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasDebugLogging
{
    public static function log(string $message): void
    {
        $loggerName = preg_replace("/.*\\\\/", '', static::class);
        Log::debug("[$loggerName] $message");
    }
}
