<?php

namespace App\Services\Task;

use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowInput;
use App\WorkflowTools\WorkflowInputWorkflowTool;
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
        // Produce the artifact
        $artifact = Artifact::create([
            'name'         => $this->workflowInput->name . ' ' . DateHelper::formatDateTime(),
            'model'        => '',
            'text_content' => $this->workflowInput->content,
            'json_content' => WorkflowInputWorkflowTool::getTeamObjects($this->workflowInput),
        ]);

        $artifact->storedFiles()->saveMany($this->workflowInput->storedFiles);

        Log::debug("Created $artifact");

        return $artifact;
    }
}
