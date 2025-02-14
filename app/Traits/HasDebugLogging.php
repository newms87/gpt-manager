<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasDebugLogging
{
    public static function log(string $message): void
    {
        Log::debug(preg_replace("/.*\\\\/", '', static::class) . ": $message");
    }
}
