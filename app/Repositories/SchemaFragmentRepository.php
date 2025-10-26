<?php

namespace App\Repositories;

use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class SchemaFragmentRepository extends ActionRepository
{
    public static string $model = SchemaFragment::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('schemaDefinition', fn(Builder $builder) => $builder->where('team_id', team()->id));
    }

    public function applyAction(string $action, SchemaFragment|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createFragment($data),
            'update' => $this->updateFragment($model, $data),
            default  => parent::applyAction($action, $model, $data)
        };
    }

    public function createFragment($input): SchemaFragment
    {
        $schemaDefinition = SchemaDefinition::find($input['schema_definition_id'] ?? null);
        if (!$schemaDefinition) {
            throw new ValidationError('Schema Definition was not found: ' . ($input['schema_definition_id'] ?? 'no Schema Definition ID was given'));
        }

        $name = $input['name'] ?? 'New Fragment';
        $name = ModelHelper::getNextUniqueValue($this->query()->where('schema_definition_id', $schemaDefinition->id), 'name', $name);

        $fragment = SchemaFragment::make()->forceFill([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => $name,
            'fragment_selector'    => ['type' => 'object'],
        ]);

        return $this->updateFragment($fragment, []);
    }

    public function updateFragment(SchemaFragment $fragment, array $input): SchemaFragment
    {
        $fragment->fill($input)->validate()->save($input);

        return $fragment;
    }
}
