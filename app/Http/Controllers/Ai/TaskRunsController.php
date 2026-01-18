<?php

namespace App\Http\Controllers\Ai;

use App\Models\Task\TaskRun;
use App\Repositories\TaskRunRepository;
use App\Resources\TaskDefinition\TaskRunResource;
use Illuminate\Http\JsonResponse;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Resources\Audit\ErrorLogEntryResource;

class TaskRunsController extends ActionController
{
    public static ?string $repo     = TaskRunRepository::class;

    public static ?string $resource = TaskRunResource::class;

    public function errors(TaskRun $taskRun)
    {
        return ErrorLogEntryResource::collection($taskRun->getErrorLogEntries());
    }

    /**
     * Get the restart history for a task run.
     * Returns historical (soft-deleted) runs that were replaced by this active run.
     */
    public function history(TaskRun $taskRun): JsonResponse
    {
        $historicalRuns = $taskRun->historicalRuns()
            ->with(['taskProcesses', 'inputArtifacts', 'outputArtifacts'])
            ->get();

        return response()->json([
            'data' => TaskRunResource::collection($historicalRuns),
        ]);
    }
}
