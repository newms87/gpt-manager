<?php

namespace App\Repositories;

use App\Models\Prompt\PromptSchema;
use App\Models\Prompt\PromptSchemaFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class PromptSchemaFragmentRepository extends ActionRepository
{
    public static string $model = PromptSchemaFragment::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('promptSchema', fn(Builder $builder) => $builder->where('team_id', team()->id));
    }

    public function applyAction(string $action, PromptSchemaFragment|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createFragment($data),
            'update' => $this->updateFragment($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createFragment($input): PromptSchemaFragment
    {
        $promptSchema = PromptSchema::find($input['prompt_schema_id'] ?? null);
        if (!$promptSchema) {
            throw new ValidationError('Prompt Schema was not found: ' . ($input['prompt_schema_id'] ?? 'no Prompt Schema ID was given'));
        }

        $name = $input['name'] ?? 'New Fragment';
        $name = ModelHelper::getNextUniqueValue($this->query()->where('prompt_schema_id', $promptSchema->id), 'name', $name);

        $fragment = PromptSchemaFragment::make()->forceFill([
            'prompt_schema_id'  => $promptSchema->id,
            'name'              => $name,
            'fragment_selector' => ['type' => 'object'],
        ]);

        return $this->updateFragment($fragment, []);
    }

    public function updateFragment(PromptSchemaFragment $fragment, array $input): PromptSchemaFragment
    {
        $fragment->fill($input)->validate()->save($input);

        return $fragment;
    }
}
