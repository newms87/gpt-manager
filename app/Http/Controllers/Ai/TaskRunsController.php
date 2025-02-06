<?php

namespace App\Http\Controllers\Ai;

use App\Models\Task\TaskRun;
use App\Repositories\TaskProcessRepository;
use App\Repositories\TaskRunRepository;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskRunsController extends ActionController
{
    public static string  $repo     = TaskRunRepository::class;
    public static ?string $resource = TaskRunResource::class;

    /**
     * @param TaskRun $model
     * @return mixed
     */
    public function details($model): mixed
    {
        // The details are called regularly when a user views a page with a workflow run visible.
        // So we can check for timeouts here to be sure we're up-to-date instead of running a cron job.
        if ($model) {
            foreach($model->taskProcesses as $taskProcess) {
                app(TaskProcessRepository::class)->checkForTimeout($taskProcess);
            }
        }

        return parent::details($model);
    }
}
