<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskProcessRepository;
use App\Resources\TaskDefinition\TaskProcessResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskProcessesController extends ActionController
{
    public static ?string $repo     = TaskProcessRepository::class;

    public static ?string $resource = TaskProcessResource::class;

    public function details($model): mixed
    {
        // The details are called regularly when a user views a page with a workflow run visible.
        // So we can check for timeouts here to be sure we're up-to-date instead of running a cron job.
        if ($model) {
            app(TaskProcessRepository::class)->checkForTimeout($model);
        }

        return parent::details($model);
    }
}
