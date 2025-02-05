<?php

namespace App\Services\Task;

use App\Models\Task\TaskInput;
use App\Models\Workflow\Artifact;
use App\WorkflowTools\WorkflowInputWorkflowTool;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\DateHelper;

class TaskInputToArtifactMapper
{
    protected TaskInput $taskInput;

    public function setTaskInput(TaskInput $taskInput): static
    {
        $this->taskInput = $taskInput;

        return $this;
    }

    public function map(): Artifact|null
    {
        // Produce the artifact
        $workflowInput = $this->taskInput->workflowInput;;

        $artifact = Artifact::create([
            'name'         => $workflowInput->name . ' ' . DateHelper::formatDateTime(),
            'model'        => '',
            'text_content' => $workflowInput->content,
            'json_content' => WorkflowInputWorkflowTool::getTeamObjects($workflowInput),
        ]);

        $artifact->storedFiles()->saveMany($workflowInput->storedFiles);

        Log::debug("Created $artifact");

        return $artifact;
    }
}
