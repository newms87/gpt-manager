<?php

namespace App\Services\Workflow;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;

class WorkflowListenerService
{
    /**
     * Run a workflow for a model that uses HasWorkflowListeners trait
     */
    public function runWorkflow(
        Model $listener,
        string $workflowType,
        string $configKey,
        string $envVarName,
        callable $createWorkflowInput,
        ?callable $preRunCallback = null,
        ?callable $postRunCallback = null
    ): WorkflowRun {
        // Get workflow definition
        $workflowDefinition = $this->getWorkflowDefinition($configKey, $envVarName);

        return DB::transaction(function () use (
            $listener,
            $workflowDefinition,
            $workflowType,
            $createWorkflowInput,
            $preRunCallback,
            $postRunCallback
        ) {
            // Execute pre-run callback if provided
            if ($preRunCallback) {
                $preRunCallback($listener);
            }

            $workflowInput = $createWorkflowInput();
            
            $artifacts = [$workflowInput->toArtifact()];
            $workflowRun = WorkflowRunnerService::start($workflowDefinition, $artifacts);
            
            // Create workflow listener
            $workflowListener = WorkflowListener::createForListener(
                $listener,
                $workflowRun,
                $workflowType,
                ['workflow_input_id' => $workflowInput->id]
            );
            
            $workflowListener->markAsRunning();

            // Execute post-run callback if provided
            if ($postRunCallback) {
                $postRunCallback($listener, $workflowRun, $workflowListener);
            }

            return $workflowRun;
        });
    }

    /**
     * Create a workflow input from a model
     */
    public function createWorkflowInput(
        Model $model,
        string $name,
        array $additionalData = []
    ): WorkflowInput {
        $workflowInputRepo = app(WorkflowInputRepository::class);
        
        $data = array_merge([
            'name' => $name,
            'content' => json_encode(array_merge([
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ], $additionalData)),
        ], $additionalData);

        return $workflowInputRepo->createWorkflowInput($data);
    }

    /**
     * Get workflow definition by config key
     */
    protected function getWorkflowDefinition(string $configKey, string $envVarName): WorkflowDefinition
    {
        $workflowDefinitionName = config($configKey, '');
        
        if (empty($workflowDefinitionName)) {
            throw new ValidationError("Workflow not configured. Please set $envVarName environment variable.");
        }

        $workflowDefinition = WorkflowDefinition::where('team_id', team()->id)
            ->where('name', $workflowDefinitionName)
            ->first();
        
        if (!$workflowDefinition) {
            throw new ValidationError("Workflow '$workflowDefinitionName' not found");
        }

        return $workflowDefinition;
    }

    /**
     * Handle workflow completion for any listener
     */
    public function onWorkflowComplete(
        WorkflowRun $workflowRun,
        callable $successCallback,
        callable $failureCallback
    ): void {
        $workflowListener = WorkflowListener::findForWorkflowRun($workflowRun);

        if (!$workflowListener) {
            return;
        }

        $listener = $workflowListener->listener;
        if (!$listener) {
            return;
        }

        if ($workflowRun->isCompleted()) {
            $workflowListener->markAsCompleted();
            $successCallback($listener, $workflowRun, $workflowListener);
        } else {
            $workflowListener->markAsFailed();
            $failureCallback($listener, $workflowRun, $workflowListener);
        }
    }
}