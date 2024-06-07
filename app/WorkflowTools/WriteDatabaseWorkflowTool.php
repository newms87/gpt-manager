<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use Newms87\Danx\Exceptions\ApiException;
use Newms87\Danx\Services\TranscodeFileService;

class WriteDatabaseWorkflowTool extends WorkflowTool
{
    public static $toolName = 'Write To Database';

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
     * @param WorkflowTask $workflowTask
     * @return void
     * @throws ApiException
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $workflowInput = $workflowTask->workflowJobRun->workflowRun->workflowInput;

        if (!$workflowInput->is_transcoded) {
            foreach($workflowInput->storedFiles as $storedFile) {
                app(TranscodeFileService::class)->pdfToImages($storedFile);
            }

            $workflowInput->is_transcoded = true;
            $workflowInput->save();
        }
    }
}
