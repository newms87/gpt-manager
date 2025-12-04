<?php

namespace App\Services\UiDemand;

use App\Models\Demand\DemandTemplate;
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
    /**
     * Generic workflow execution method that replaces hardcoded workflow methods
     *
     * @param  UiDemand  $uiDemand  The demand to run the workflow on
     * @param  string  $workflowKey  The workflow key from config (e.g., 'extract_data', 'write_medical_summary')
     * @param  array  $params  Optional parameters: output_template_id, instruction_template_id, additional_instructions
     *
     * @throws ValidationError
     */
    public function runWorkflow(UiDemand $uiDemand, string $workflowKey, array $params = []): WorkflowRun
    {
        $configService = app(UiDemandWorkflowConfigService::class);

        // Get workflow configuration
        $workflowConfig = $configService->getWorkflow($workflowKey);
        if (!$workflowConfig) {
            throw new ValidationError("Workflow '{$workflowKey}' not found in configuration");
        }

        // Validate workflow can run (dependencies met, etc.)
        if (!$configService->canRunWorkflow($uiDemand, $workflowKey)) {
            throw new ValidationError("Cannot run workflow '{$workflowKey}'. Check dependencies and input requirements.");
        }

        // Get workflow definition from database
        $workflowDefinition = WorkflowDefinition::where('team_id', team()->id)
            ->where('name', $workflowConfig['name'])
            ->first();

        if (!$workflowDefinition) {
            throw new ValidationError("Workflow definition '{$workflowConfig['name']}' not found");
        }

        // Build workflow input based on config
        $inputArtifacts = $this->buildWorkflowInput($uiDemand, $workflowConfig, $params);

        // Start workflow
        $workflowRun = WorkflowRunnerService::start($workflowDefinition, $inputArtifacts);

        // Create WorkflowListener for callbacks
        WorkflowListener::createForListener(
            $uiDemand,
            $workflowRun,
            $workflowKey
        );

        // Subscribe UiDemand to the workflow's usage event
        $this->subscribeToWorkflowUsageEvent($uiDemand, $workflowRun);

        // Attach workflow run to demand with workflow key
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => $workflowKey]);

        return $workflowRun;
    }

    /**
     * Build workflow input artifacts based on config
     */
    protected function buildWorkflowInput(UiDemand $uiDemand, array $workflowConfig, array $params): array
    {
        $inputConfig = $workflowConfig['input'] ?? [];
        $source      = $inputConfig['source']   ?? 'demand';

        // Create main workflow input based on source
        if ($source === 'demand') {
            $workflowInput = $this->createWorkflowInputFromDemand($uiDemand, $workflowConfig['label']);
        } elseif ($source === 'artifacts') {
            // Get artifacts from a previous workflow run
            $fromWorkflow     = $inputConfig['from_workflow']     ?? null;
            $artifactCategory = $inputConfig['artifact_category'] ?? null;

            if (!$fromWorkflow || !$artifactCategory) {
                throw new ValidationError('Artifacts source requires from_workflow and artifact_category configuration');
            }

            // Find the completed workflow run for the specified workflow
            $completedWorkflowRun = $uiDemand->workflowRuns()
                ->wherePivot('workflow_type', $fromWorkflow)
                ->where('status', WorkflowRun::STATUS_COMPLETED)
                ->first();

            if (!$completedWorkflowRun) {
                throw new ValidationError("No completed workflow run found for '{$fromWorkflow}'");
            }

            // Get artifacts from the workflow run's output artifacts filtered by category
            // Or fall back to artifacts attached to the UiDemand with the specified category
            $artifacts = $completedWorkflowRun->artifacts()
                ->wherePivot('category', $artifactCategory)
                ->get();

            // If no artifacts found on workflow run, try getting from UiDemand directly
            if ($artifacts->isEmpty()) {
                $artifacts = $uiDemand->getArtifactsByCategory($artifactCategory);
            }

            if ($artifacts->isEmpty()) {
                throw new ValidationError("No artifacts found with category '{$artifactCategory}' from workflow '{$fromWorkflow}'");
            }

            // For now, return all artifacts as input (run_per_artifact handling will be added later)
            return $artifacts->all();
        } else {
            // source === 'team_object'
            if (!$uiDemand->teamObject) {
                throw new ValidationError('Team object not found for demand');
            }

            $templateId             = $params['output_template_id']        ?? null;
            $additionalInstructions = $params['additional_instructions']  ?? null;

            $workflowInput = $this->createWorkflowInputFromTeamObject(
                $uiDemand,
                $uiDemand->teamObject,
                $workflowConfig['label'],
                $templateId,
                $additionalInstructions
            );
        }

        // Handle instruction template injection if provided
        if (isset($params['instruction_template_id'])) {
            $instructionTemplate = WorkflowInput::find($params['instruction_template_id']);
            if ($instructionTemplate && $instructionTemplate->content) {
                $workflowInput->content .= <<<TEXT


=== CRITICAL WRITING INSTRUCTIONS ===
The following instructions are EXTREMELY IMPORTANT and must be followed carefully when writing the medical summary. These instructions define the required style, tone, structure, and format. Following these instructions precisely is a CRITICAL part of this task and directly impacts the quality and effectiveness of the final medical summary.

{$instructionTemplate->content}

=== END CRITICAL INSTRUCTIONS ===

TEXT;
            }
        }

        // Start with the main workflow input
        $inputArtifacts = [$workflowInput->toArtifact()];

        // Add artifacts from previous workflows if configured
        if (isset($inputConfig['include_artifacts_from'])) {
            foreach ($inputConfig['include_artifacts_from'] as $artifactSource) {
                $sourceWorkflow = $artifactSource['workflow'] ?? null;
                $sourceCategory = $artifactSource['category'] ?? null;

                if ($sourceWorkflow && $sourceCategory) {
                    // Get artifacts from UiDemand filtered by category
                    $artifacts = $uiDemand->artifacts()
                        ->wherePivot('category', $sourceCategory)
                        ->withPivot(['category'])
                        ->get();

                    // Store categories in artifact meta before passing to workflow
                    foreach ($artifacts as $artifact) {
                        $meta               = $artifact->meta ?? [];
                        $meta['__category'] = $artifact->pivot->category;
                        $artifact->meta     = $meta;
                        $artifact->save();
                        $inputArtifacts[] = $artifact;
                    }
                }
            }
        }

        return $inputArtifacts;
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
        $outputArtifacts = $workflowRun->collectFinalOutputArtifacts();

        // Get workflow key from pivot table
        $workflowKey = $uiDemand->workflowRuns()
            ->where('workflow_runs.id', $workflowRun->id)
            ->first()
            ?->pivot
            ?->workflow_type;

        if (!$workflowKey) {
            return;
        }

        // Get workflow configuration
        $configService  = app(UiDemandWorkflowConfigService::class);
        $workflowConfig = $configService->getWorkflow($workflowKey);

        if (!$workflowConfig) {
            return;
        }

        // Handle artifact attachment based on display config
        $displayConfig = $workflowConfig['display_artifacts'] ?? false;
        if ($displayConfig) {
            $category = $displayConfig['artifact_category'] ?? 'output';
            $this->attachArtifactsToUiDemand($uiDemand, $outputArtifacts, $category);
        }

        // Update metadata with completion timestamp
        $metadata = array_merge($uiDemand->metadata ?? [], [
            "{$workflowKey}_completed_at" => now()->toIso8601String(),
            'workflow_run_id'             => $workflowRun->id,
        ]);

        $uiDemand->update([
            'status'   => UiDemand::STATUS_DRAFT, // Stay as Draft until manually published
            'metadata' => $metadata,
        ]);
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
        $configService = app(UiDemandWorkflowConfigService::class);
        $name          = $configService->getSchemaDefinition();

        $schemaDefinition = SchemaDefinition::where('team_id', team()->id)
            ->where('name', $name)
            ->first();

        if (!$schemaDefinition) {
            throw new ValidationError("UI Demand failed to resolve Schema definition '{$name}': Not found");
        }

        return $schemaDefinition;
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
            $template = DemandTemplate::find($templateId);
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
     * Attach artifacts to UiDemand with specified category
     * Also attaches any StoredFiles from those artifacts to UiDemand's outputFiles
     */
    protected function attachArtifactsToUiDemand(UiDemand $uiDemand, $artifacts, string $category): void
    {
        foreach ($artifacts as $artifact) {
            // Attach artifact to UiDemand with specified category
            $uiDemand->artifacts()->syncWithoutDetaching([$artifact->id => ['category' => $category]]);

            // Attach any StoredFiles from the artifact to UiDemand's outputFiles with category 'output'
            // Note: UiDemand->outputFiles() relationship filters for category 'output'
            $storedFileIds = $artifact->storedFiles()->pluck('stored_files.id')->toArray();
            if (!empty($storedFileIds)) {
                // Build array with category for each stored file
                $storedFilesWithCategory = [];
                foreach ($storedFileIds as $storedFileId) {
                    $storedFilesWithCategory[$storedFileId] = ['category' => 'output'];
                }
                $uiDemand->morphToMany(\Newms87\Danx\Models\Utilities\StoredFile::class, 'storable', 'stored_file_storables')
                    ->syncWithoutDetaching($storedFilesWithCategory);
            }
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
