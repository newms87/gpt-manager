<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Services\TranscodeFileService;

class WorkflowInputWorkflowTool extends WorkflowTool
{
    use ResolvesDependencyArtifactsTrait;

    public static string $toolName = 'Workflow Input';

    public function assignTasks(WorkflowJobRun $workflowJobRun, array|Collection $dependencyArtifacts = []): void
    {
        $workflowJobRun->tasks()->create([
            'user_id'                => user()->id,
            'workflow_job_id'        => $workflowJobRun->workflow_job_id,
            'status'                 => WorkflowTask::STATUS_PENDING,
            'workflow_assignment_id' => null,
            'group'                  => 'default',
        ]);
    }

    public function getResponsePreview(WorkflowJob $workflowJob): array|string|null
    {
        return [
            [
                'content' => 'Example content',
                'files'   => [
                    [
                        'name' => 'example.pdf',
                        'url'  => 'https://example.com/example.pdf',
                    ],
                    [
                        'name' => 'example2.pdf',
                        'url'  => 'https://example.com/example2.pdf',
                    ],
                ],
            ],
        ];
    }

    /**
     * Run the tool on the workflow task to transcode any PDF files to images
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $workflowInput = $workflowTask->workflowJobRun->workflowRun->workflowInput;

        Log::debug(self::$toolName . ": preparing $workflowTask =====> $workflowInput");
        $files = [];

        $artifact = $workflowTask->artifacts()->create([
            'name'    => self::$toolName . ': ' . $workflowInput->name,
            'model'   => '',
            'content' => $workflowInput->content,
            'data'    => ['files' => $files],
        ]);

        foreach($workflowInput->storedFiles as $storedFile) {
            if ($storedFile->isPdf()) {
                app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

                $transcodes = $storedFile->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();
                foreach($transcodes as $transcode) {
                    $artifact->storedFiles()->attach($transcode);
                }
            } else {
                $artifact->storedFiles()->attach($storedFile);
            }
        }

        Log::debug(self::$toolName . ": created $artifact with content of " . strlen($artifact->content) . " bytes and " . count($files) . " files");
    }
}
