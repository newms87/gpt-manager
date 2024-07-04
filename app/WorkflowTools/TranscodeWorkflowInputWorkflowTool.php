<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use Newms87\Danx\Services\TranscodeFileService;

class TranscodeWorkflowInputWorkflowTool extends WorkflowTool
{
    public static string $toolName = 'Prepare Workflow Input';

    public function assignTasks(WorkflowJobRun $workflowJobRun, array $dependsOnJobs): void
    {
        // Always create 1 task to transcode the workflow input
        $workflowJobRun->tasks()->create([
            'user_id'         => user()->id,
            'workflow_job_id' => $workflowJobRun->workflow_job_id,
            'group'           => '',
            'status'          => WorkflowTask::STATUS_PENDING,
        ]);
    }

    /**
     * Run the tool on the workflow task to transcode any PDF files to images
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $workflowInput = $workflowTask->workflowJobRun->workflowRun->workflowInput;

        if (!$workflowInput->is_transcoded) {
            foreach($workflowInput->storedFiles as $storedFile) {
                if ($storedFile->isPdf()) {
                    app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);
                }
            }

            $workflowInput->is_transcoded = true;
            $workflowInput->save();
        }
    }
}
