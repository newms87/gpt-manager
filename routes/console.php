<?php

use App\Console\Commands\ProcessDailyUsageBilling;
use App\Console\Commands\TaskTimeoutCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(TaskTimeoutCommand::class)->everyMinute();

// Process daily usage billing at 2 AM UTC
Schedule::command(ProcessDailyUsageBilling::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/billing.log'));
