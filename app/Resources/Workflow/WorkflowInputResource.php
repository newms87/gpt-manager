<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowInput;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

/**
 * @mixin WorkflowInput
 * @property WorkflowInput $resource
 */
class WorkflowInputResource extends ActionResource
{
    protected static string $type = 'WorkflowInput';

    public function data(): array
    {
        $thumbFile = $this->storedFiles()->first();

        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'description'             => $this->description,
            'workflow_runs_count'     => $this->workflow_runs_count,
            'thumb'                   => StoredFileResource::make($thumbFile),
            'has_active_workflow_run' => $this->activeWorkflowRuns()->exists(),
            'tags'                    => $this->objectTags()->pluck('name'),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
