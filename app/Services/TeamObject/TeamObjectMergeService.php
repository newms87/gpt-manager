<?php

namespace App\Services\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;

class TeamObjectMergeService
{
    public function merge(TeamObject $sourceObject, TeamObject $targetObject, ?array $schema = null): TeamObject
    {
        $this->validateMerge($sourceObject, $targetObject);

        return DB::transaction(function () use ($sourceObject, $targetObject, $schema) {
            $this->mergeAttributes($sourceObject, $targetObject);
            $this->mergeRelationships($sourceObject, $targetObject, $schema);

            $sourceObject->delete();

            return $targetObject->fresh(['attributes', 'relationships']);
        });
    }

    protected function validateMerge(TeamObject $sourceObject, TeamObject $targetObject): void
    {
        $this->validateOwnership($sourceObject);
        $this->validateOwnership($targetObject);

        if ($sourceObject->id === $targetObject->id) {
            throw new ValidationError('Cannot merge object with itself', 400);
        }

        if ($sourceObject->type !== $targetObject->type) {
            throw new ValidationError('Cannot merge objects of different types', 400);
        }

        if ($sourceObject->schema_definition_id !== $targetObject->schema_definition_id) {
            throw new ValidationError('Cannot merge objects with different schema definitions', 400);
        }

        if ($sourceObject->team_id !== $targetObject->team_id) {
            throw new ValidationError('Cannot merge objects from different teams', 400);
        }
    }

    protected function validateOwnership(TeamObject $teamObject): void
    {
        $currentTeam = team();

        if (!$currentTeam) {
            throw new ValidationError('No active team found', 401);
        }

        if ($teamObject->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this team object', 403);
        }
    }

    protected function mergeAttributes(TeamObject $sourceObject, TeamObject $targetObject): void
    {
        $existingAttributeNames = $targetObject->attributes()->pluck('name')->toArray();

        $sourceObject->attributes()
            ->whereNotIn('name', $existingAttributeNames)
            ->update(['team_object_id' => $targetObject->id]);
    }

    protected function mergeRelationships(TeamObject $sourceObject, TeamObject $targetObject, ?array $schema = null): void
    {
        if (!$schema) {
            $schema = $targetObject->schemaDefinition?->schema;
        }
        $schemaProperties = $schema['properties'] ?? [];
        
        $sourceRelationships = $sourceObject->relationships()->with('related')->get();
        $groupedSourceRelationships = $sourceRelationships->groupBy('relationship_name');

        foreach ($groupedSourceRelationships as $relationshipName => $relationships) {
            $relationshipSchema = $schemaProperties[$relationshipName] ?? null;
            $relationshipType = $relationshipSchema['type'] ?? 'array';

            if ($relationshipType === 'object') {
                $this->mergeObjectRelationship($targetObject, $relationshipName, $relationships, $relationshipSchema);
            } else {
                $this->mergeArrayRelationship($targetObject, $relationshipName, $relationships, $relationshipSchema);
            }
        }

        $sourceObject->relationships()
            ->update(['team_object_id' => $targetObject->id]);
    }

    protected function mergeObjectRelationship(TeamObject $targetObject, string $relationshipName, $sourceRelationships, ?array $relationshipSchema): void
    {
        $existingTargetRelationship = $targetObject->relationships()
            ->where('relationship_name', $relationshipName)
            ->with('related')
            ->first();

        $sourceRelationship = $sourceRelationships->first();
        $sourceRelatedObject = $sourceRelationship?->related;

        if (!$sourceRelatedObject) {
            return;
        }

        if ($existingTargetRelationship && $existingTargetRelationship->related) {
            $targetRelatedObject = $existingTargetRelationship->related;
            $this->merge($sourceRelatedObject, $targetRelatedObject, $relationshipSchema);
        } else {
            $uniqueName = ModelHelper::getNextModelName(
                TeamObject::make(['name' => $sourceRelatedObject->name]),
                'name',
                [
                    'team_id' => $sourceRelatedObject->team_id,
                    'type' => $sourceRelatedObject->type,
                    'schema_definition_id' => $sourceRelatedObject->schema_definition_id,
                    'root_object_id' => $targetObject->id,
                ]
            );

            $sourceRelatedObject->update([
                'name' => $uniqueName,
                'root_object_id' => $targetObject->id,
            ]);
        }
    }

    protected function mergeArrayRelationship(TeamObject $targetObject, string $relationshipName, $sourceRelationships, ?array $relationshipSchema): void
    {
        foreach ($sourceRelationships as $relationship) {
            $relatedObject = $relationship->related;

            if (!$relatedObject) {
                continue;
            }

            $uniqueName = ModelHelper::getNextModelName(
                TeamObject::make(['name' => $relatedObject->name]),
                'name',
                [
                    'team_id' => $relatedObject->team_id,
                    'type' => $relatedObject->type,
                    'schema_definition_id' => $relatedObject->schema_definition_id,
                    'root_object_id' => $targetObject->id,
                ]
            );

            $relatedObject->update([
                'name' => $uniqueName,
                'root_object_id' => $targetObject->id,
            ]);
        }
    }
}
