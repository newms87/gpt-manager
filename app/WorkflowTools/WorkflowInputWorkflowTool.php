<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\TeamObjectRepository;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use App\WorkflowTools\Traits\ResolvesDependencyArtifactsTrait;
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
            'status'                 => WorkflowRun::STATUS_PENDING,
            'workflow_assignment_id' => null,
            'group'                  => 'default',
        ]);
    }

    /**
     * Get a usable example of what a response from this tool would look like
     * NOTE: Workflow input tool behaves a little differently in that it uses any assigned schemas as a template for
     * the teamObjects. The assumption is that a schema is defined for the selected Team Object type.
     */
    public function getResponseExample(WorkflowJob $workflowJob): array
    {
        $response = [
            'content' => 'Example content',
            'files'   => [
                [
                    'filename' => 'example.pdf',
                    'url'      => 'https://example.com/example.pdf',
                ],
                [
                    'filename' => 'example2.pdf',
                    'url'      => 'https://example.com/example2.pdf',
                ],
            ],
        ];


        if ($workflowJob->responseSchema) {
            $response['teamObjects'] = [$workflowJob->responseSchema->response_example];
        }

        return $response;
    }

    /**
     * Run the tool on the workflow task to transcode any PDF files to images
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $workflowInput = $workflowTask->workflowJobRun->workflowRun->workflowInput;

        Log::debug(self::$toolName . ": preparing $workflowTask ==> $workflowInput");
        $artifact = $workflowTask->artifacts()->create([
            'name'    => self::$toolName . ': ' . $workflowInput->name,
            'model'   => '',
            'content' => $workflowInput->content,
            'data'    => [
                'teamObjects' => $this->getTeamObjects($workflowInput),
            ],
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

        Log::debug(self::$toolName . ": created $artifact");
    }

    public function getTeamObjects(WorkflowInput $workflowInput): array
    {
        if (!$workflowInput->team_object_type) {
            return [];
        }

        $teamObject = app(TeamObjectRepository::class)->getFullyLoadedTeamObject($workflowInput->team_object_type, $workflowInput->team_object_id);


        // TODO: For now just one object, but maybe add team_object_filter field to query the team objects required

        return [TeamObjectForAgentsResource::make($teamObject)];
    }
}
