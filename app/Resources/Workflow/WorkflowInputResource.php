<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class WorkflowInputResource extends ActionResource
{
    /**
     * @param WorkflowInput $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        $thumbFile = $model->storedFiles()->first();

        return static::make($model, [
                'id'                      => $model->id,
                'name'                    => $model->name,
                'description'             => $model->description,
                'workflow_runs_count'     => $model->workflow_runs_count,
                'thumb'                   => StoredFileResource::data($thumbFile),
                'has_active_workflow_run' => $model->activeWorkflowRuns()->exists(),
                'tags'                    => $model->objectTags()->pluck('name'),
                'created_at'              => $model->created_at,
                'updated_at'              => $model->updated_at,
            ] + $attributes);
    }

    /**
     * @param WorkflowInput $model
     */
    public static function details(Model $model): array
    {
        $storedFiles  = $model->storedFiles()->with('transcodes');
        $workflowRuns = $model->workflowRuns()->orderByDesc('id')->get();

        return static::data($model, [
            'files'        => StoredFileResource::collection($storedFiles, fn(StoredFile $storedFile) => [
                'transcodes' => StoredFileResource::collection($storedFile->transcodes),
            ]),
            'content'      => $model->content,
            'workflowRuns' => WorkflowRunResource::collection($workflowRuns),
        ]);
    }
}
