<?php

namespace App\Resources\TaskWorkflow;

use App\Models\Task\WorkflowInput;
use App\Resources\TeamObject\TeamObjectResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class WorkflowInputResource extends ActionResource
{
    public static function data(WorkflowInput $workflowInput, array $fields = []): array
    {
        // Conditionally eager load transcodes w/ the stored files if the transcodes are included
        $withTranscodes = $fields['files']['transcodes'] ?? false;
        $storedFiles    = $workflowInput->storedFiles()->with($withTranscodes ? 'transcodes' : [])->get();

        return [
            'id'               => $workflowInput->id,
            'name'             => $workflowInput->name,
            'description'      => $workflowInput->description,
            'thumb'            => StoredFileResource::getThumb($storedFiles->first()),
            'tags'             => $workflowInput->objectTags()->pluck('name'),
            'team_object_type' => $workflowInput->team_object_type,
            'team_object_id'   => $workflowInput->team_object_id,
            'created_at'       => $workflowInput->created_at,
            'updated_at'       => $workflowInput->updated_at,

            // Optional fields
            'files'            => fn($fields) => StoredFileResource::collection($storedFiles, $fields),
            'content'          => fn() => $workflowInput->content,
            'teamObject'       => fn($fields) => TeamObjectResource::make($workflowInput->teamObject, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'files'      => ['thumb' => true, 'transcodes' => true],
            'content'    => true,
            'teamObject' => true,
        ]);
    }
}
