<?php

namespace App\Repositories;

use App\Models\Schema\SchemaDefinition;
use App\Resources\Schema\SchemaDefinitionResource;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class SchemaDefinitionRepository extends ActionRepository
{
    public static string $model = SchemaDefinition::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(agents_count) as agents_count"),
        ]);
    }

    /**
     * Apply the given action to the model.
     */
    public function applyAction(string $action, SchemaDefinition|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => SchemaDefinitionResource::make($this->createSchemaDefinition($data)),
            'update' => $this->updateSchemaDefinition($model, $data),
            'generate-example' => $this->generateResponseExample($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a new schema with the provided input.
     */
    public function createSchemaDefinition($input): SchemaDefinition
    {
        $schemaDefinition = SchemaDefinition::make()->forceFill([
            'team_id' => team()->id,
        ]);

        $input += [
            'type'          => SchemaDefinition::TYPE_AGENT_RESPONSE,
            'schema_format' => SchemaDefinition::FORMAT_YAML,
            'schema'        => [
                'type'  => 'object',
                'title' => $input['name'] ?? 'New Schema Object',
            ],
        ];

        return $this->updateSchemaDefinition($schemaDefinition, $input);
    }

    /**
     * Update the given schema with the provided input.
     */
    public function updateSchemaDefinition(SchemaDefinition $schemaDefinition, array $input): SchemaDefinition
    {
        if (!$schemaDefinition->canEdit()) {
            throw new ValidationError('You do not have permission to edit this schema.');
        }
        
        $schemaDefinition->fill($input);

        $schemaDefinition->validate()->save($input);

        return $schemaDefinition;
    }


    /**
     * Generate an example response for the given schema.
     *
     * This is useful for feedback for the user to validate the response schema and also used to identify available
     * fields for grouping when passing data between Workflow Jobs
     */
    public function generateResponseExample(SchemaDefinition $schemaDefinition): true
    {
        $agent = team()->agents()->firstOrCreate([
            'name' => 'Schema Response Example Generator [GENERATED]',
        ], [
            'api'         => config('ai.default_api'),
            'model'       => config('ai.default_model'),
            'api_options' => [
                'temperature' => 0,
            ],
        ]);

        $threadRepo = app(ThreadRepository::class);
        $thread     = $threadRepo->create($agent, $schemaDefinition->name . ' Response Example');

        $message = 'Create a response following the provided schema below using example data. Provide a robust example response so all fields are included in the resulting JSON object with all permutations of fields from the given schema. DO NOT INCLUDE fields that do not exist in the schema below!! The goal is to create a response with an example that shows all possible fields for a response (even if fields are mutually exclusive or seem unnecessary or wrong, include all fields if they are in the schema conditionally, optionally or required). Pay close attention to field type if implied or specified!! If type is array, always provide exactly 2 items. Respond with JSON only! NO OTHER TEXT.';

        $threadRepo->addMessageToThread($thread, $message);
        $threadRepo->addMessageToThread($thread, $schemaDefinition->schema);

        $threadRun = app(AgentThreadService::class)->run($thread);

        $schemaDefinition->response_example = $threadRun->lastMessage->getJsonContent() ?: [];
        $schemaDefinition->save();

        // Clean up the thread so we don't clutter the UI
        $thread->delete();

        return true;
    }
}
