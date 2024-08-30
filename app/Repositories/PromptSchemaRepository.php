<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptSchema;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Repositories\ActionRepository;

class PromptSchemaRepository extends ActionRepository
{
    public static string $model = PromptSchema::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(agents_count) as agents_count"),
            DB::raw("SUM(workflow_jobs_count) as workflow_jobs_count"),
        ]);
    }

    /**
     * Apply the given action to the model.
     */
    public function applyAction(string $action, PromptSchema|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createPromptSchema($data),
            'update' => $this->updatePromptSchema($model, $data),
            'generate-example' => $this->generateResponseExample($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a new prompt schema with the provided input.
     */
    public function createPromptSchema($input): PromptSchema
    {
        $promptSchema = PromptSchema::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $input += [
            'type'          => PromptSchema::TYPE_AGENT_RESPONSE,
            'schema_format' => PromptSchema::FORMAT_YAML,
        ];

        return $this->updatePromptSchema($promptSchema, $input);
    }

    /**
     * Update the given prompt schema with the provided input.
     */
    public function updatePromptSchema(PromptSchema $promptSchema, array $input): PromptSchema
    {
        $promptSchema->fill($input);

        $promptSchema->validate()->save($input);

        return $promptSchema;
    }


    /**
     * Generate an example response for the given schema.
     *
     * This is useful for feedback for the user to validate the response schema and also used to identify available
     * fields for grouping when passing data between Workflow Jobs
     */
    public function generateResponseExample(PromptSchema $promptSchema): true
    {
        $agent = team()->agents()->firstOrCreate([
            'name' => 'Schema Response Example Generator [GENERATED]',
        ], [
            'api'             => config('ai.default_api'),
            'model'           => config('ai.default_model'),
            'temperature'     => 0,
            'response_format' => Agent::RESPONSE_FORMAT_JSON_OBJECT,
        ]);

        $threadRepo = app(ThreadRepository::class);
        $thread     = $threadRepo->create($agent, $promptSchema->name . ' Response Example');

        $message = 'Create a response following the provided schema below using example data. Provide a robust example response so all fields are included in the resulting JSON object with all permutations of fields from the given schema. DO NOT INCLUDE fields that do not exist in the schema below!! The goal is to create a response with an example that shows all possible fields for a response (even if fields are mutually exclusive or seem unnecessary or wrong, include all fields if they are in the schema conditionally, optionally or required). Pay close attention to field type if implied or specified!! If type is array, always provide exactly 2 items. Respond with JSON only! NO OTHER TEXT.';

        $threadRepo->addMessageToThread($thread, $message);
        $threadRepo->addMessageToThread($thread, $promptSchema->schema);

        $threadRun = app(AgentThreadService::class)->run($thread, dispatch: false);

        $promptSchema->response_example = $threadRun->lastMessage->getJsonContent() ?: [];
        $promptSchema->save();

        // Clean up the thread so we don't clutter the UI
        $thread->delete();

        return true;
    }
}
