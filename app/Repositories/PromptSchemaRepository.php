<?php

namespace App\Repositories;

use App\Models\Prompt\PromptSchema;
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

    public function applyAction(string $action, PromptSchema|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createPromptSchema($data),
            'update' => $this->updatePromptSchema($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

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

    public function updatePromptSchema(PromptSchema $promptSchema, array $input): PromptSchema
    {
        $promptSchema->fill($input);

        $promptSchema->validate()->save($input);

        return $promptSchema;
    }
}
