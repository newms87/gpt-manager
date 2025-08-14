<?php

namespace App\Services\UiDemand;

use App\Models\TeamObject\TeamObject;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use App\Services\Workflow\WorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

class UiDemandWorkflowService
{
    public function extractData(UiDemand $uiDemand): WorkflowRun
    {
        if (!$uiDemand->canExtractData()) {
            throw new ValidationError('Cannot extract data for this demand. Check status and existing workflows.');
        }

        $workflowDefinition = $this->getWorkflowDefinition('extract_data');
        $workflowInput = $this->createWorkflowInputFromDemand($uiDemand, 'Extract Data');
        
        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);
        
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_EXTRACT_DATA]);
        
        return $workflowRun;
    }

    public function writeDemand(UiDemand $uiDemand): WorkflowRun
    {
        if (!$uiDemand->canWriteDemand()) {
            throw new ValidationError('Cannot write demand. Check if extract data is completed and team object exists.');
        }

        $workflowDefinition = $this->getWorkflowDefinition('write_demand');
        $workflowInput = $this->createWorkflowInputFromTeamObject($uiDemand, $uiDemand->teamObject, 'Write Demand');
        
        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);
        
        $uiDemand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => UiDemand::WORKFLOW_TYPE_WRITE_DEMAND]);
        
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
        $workflowName = $workflowRun->workflowDefinition->name;
        $outputArtifacts = $workflowRun->collectFinalOutputArtifacts();
        
        if ($workflowName === config('ui-demands.workflows.extract_data')) {
            $metadata = [
                'extract_data_completed_at' => now()->toIso8601String(),
                'workflow_run_id' => $workflowRun->id,
            ];
            
            $uiDemand->update([
                'status' => UiDemand::STATUS_DRAFT,
                'metadata' => array_merge($uiDemand->metadata ?? [], $metadata),
            ]);
            
        } elseif ($workflowName === config('ui-demands.workflows.write_demand')) {
            $googleDocsUrl = $this->extractGoogleDocsUrl($outputArtifacts);
            
            $metadata = [
                'write_demand_completed_at' => now()->toIso8601String(),
                'workflow_run_id' => $workflowRun->id,
            ];
            
            if ($googleDocsUrl) {
                $storedFile = $this->createStoredFileForGoogleDocs($googleDocsUrl, $uiDemand);
                $uiDemand->storedFiles()->attach($storedFile->id, ['category' => 'demand_output']);
                $metadata['google_docs_url'] = $googleDocsUrl;
            }
            
            $uiDemand->update([
                'status' => UiDemand::STATUS_DRAFT, // Stay as Draft until manually published
                'metadata' => array_merge($uiDemand->metadata ?? [], $metadata),
            ]);
        }
    }

    protected function handleWorkflowFailure(UiDemand $uiDemand, WorkflowRun $workflowRun): void
    {
        $metadata = array_merge($uiDemand->metadata ?? [], [
            'failed_at' => now()->toIso8601String(),
            'error' => $workflowRun->status,
            'workflow_run_id' => $workflowRun->id,
        ]);
        
        $uiDemand->update([
            'status' => UiDemand::STATUS_FAILED,
            'metadata' => $metadata,
        ]);
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
            'team_object_type' => 'demand',
            'content'          => json_encode([
                'demand_id'   => $uiDemand->id,
                'title'       => $uiDemand->title,
                'description' => $uiDemand->description,
            ]),
        ]);

        $fileIds = $uiDemand->storedFiles()->pluck('stored_files.id')->toArray();
        $workflowInput->storedFiles()->sync($fileIds);

        return $workflowInput;
    }

    protected function createWorkflowInputFromTeamObject(UiDemand $uiDemand, TeamObject $teamObject, string $workflowType): WorkflowInput
    {
        $workflowInputRepo = app(WorkflowInputRepository::class);

        return $workflowInputRepo->createWorkflowInput([
            'name'             => "{$workflowType}: {$uiDemand->title}",
            'description'      => $uiDemand->description,
            'team_object_id'   => $teamObject->id,
            'team_object_type' => $teamObject->type,
            'content'          => json_encode([
                'demand_id'   => $uiDemand->id,
                'title'       => $uiDemand->title,
                'description' => $uiDemand->description,
            ]),
        ]);
    }



    protected function extractGoogleDocsUrl($artifacts): ?string
    {
        foreach($artifacts as $artifact) {
            if ($artifact->text_content && str_contains($artifact->text_content, 'docs.google.com')) {
                preg_match('/https:\/\/docs\.google\.com\/[^\s]+/', $artifact->text_content, $matches);
                if (!empty($matches)) {
                    return $matches[0];
                }
            }

            if ($artifact->json_content && isset($artifact->json_content['google_docs_url'])) {
                return $artifact->json_content['google_docs_url'];
            }
        }

        return null;
    }

    protected function createStoredFileForGoogleDocs(string $url, UiDemand $uiDemand): StoredFile
    {
        $storedFile = StoredFile::make()->forceFill([
            'team_id'  => $uiDemand->team_id,
            'user_id'  => $uiDemand->user_id,
            'disk'     => 'external',
            'filepath' => $url,
            'filename' => "Demand Output - {$uiDemand->title}.gdoc",
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
            'url'      => $url,
            'meta'     => [
                'type'      => 'google_docs',
                'demand_id' => $uiDemand->id,
            ],
        ]);
        $storedFile->save();

        return $storedFile;
    }
}
