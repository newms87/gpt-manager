<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Repositories\WorkflowDefinitionRepository;
use App\Resources\Workflow\WorkflowDefinitionResource;
use App\Services\Workflow\WorkflowExportService;
use App\Services\Workflow\WorkflowImportService;
use App\Services\Workflow\WorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Repositories\FileRepository;
use Newms87\Danx\Requests\PagerRequest;
use Throwable;

class WorkflowDefinitionsController extends ActionController
{
    public static ?string $repo     = WorkflowDefinitionRepository::class;
    public static ?string $resource = WorkflowDefinitionResource::class;

    public function invoke(WorkflowDefinition $workflowDefinition, PagerRequest $request)
    {
        $payload    = $request->getJson('payload');
        $input      = $payload['input'] ?? [];
        $webhookUrl = $payload['webhook_url'] ?? null;

        try {
            if ($workflowDefinition->team_id !== team()->id) {
                throw new ValidationError('You do not have permission to invoke this workflow');
            }

            if (!$payload) {
                throw new ValidationError('payload is required');
            }

            if (!$input) {
                throw new ValidationError('payload.input is required');
            }

            if (!$webhookUrl) {
                throw new ValidationError('payload.webhook_url is required');
            }

            foreach($input['files'] ?? [] as $index => $file) {
                if (empty($file['filename'])) {
                    throw new ValidationError("payload.files[$index].filename is required");
                }

                if (empty($file['url'])) {
                    throw new ValidationError("payload.files[$index].url is required");
                }
            }

            $input += [
                'name' => 'Workflow 7 API Invocation ' . uniqid(),
            ];

            $workflowInput = WorkflowInput::make($input)
                ->forceFill([
                    'team_id' => team()->id,
                    'user_id' => user()->id,
                ]);

            foreach($input['files'] ?? [] as $file) {
                $storedFile = app(FileRepository::class)->createFileWithUrl('workflow-api-invoke/' . $file['filename'], $file['url']);
                $workflowInput->storedFiles()->attach($storedFile);
            }

            $workflowInput->save();

            $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$workflowInput->toArtifact()]);

            $workflowRun->workflowApiInvocation()->create([
                'name'        => $input['name'],
                'webhook_url' => $webhookUrl,
                'payload'     => $payload,
            ]);
        } catch(Throwable $throwable) {
            return [
                'error'   => true,
                'message' => $throwable->getMessage(),
            ];
        }

        return [
            'success'         => true,
            'workflow_run_id' => $workflowRun->id,
            'status'          => $workflowRun->status,
        ];
    }

    public function exportToJson(WorkflowDefinition $workflowDefinition)
    {
        if ($workflowDefinition->team_id !== team()->id) {
            throw new ValidationError('You do not have permission to export this workflow');
        }

        return app(WorkflowExportService::class)->exportToJson($workflowDefinition);
    }

    public function importFromJson(PagerRequest $pagerRequest): array
    {
        $json = $pagerRequest->getJson('workflowDefinitionJson');

        return WorkflowDefinitionResource::make(app(WorkflowImportService::class)->importFromJson($json));
    }
}
