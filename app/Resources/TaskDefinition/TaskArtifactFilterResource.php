<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskArtifactFilter;
use Newms87\Danx\Resources\ActionResource;

class TaskArtifactFilterResource extends ActionResource
{
    public static function data(TaskArtifactFilter $taskArtifactFilter): array
    {
        return [
            'id'                        => $taskArtifactFilter->id,
            'source_task_definition_id' => $taskArtifactFilter->source_task_definition_id,
            'target_task_definition_id' => $taskArtifactFilter->target_task_definition_id,
            'include_files'             => $taskArtifactFilter->include_files,
            'include_text'              => $taskArtifactFilter->include_text,
            'include_json'              => $taskArtifactFilter->include_json,
            'fragment_selector'         => $taskArtifactFilter->fragment_selector,
        ];
    }
}
