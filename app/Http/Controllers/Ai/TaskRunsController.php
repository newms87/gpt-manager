<?php

namespace App\Http\Controllers\Ai;

use App\Models\Task\TaskRun;
use App\Repositories\TaskRunRepository;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskRunsController extends ActionController
{
    public static string  $repo     = TaskRunRepository::class;
    public static ?string $resource = TaskRunResource::class;

    public function subscribeToProcesses(TaskRun $taskRun)
    {
        cache()->put('subscribe:task-run-processes:' . user()->id, $taskRun->id, 60);

        return ['success' => true];
    }
}
