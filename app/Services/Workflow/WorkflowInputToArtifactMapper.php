<?php

namespace App\Services\Workflow;

use App\Models\Task\Artifact;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Support\Facades\Log;

class WorkflowInputToArtifactMapper
{
    protected WorkflowInput $workflowInput;

    public function setWorkflowInput(WorkflowInput $workflowInput): static
    {
        $this->workflowInput = $workflowInput;

        return $this;
    }

    public function map(): ?Artifact
    {
        $jsonContent = null;

        if ($this->workflowInput->team_object_id) {
            $jsonContent = [
                'id'   => $this->workflowInput->team_object_id,
                'type' => $this->workflowInput->team_object_type,
            ];
        }

        // Parse content JSON and merge all data into json_content
        $contentData = json_decode($this->workflowInput->content, true);

        if ($contentData) {
            $jsonContent = $jsonContent ?? [];
            // Merge all content data into json_content
            $jsonContent = array_merge($jsonContent, $contentData);
        }

        // Produce the artifact
        $artifact = Artifact::create([
            'name'         => $this->workflowInput->name,
            'model'        => '',
            'text_content' => $this->workflowInput->content,
            'json_content' => $jsonContent,
        ]);

        $artifact->storedFiles()->saveMany($this->workflowInput->storedFiles);

        Log::debug("Created $artifact");

        return $artifact;
    }
}
