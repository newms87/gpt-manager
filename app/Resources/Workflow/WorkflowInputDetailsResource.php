<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowInput;
use Newms87\Danx\Resources\StoredFileWithTranscodesResource;

/**
 * @mixin WorkflowInput
 * @property WorkflowInput $resource
 */
class WorkflowInputDetailsResource extends WorkflowInputResource
{
    public function data(): array
    {
        $runs = $this->workflowRuns()->orderByDesc('id')->get();

        return [
                'files'        => StoredFileWithTranscodesResource::collection($this->storedFiles),
                'content'      => $this->content,
                'workflowRuns' => $runs ? WorkflowInputWorkflowRunDetailsResource::collection($runs) : [],
            ] + parent::data();
    }
}
