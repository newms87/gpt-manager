<?php

namespace App\Http\Controllers\Ai;

use App\Models\Task\TaskProcess;
use App\Repositories\TaskProcessRepository;
use App\Resources\TaskDefinition\TaskProcessResource;
use Illuminate\Http\JsonResponse;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskProcessesController extends ActionController
{
    public static ?string $repo = TaskProcessRepository::class;

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

    /**
     * Get the restart history for a task process.
     * Returns historical (soft-deleted) processes that were replaced by this active process.
     */
    public function history(TaskProcess $taskProcess): JsonResponse
    {
        $historicalProcesses = $taskProcess->historicalProcesses()
            ->with(['agentThread', 'jobDispatches'])
            ->get();

        return response()->json([
            'data' => TaskProcessResource::collection($historicalProcesses),
        ]);
    }
}
