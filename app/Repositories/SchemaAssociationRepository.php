<?php

namespace App\Repositories;

use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class SchemaAssociationRepository extends ActionRepository
{
    public static string $model = SchemaAssociation::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('schemaDefinition', fn(Builder $builder) => $builder->where('team_id', team()->id));
    }

    public function applyAction(string $action, SchemaAssociation|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'update' => $this->updateAssociation($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Update a Schema Association verifying the schema and fragment exist
     */
    public function updateAssociation(SchemaAssociation $schemaAssociation, array $input): SchemaAssociation
    {
        $schemaAssociation->fill($input);

        if ($schemaAssociation->schemaDefinition()->doesntExist()) {
            throw new ValidationError('Schema Definition was not found: ' . $schemaAssociation->schema_definition_id);
        }

        // Verify this schema fragment exists and belongs to the users team
        if ($schemaAssociation->schema_fragment_id) {
            $schemaFragment = SchemaFragment::whereHas('schemaDefinition', fn($b) => $b->where('team_id', team()->id))->find($schemaAssociation->schema_fragment_id);

            if (!$schemaFragment) {
                throw new ValidationError('Schema Fragment was not found: ' . $schemaAssociation->schema_fragment_id);
            }
        }

        $schemaAssociation->save();

        return $schemaAssociation;
    }
}
