<?php

namespace App\Repositories;

use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Resources\TeamObject\TeamObjectAttributeResource;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use BadFunctionCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Repositories\ActionRepository;

class TeamObjectRepository extends ActionRepository
{
    public static string $model = TeamObject::class;

    public function query(): Builder
    {
        $query = parent::query()->where('team_id', team()->id);
        if (!can('view_imported_schemas')) {
            $query->whereDoesntHave('schemaDefinition.resourcePackageImport', fn(Builder $builder) => $builder->where('can_view', 0));
        }

        return $query;
    }

    public function applyAction(string $action, TeamObject|Model|array|null $model = null, ?array $data = null)
    {
        $type = $data['type'] ?? $model?->type;
        $name = $data['name'] ?? $model?->name;

        return match ($action) {
            'create'          => $this->createTeamObject($type, $name, $data),
            'update'          => (bool)$this->updateTeamObject($model, $data),
            'create-relation' => $this->createRelation($model, $data['relationship_name'] ?? null, $type, $name, $data),
            'save-attribute'  => TeamObjectAttributeResource::make($this->saveTeamObjectAttribute($model, $data['name'] ?? null, $data)),
            default           => parent::applyAction($action, $model, $data)
        };
    }

    public function resolveTeamObject($type, $name, $input = []): ?TeamObject
    {
        if (!$type || !$name) {
            throw new BadFunctionCallException("Team Objects requires a type and name for each object: \n\nType: $type\nName: $name\nInput:\n" . json_encode($input));
        }

        $schemaDefinition = null;
        $rootObject       = null;

        if (isset($input['schema_definition_id'])) {
            $schemaDefinition = SchemaDefinition::find($input['schema_definition_id']);

            if (!$schemaDefinition) {
                throw new ValidationError("Resolve Team Object ($type) $name failed: Schema Definition not found: $input[schema_definition_id]");
            }
        }

        if (isset($input['root_object_id'])) {
            $rootObject = TeamObject::find($input['root_object_id']);

            if (!$rootObject) {
                throw new ValidationError("Resolve Team Object ($type) $name failed: Root Object not found: $input[root_object_id]");
            }
        }

        $teamObjectQuery = $this->query()
            ->where('type', $type)
            ->where('name', $name)
            ->withTrashed();

        if ($schemaDefinition) {
            $teamObjectQuery->where('schema_definition_id', $schemaDefinition->id);
        } else {
            $teamObjectQuery->whereNull('schema_definition_id');
        }

        if ($rootObject) {
            $teamObjectQuery->where('root_object_id', $rootObject->id);
        } else {
            $teamObjectQuery->whereNull('root_object_id');
        }

        return $teamObjectQuery->first();
    }

    /**
     * Create a new Team Object record based on type, name and input
     */
    public function createTeamObject($type, $name, $input = []): TeamObject
    {
        $rootObjectId       = $input['root_object_id']       ?? null;
        $schemaDefinitionId = $input['schema_definition_id'] ?? null;

        $rootObject       = $rootObjectId ? TeamObject::find($rootObjectId) : null;
        $schemaDefinition = $schemaDefinitionId ? SchemaDefinition::find($schemaDefinitionId) : null;

        if ($rootObjectId && !$rootObject) {
            throw new ValidationError("Root Object not found: $rootObjectId");
        }

        if ($schemaDefinitionId && !$schemaDefinition) {
            throw new ValidationError("Schema Definition not found: $schemaDefinitionId");
        }

        return (new JSONSchemaDataToDatabaseMapper)
            ->setSchemaDefinition($schemaDefinition)
            ->setRootObject($rootObject)
            ->createTeamObject($type, $name, $input);
    }

    /**
     * Update an existing team object
     */
    public function updateTeamObject(TeamObject $teamObject, $input = []): TeamObject
    {
        return (new JSONSchemaDataToDatabaseMapper)->updateTeamObject($teamObject, $input);
    }

    /**
     * Create a new Team Object record and a relationship to another Team Object record based on type, name and related
     * object
     */
    public function createRelation(TeamObject $teamObject, $relationshipName, $type, $name, $input = []): TeamObject
    {
        if (!$relationshipName) {
            throw new ValidationError('Save Objects requires a relationship_name for each relation');
        }

        unset($input['name']);

        // Inherit the schema definition and root object from the parent object to ensure correct cardinality in DB
        $inheritedData = [
            'schema_definition_id' => $teamObject->schema_definition_id,
            'root_object_id'       => $teamObject->root_object_id ?? $teamObject->id,
        ];

        $mapper = (new JSONSchemaDataToDatabaseMapper)
            ->setSchemaDefinition($teamObject->schemaDefinition)
            ->setRootObject($teamObject->rootObject);

        $name          = ModelHelper::getNextModelName(TeamObject::make(['name' => $name]), 'name', ['type' => $type] + $inheritedData);
        $relatedObject = $mapper->createTeamObject($type, $name, $input + $inheritedData);

        $mapper->saveTeamObjectRelationship($teamObject, $relationshipName, $relatedObject);

        return $teamObject;
    }

    /**
     * Create or Update the value, confidence and sources for a Team Object Attribute record based on team object
     * and property name
     */
    public function saveTeamObjectAttribute(TeamObject $teamObject, $name, $attribute, ?array $meta = []): ?TeamObjectAttribute
    {
        return (new JSONSchemaDataToDatabaseMapper)->saveTeamObjectAttribute($teamObject, $name, $attribute, $meta);
    }

    /**
     * Load a Team Object record based on type and ID
     */
    public function loadTeamObject($type, $id): ?TeamObject
    {
        return TeamObject::where('id', $id)->where('type', $type)->first();
    }
}
