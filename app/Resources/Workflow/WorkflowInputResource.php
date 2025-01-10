<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowInput;
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
            'id'                      => $workflowInput->id,
            'name'                    => $workflowInput->name,
            'description'             => $workflowInput->description,
            'workflow_runs_count'     => $workflowInput->workflow_runs_count,
            'thumb'                   => StoredFileResource::getThumb($storedFiles->first()),
            'has_active_workflow_run' => $workflowInput->activeWorkflowRuns()->exists(),
            'tags'                    => $workflowInput->objectTags()->pluck('name'),
            'team_object_type'        => $workflowInput->team_object_type,
            'team_object_id'          => $workflowInput->team_object_id,
            'created_at'              => $workflowInput->created_at,
            'updated_at'              => $workflowInput->updated_at,

            // Optional fields
            'files'                   => fn($fields) => StoredFileResource::collection($storedFiles, $fields),
            'content'                 => fn() => $workflowInput->content,
        ];
    }
}
