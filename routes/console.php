<?php

use App\Console\Commands\TaskTimeoutCommand;
use App\Console\Commands\WorkflowTimeoutCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(WorkflowTimeoutCommand::class)->everyMinute();
Schedule::command(TaskTimeoutCommand::class)->everyMinute();
