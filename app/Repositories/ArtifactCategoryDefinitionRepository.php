<?php

namespace App\Repositories;

use App\Models\Schema\ArtifactCategoryDefinition;
use App\Models\Schema\SchemaDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class ArtifactCategoryDefinitionRepository extends ActionRepository
{
    public static string $model = ArtifactCategoryDefinition::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('schemaDefinition', fn(Builder $builder) => $builder->where('team_id', team()->id));
    }

    public function applyAction(string $action, ArtifactCategoryDefinition|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createCategory($data),
            'update' => $this->updateCategory($model, $data),
            default  => parent::applyAction($action, $model, $data)
        };
    }

    public function createCategory(array $input): ArtifactCategoryDefinition
    {
        $schemaDefinition = SchemaDefinition::find($input['schema_definition_id'] ?? null);
        if (!$schemaDefinition) {
            throw new ValidationError('Schema Definition was not found: ' . ($input['schema_definition_id'] ?? 'no Schema Definition ID was given'));
        }

        $name = $input['name'] ?? 'New Category';
        $name = ModelHelper::getNextUniqueValue(
            ArtifactCategoryDefinition::withTrashed()->where('schema_definition_id', $schemaDefinition->id),
            'name',
            $name
        );

        $label = $input['label'] ?? $name;

        $category = ArtifactCategoryDefinition::make()->forceFill([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => $name,
            'label'                => $label,
            'prompt'               => $input['prompt'] ?? '',
            'fragment_selector'    => $input['fragment_selector'] ?? null,
            'editable'             => $input['editable'] ?? true,
            'deletable'            => $input['deletable'] ?? true,
        ]);

        return $this->updateCategory($category, []);
    }

    public function updateCategory(ArtifactCategoryDefinition $category, array $input): ArtifactCategoryDefinition
    {
        $category->fill($input)->validate()->save($input);

        return $category;
    }
}
