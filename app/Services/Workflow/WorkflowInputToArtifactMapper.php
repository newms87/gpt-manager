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

    public function map(): Artifact|null
    {
        $jsonContent = null;

        if ($this->workflowInput->team_object_id) {
            $jsonContent = [
                'id'   => $this->workflowInput->team_object_id,
                'type' => $this->workflowInput->team_object_type,
            ];
        }

        // Parse content JSON to extract template_stored_file_id and additional_instructions
        $contentData = json_decode($this->workflowInput->content, true);
        
        if ($contentData) {
            // Add template_stored_file_id to json_content if present
            if (isset($contentData['template_stored_file_id'])) {
                $jsonContent = $jsonContent ?? [];
                $jsonContent['template_stored_file_id'] = $contentData['template_stored_file_id'];
            }
            
            // Add additional_instructions to json_content if present
            if (isset($contentData['additional_instructions'])) {
                $jsonContent = $jsonContent ?? [];
                $jsonContent['additional_instructions'] = $contentData['additional_instructions'];
            }
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
