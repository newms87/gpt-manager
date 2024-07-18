<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowTask;
use Newms87\Danx\Services\TranscodeFileService;

class TranscodeWorkflowInputWorkflowTool extends WorkflowTool
{
    use AssignsWorkflowTasksTrait, ResolvesDependencyArtifactsTrait;

    public static string $toolName = 'Transcode Workflow Input';

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
