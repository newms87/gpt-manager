<?php

namespace App\Services\UiDemand;

use App\Models\Demand\UiDemand;
use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use App\Services\Workflow\WorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;

class UiDemandWorkflowService
{
    public function extractData(UiDemand $uiDemand): WorkflowRun
    {
        if (!$uiDemand->canExtractData()) {
            if (!$uiDemand->inputFiles()->count()) {
                throw new ValidationError('No input files attached to the demand.');
            }
            throw new ValidationError('Cannot extract data for this demand. Make sure a workflow is not already running for this demand.');
        }


        $workflowDefinition = $this->getWorkflowDefinition('extract_data');
        $workflowInput      = $this->createWorkflowInputFromDemand($uiDemand, 'Extract Data');

        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);

        // Create WorkflowListener for callbacks
        WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA
        );

        // Subscribe UiDemand to the workflow's usage event
        $this->subscribeToWorkflowUsageEvent($uiDemand, $workflowRun);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);

        return $workflowRun;
    }

    public function writeMedicalSummary(UiDemand $uiDemand, ?string $instructionTemplateId = null, ?string $additionalInstructions = null): WorkflowRun
    {
        if (!$uiDemand->canWriteMedicalSummary()) {
            throw new ValidationError('Cannot write medical summary. Check if extract data is completed and team object exists.');
        }

        $workflowDefinition = $this->getWorkflowDefinition('write_medical_summary');
        $workflowInput      = $this->createWorkflowInputFromTeamObject($uiDemand, $uiDemand->teamObject, 'Write Medical Summary', null, $additionalInstructions);

        // Append instruction template content if provided
        if ($instructionTemplateId) {
            $instructionTemplate = WorkflowInput::find($instructionTemplateId);
            if ($instructionTemplate && $instructionTemplate->content) {
                // Append critical instruction template content to the main workflow input
                $workflowInput->content .= <<<TEXT


=== CRITICAL WRITING INSTRUCTIONS ===
The following instructions are EXTREMELY IMPORTANT and must be followed carefully when writing the medical summary. These instructions define the required style, tone, structure, and format. Following these instructions precisely is a CRITICAL part of this task and directly impacts the quality and effectiveness of the final medical summary.

{$instructionTemplate->content}

=== END CRITICAL INSTRUCTIONS ===

TEXT;
            }
        }

        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);

        // Create WorkflowListener for callbacks
        WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY
        );

        // Subscribe UiDemand to the workflow's usage event
        $this->subscribeToWorkflowUsageEvent($uiDemand, $workflowRun);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_MEDICAL_SUMMARY]);

        return $workflowRun;
    }

    public function writeDemandLetter(UiDemand $uiDemand, ?int $templateId = null, ?string $additionalInstructions = null): WorkflowRun
    {
        if (!$uiDemand->canWriteDemandLetter()) {
            throw new ValidationError('Cannot write demand letter. Check if write medical summary is completed and team object exists.');
        }

        $workflowDefinition = $this->getWorkflowDefinition('write_demand_letter');
        $workflowInput      = $this->createWorkflowInputFromTeamObject($uiDemand, $uiDemand->teamObject, 'Write Demand Letter', $templateId, $additionalInstructions);

        // Collect all input artifacts for the workflow:
        // 1. The workflow input (converted to artifact)
        // 2. Medical summary artifacts from previous workflow step
        $inputArtifacts = [$workflowInput->toArtifact()];

        // Add medical summary artifacts as additional input artifacts
        // These contain the generated medical summaries from the previous workflow step
        $medicalSummaryArtifacts = $uiDemand->medicalSummaries()->get();
        foreach($medicalSummaryArtifacts as $artifact) {
            $inputArtifacts[] = $artifact;
        }

        $workflowRun = WorkflowRunnerService::start($workflowDefinition, $inputArtifacts);

        // Create WorkflowListener for callbacks
        WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND_LETTER
        );

        // Subscribe UiDemand to the workflow's usage event
        $this->subscribeToWorkflowUsageEvent($uiDemand, $workflowRun);

        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND_LETTER]);

        return $workflowRun;
    }

    public function handleUiDemandWorkflowComplete(WorkflowRun $workflowRun): void
    {
        $uiDemand = UiDemand::whereHas('workflowRuns', function ($query) use ($workflowRun) {
            $query->where('workflow_runs.id', $workflowRun->id);
        })->first();

        if (!$uiDemand) {
            return;
        }

        if ($workflowRun->isCompleted()) {
            $this->handleWorkflowSuccess($uiDemand, $workflowRun);
        } else {
            $this->handleWorkflowFailure($uiDemand, $workflowRun);
        }
    }

    protected function handleWorkflowSuccess(UiDemand $uiDemand, WorkflowRun $workflowRun): void
    {
        $workflowName    = $workflowRun->workflowDefinition->name;
        $outputArtifacts = $workflowRun->collectFinalOutputArtifacts();

        if ($workflowName === config('ui-demands.workflows.extract_data')) {
            $metadata = [
                'extract_data_completed_at' => now()->toIso8601String(),
                'workflow_run_id'           => $workflowRun->id,
            ];

            $uiDemand->update([
                'status'   => UiDemand::STATUS_DRAFT,
                'metadata' => array_merge($uiDemand->metadata ?? [], $metadata),
            ]);

        } elseif ($workflowName === config('ui-demands.workflows.write_medical_summary')) {
            $metadata = [
                'write_medical_summary_completed_at' => now()->toIso8601String(),
                'workflow_run_id'                    => $workflowRun->id,
            ];

            // Attach medical summary artifacts to UiDemand with medical_summary category
            $this->attachArtifactsToUiDemand($uiDemand, $outputArtifacts, 'medical_summary');

            $uiDemand->update([
                'status'   => UiDemand::STATUS_DRAFT, // Stay as Draft until manually published
                'metadata' => array_merge($uiDemand->metadata ?? [], $metadata),
            ]);

        } elseif ($workflowName === config('ui-demands.workflows.write_demand_letter')) {
            $metadata = [
                'write_demand_letter_completed_at' => now()->toIso8601String(),
                'workflow_run_id'                  => $workflowRun->id,
            ];

            // Attach output files from workflow artifacts
            $this->attachOutputFilesFromWorkflow($uiDemand, $outputArtifacts);

            $uiDemand->update([
                'status'   => UiDemand::STATUS_DRAFT, // Stay as Draft until manually published
                'metadata' => array_merge($uiDemand->metadata ?? [], $metadata),
            ]);
        }
    }

    protected function handleWorkflowFailure(UiDemand $uiDemand, WorkflowRun $workflowRun): void
    {
        $metadata = array_merge($uiDemand->metadata ?? [], [
            'failed_at'       => now()->toIso8601String(),
            'error'           => $workflowRun->status,
            'workflow_run_id' => $workflowRun->id,
        ]);

        $uiDemand->update([
            'status'   => UiDemand::STATUS_FAILED,
            'metadata' => $metadata,
        ]);
    }

    public function getSchemaDefinitionForDemand(): SchemaDefinition
    {
        $name = config('ui-demands.workflows.schema_definition');

        $schemaDefinition = SchemaDefinition::where('team_id', team()->id)
            ->where('name', $name)
            ->first();

        if (!$schemaDefinition) {
            throw new ValidationError("UI Demand failed to resolve Schema definition '{$name}': Not found");
        }

        return $schemaDefinition;
    }

    protected function getWorkflowDefinition(string $workflowType): WorkflowDefinition
    {
        $workflowName = config("ui-demands.workflows.{$workflowType}");

        if (!$workflowName) {
            throw new ValidationError("Workflow configuration not found for type: {$workflowType}");
        }

        $workflowDefinition = WorkflowDefinition::where('team_id', team()->id)
            ->where('name', $workflowName)
            ->first();

        if (!$workflowDefinition) {
            throw new ValidationError("Workflow '{$workflowName}' not found");
        }

        return $workflowDefinition;
    }

    protected function createWorkflowInputFromDemand(UiDemand $uiDemand, string $workflowType): WorkflowInput
    {
        $workflowInputRepo = app(WorkflowInputRepository::class);

        $workflowInput = $workflowInputRepo->createWorkflowInput([
            'name'             => "$workflowType: $uiDemand->title",
            'description'      => $uiDemand->description,
            'team_object_id'   => $uiDemand->team_object_id,
            'team_object_type' => 'Demand',
            'content'          => json_encode([
                'demand_id'   => $uiDemand->id,
                'title'       => $uiDemand->title,
                'description' => $uiDemand->description,
            ]),
        ]);

        $fileIds = $uiDemand->inputFiles()->pluck('stored_files.id')->toArray();
        $workflowInput->storedFiles()->sync($fileIds);

        return $workflowInput;
    }

    protected function createWorkflowInputFromTeamObject(UiDemand $uiDemand, TeamObject $teamObject, string $workflowType, ?int $templateId = null, ?string $additionalInstructions = null): WorkflowInput
    {
        $workflowInputRepo = app(WorkflowInputRepository::class);

        $contentData = [
            'demand_id'   => $uiDemand->id,
            'title'       => $uiDemand->title,
            'description' => $uiDemand->description,
        ];

        // Add template stored file ID if provided
        if ($templateId) {
            $template = \App\Models\Demand\DemandTemplate::find($templateId);
            if ($template && $template->stored_file_id) {
                $contentData['template_stored_file_id'] = $template->stored_file_id;
            }
        }

        // Add additional instructions if provided
        if ($additionalInstructions) {
            $contentData['additional_instructions'] = $additionalInstructions;
        }

        return $workflowInputRepo->createWorkflowInput([
            'name'             => "{$workflowType}: {$uiDemand->title}",
            'description'      => $uiDemand->description,
            'team_object_id'   => $teamObject->id,
            'team_object_type' => $teamObject->type,
            'content'          => json_encode($contentData),
        ]);
    }


    /**
     * Attach output files from workflow artifacts to UiDemand
     */
    protected function attachOutputFilesFromWorkflow(UiDemand $uiDemand, $outputArtifacts): void
    {
        foreach($outputArtifacts as $artifact) {
            // Get all StoredFiles attached to this artifact
            $artifactStoredFiles = $artifact->storedFiles;

            foreach($artifactStoredFiles as $storedFile) {
                // Reuse the StoredFile from artifact and attach to UiDemand as output
                $uiDemand->outputFiles()->syncWithoutDetaching([$storedFile->id => ['category' => 'output']]);
            }
        }
    }

    /**
     * Attach artifacts to UiDemand with specified category
     */
    protected function attachArtifactsToUiDemand(UiDemand $uiDemand, $artifacts, string $category): void
    {
        foreach($artifacts as $artifact) {
            // Attach artifact to UiDemand with specified category
            $uiDemand->artifacts()->syncWithoutDetaching([$artifact->id => ['category' => $category]]);
        }
    }

    /**
     * Subscribe UiDemand to the workflow's usage event for tracking
     */
    protected function subscribeToWorkflowUsageEvent(UiDemand $uiDemand, WorkflowRun $workflowRun): void
    {
        $usageEvent = $workflowRun->findWorkflowUsageEvent();
        if ($usageEvent) {
            $uiDemand->subscribeToUsageEvent($usageEvent);
        }
    }
}
