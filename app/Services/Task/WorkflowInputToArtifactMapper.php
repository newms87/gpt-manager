<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\WorkflowInput;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\DateHelper;

class WorkflowInputToArtifactMapper
{
    protected WorkflowInput $workflowInput;

    public function setWorkflowInput(WorkflowInput $workflowInput): static
    {
        $this->workflowInput = $workflowInput;

        return $this;
    }

    public function map(): Artifact|null
    {
        $jsonContent = null;

        if ($this->workflowInput->team_object_id) {
            $jsonContent = [
                'id'   => $this->workflowInput->team_object_id,
                'type' => $this->workflowInput->team_object_type,
            ];
        }

        // Produce the artifact
        $artifact = Artifact::create([
            'name'         => $this->workflowInput->name . ' ' . DateHelper::formatDateTime(),
            'model'        => '',
            'text_content' => $this->workflowInput->content,
            'json_content' => $jsonContent,
        ]);

        $artifact->storedFiles()->saveMany($this->workflowInput->storedFiles);

        Log::debug("Created $artifact");

        return $artifact;
    }
}
