<?php

namespace App\Resources\Agent;

use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Workflow\WorkflowAssignment;
use App\Resources\Prompt\AgentPromptDirectiveResource;
use App\Resources\Prompt\PromptDirectiveResource;
use App\Resources\Prompt\PromptSchemaResource;
use App\Resources\Workflow\WorkflowAssignmentResource;
use App\Resources\Workflow\WorkflowJobResource;
use App\Resources\Workflow\WorkflowResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class AgentResource extends ActionResource
{
    /**
     * @param Agent $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                     => $model->id,
            'knowledge_name'         => $model->knowledge?->name,
            'name'                   => $model->name,
            'description'            => $model->description,
            'api'                    => $model->api,
            'model'                  => $model->model,
            'temperature'            => $model->temperature,
            'tools'                  => $model->tools ?: [],
            'prompt'                 => $model->prompt,
            'response_format'        => $model->response_format,
            'response_notes'         => $model->response_notes,
            'response_sample'        => $model->getFormattedSampleResponse(),
            'enable_message_sources' => $model->enable_message_sources,
            'retry_count'            => $model->retry_count,
            'threads_count'          => $model->threads_count,
            'assignments_count'      => $model->assignments_count,
            'created_at'             => $model->created_at,
            'updated_at'             => $model->updated_at,
        ];
    }

    /**
     * @param Agent $model
     */
    public static function details(Model $model): array
    {
        $threads     = $model->threads()->orderByDesc('updated_at')->with('messages.storedFiles.transcodes')->limit(20)->get();
        $assignments = $model->assignments()->with('workflowJob.workflow')->limit(20)->get();
        $directives  = $model->directives()->orderBy('position')->with('directive')->get();

        return static::make($model, [
            'response_schema' => PromptSchemaResource::make($model->responseSchema),
            'directives'      => AgentPromptDirectiveResource::collection($directives, fn(AgentPromptDirective $agentPromptDirective) => [
                'directive' => PromptDirectiveResource::make($agentPromptDirective->directive),
            ]),
            'threads'         => ThreadResource::collection($threads, fn(Thread $thread) => [
                'messages' => MessageResource::collection($thread->messages, fn(Message $message) => [
                    'files' => StoredFileResource::collection($message->storedFiles, fn(StoredFile $storedFile) => [
                        'transcodes' => StoredFileResource::collection($storedFile->transcodes),
                    ]),
                ]),
            ]),
            'assignments'     => WorkflowAssignmentResource::collection($assignments, fn(WorkflowAssignment $assignment) => [
                'workflowJob' => WorkflowJobResource::make($assignment->workflowJob, [
                    'workflow' => WorkflowResource::make($assignment->workflowJob->workflow),
                ]),
            ]),
        ]);
    }
}
