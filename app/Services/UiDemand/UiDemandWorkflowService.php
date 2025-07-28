<?php

namespace App\Services\UiDemand;

use App\Models\TeamObject\TeamObject;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowListener;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use App\Services\Workflow\WorkflowListenerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

class UiDemandWorkflowService extends WorkflowListenerService
{
    public function extractData(UiDemand $uiDemand): WorkflowRun
    {
        if (!$uiDemand->canExtractData()) {
            throw new ValidationError('Cannot extract data for this demand. Check status and existing workflows.');
        }

        return parent::runWorkflow(
            $uiDemand,
            WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA,
            'app.demand_workflow_extract_data',
            'DEMAND_WORKFLOW_EXTRACT_DATA',
            function () use ($uiDemand) {
                return $this->createWorkflowInputFromDemand($uiDemand, 'Extract Data');
            },
            function ($uiDemand) {
                $uiDemand->update(['status' => UiDemand::STATUS_PROCESSING]);
            }
        );
    }

    public function writeDemand(UiDemand $uiDemand): WorkflowRun
    {
        if (!$uiDemand->canWriteDemand()) {
            throw new ValidationError('Cannot write demand. Check if extract data is completed and team object exists.');
        }

        return parent::runWorkflow(
            $uiDemand,
            WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND,
            'app.demand_workflow_write_demand',
            'DEMAND_WORKFLOW_WRITE_DEMAND',
            function () use ($uiDemand) {
                return $this->createWorkflowInputFromTeamObject($uiDemand, $uiDemand->teamObject, 'Write Demand');
            },
            function ($uiDemand) {
                $uiDemand->update(['status' => UiDemand::STATUS_PROCESSING]);
            }
        );
    }

    public function handleUiDemandWorkflowComplete(WorkflowRun $workflowRun): void
    {
        parent::onWorkflowComplete(
            $workflowRun,
            function ($listener, $workflowRun, $workflowListener) {
                if ($listener instanceof UiDemand) {
                    $this->handleWorkflowSuccess($listener, $workflowRun, $workflowListener);
                }
            },
            function ($listener, $workflowRun, $workflowListener) {
                if ($listener instanceof UiDemand) {
                    $this->handleWorkflowFailure($listener, $workflowRun, $workflowListener);
                }
            }
        );
    }

    protected function handleWorkflowSuccess(UiDemand $uiDemand, WorkflowRun $workflowRun, WorkflowListener $workflowListener): void
    {
        $workflowType = $workflowListener->workflow_type;

        if ($workflowType === WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA) {
            $outputArtifacts = $workflowRun->collectFinalOutputArtifacts();
            $teamObject      = $this->findTeamObjectFromArtifacts($outputArtifacts);

            if ($teamObject) {
                $uiDemand->update(['team_object_id' => $teamObject->id]);

                // Update workflow listener metadata
                $workflowListener->update([
                    'metadata' => array_merge($workflowListener->metadata ?? [], [
                        'team_object_id' => $teamObject->id,
                        'completed_at'   => now()->toIso8601String(),
                    ]),
                ]);
            }

            // Check if we should auto-run write demand
            if ($workflowListener->metadata['auto_write_demand'] ?? false) {
                $this->writeDemand($uiDemand);
            }
        } elseif ($workflowType === WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND) {
            $outputArtifacts = $workflowRun->collectFinalOutputArtifacts();
            $googleDocsUrl   = $this->extractGoogleDocsUrl($outputArtifacts);

            if ($googleDocsUrl) {
                $storedFile = $this->createStoredFileForGoogleDocs($googleDocsUrl, $uiDemand);
                $uiDemand->storedFiles()->attach($storedFile->id, ['category' => 'demand_output']);

                // Update workflow listener metadata
                $workflowListener->update([
                    'metadata' => array_merge($workflowListener->metadata ?? [], [
                        'google_docs_url' => $googleDocsUrl,
                        'completed_at'    => now()->toIso8601String(),
                    ]),
                ]);
            }

            $uiDemand->update([
                'status'       => UiDemand::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    protected function handleWorkflowFailure(UiDemand $uiDemand, WorkflowRun $workflowRun, WorkflowListener $workflowListener): void
    {
        $workflowType = $workflowListener->workflow_type;

        // Update workflow listener metadata
        $workflowListener->update([
            'metadata' => array_merge($workflowListener->metadata ?? [], [
                'failed_at' => now()->toIso8601String(),
                'error'     => $workflowRun->status,
            ]),
        ]);

        $uiDemand->update(['status' => UiDemand::STATUS_FAILED]);
    }

    protected function createWorkflowInputFromDemand(UiDemand $uiDemand, string $workflowType): WorkflowInput
    {
        $workflowInputRepo = app(WorkflowInputRepository::class);

        $workflowInput = $workflowInputRepo->createWorkflowInput([
            'name'        => "{$workflowType}: {$uiDemand->title}",
            'description' => $uiDemand->description,
            'content'     => json_encode([
                'demand_id'   => $uiDemand->id,
                'title'       => $uiDemand->title,
                'description' => $uiDemand->description,
            ]),
        ]);

        $fileIds = $uiDemand->storedFiles()->pluck('stored_files.id')->toArray();
        if (!empty($fileIds)) {
            $workflowInputRepo->syncStoredFiles($workflowInput, ['files' => $fileIds]);
        }

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


    protected function findTeamObjectFromArtifacts($artifacts): ?TeamObject
    {
        foreach($artifacts as $artifact) {
            if ($artifact->meta && isset($artifact->meta['team_object_id'])) {
                return TeamObject::find($artifact->meta['team_object_id']);
            }
        }

        return null;
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
