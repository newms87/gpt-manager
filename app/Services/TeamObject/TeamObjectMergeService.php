<?php

namespace App\Services\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;

class TeamObjectMergeService
{
    public function merge(TeamObject $sourceObject, TeamObject $targetObject): TeamObject
    {
        $this->validateMerge($sourceObject, $targetObject);

        return DB::transaction(function () use ($sourceObject, $targetObject) {
            $this->mergeAttributes($sourceObject, $targetObject);
            $this->mergeRelationships($sourceObject, $targetObject);

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

    protected function mergeRelationships(TeamObject $sourceObject, TeamObject $targetObject): void
    {
        $sourceRelationships = $sourceObject->relationships()->with('related')->get();

        foreach($sourceRelationships as $relationship) {
            $relatedObject = $relationship->related;

            if (!$relatedObject) {
                continue;
            }

            $uniqueName = ModelHelper::getNextModelName(
                TeamObject::make(['name' => $relatedObject->name]),
                'name',
                [
                    'team_id'              => $relatedObject->team_id,
                    'type'                 => $relatedObject->type,
                    'schema_definition_id' => $relatedObject->schema_definition_id,
                    'root_object_id'       => $targetObject->id,
                ]
            );

            $relatedObject->update([
                'name'           => $uniqueName,
                'root_object_id' => $targetObject->id,
            ]);
        }

        $sourceObject->relationships()
            ->update(['team_object_id' => $targetObject->id]);
    }
}
