<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use Log;

class TeamObjectForAgentsResource
{
    public static function make(TeamObject $teamObject): array
    {
        // Filter out only desired and non-empty attributes
        $loadedObject = collect($teamObject->toArray())->only(['id', 'url', 'date', 'type', 'name', 'meta', 'description'])->toArray();

        $schema = $teamObject->schemaDefinition?->schema;

        $loadedAttributes    = static::loadTeamObjectAttributes($teamObject);
        $loadedRelationships = static::recursivelyLoadTeamObjectRelations($teamObject, $schema);

        return $loadedAttributes + $loadedRelationships + $loadedObject;
    }

    /**
     * Load all attributes for a Team Object record
     */
    public static function loadTeamObjectAttributes(TeamObject $teamObject): array
    {
        $attributes = TeamObjectAttribute::where('team_object_id', $teamObject->id)->get();

        $loadedAttributes = [];

        foreach($attributes as $attribute) {
            $loadedAttributes[$attribute->name] = $attribute->getValue();
        }

        return $loadedAttributes;
    }

    /**
     * Load all relationships for a Team Object record and recursively load all attributes and relationships
     */
    public static function recursivelyLoadTeamObjectRelations(TeamObject $teamObject, array $schema = null, $maxDepth = 10): array
    {
        $relationships = TeamObjectRelationship::where('team_object_id', $teamObject->id)->get();

        $loadedRelationships = [];

        foreach($relationships as $relationship) {
            $relatedObject = TeamObject::find($relationship->related_team_object_id);

            if (!$relatedObject) {
                Log::warning("Could not find related object with ID: $relationship->related_team_object_id");
                continue;
            }

            $relatedSchema        = $schema['items']['properties'][$relationship->relationship_name] ?? $schema['properties'][$relationship->relationship_name] ?? [];
            $arrayRelatedObject   = collect($relatedObject->toArray())->except(['created_at', 'updated_at', 'deleted_at'])->toArray();
            $relatedRelationships = [];
            $relatedAttributes    = static::loadTeamObjectAttributes($relatedObject);

            // Keep loading recursively if we haven't reached the max depth
            if ($maxDepth > 0) {
                $relatedRelationships = static::recursivelyLoadTeamObjectRelations($relatedObject, $relatedSchema, $maxDepth - 1);
            } else {
                Log::warning("Max depth reached for object with ID: $teamObject->id");
            }

            $arrayRelatedObject = $relatedAttributes + $relatedRelationships + $arrayRelatedObject;

            // If the relationship is plural, add the object to the relationship array
            $relatedSchemaType = $relatedSchema['type'] ?? null;
            $currentRelation   = $loadedRelationships[$relationship->relationship_name] ?? null;

            if ($currentRelation) {
                if (is_associative_array($currentRelation)) {
                    $currentRelation = [$currentRelation, $arrayRelatedObject];
                } else {
                    $currentRelation[] = $arrayRelatedObject;
                }
            } else {
                if ($relatedSchemaType === 'array') {
                    $currentRelation = [$arrayRelatedObject];
                } else {
                    $currentRelation = $arrayRelatedObject;
                }
            }

            $loadedRelationships[$relationship->relationship_name] = $currentRelation;
        }

        return $loadedRelationships;
    }
}
