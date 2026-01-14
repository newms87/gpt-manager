<?php

namespace App\Traits;

use App\Models\TeamObject\TeamObject;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;

/**
 * Helper trait for traversing and managing TeamObject relationships.
 */
trait TeamObjectRelationshipHelper
{
    /**
     * Walk up the relationship tree from a TeamObject to get all ancestors.
     * Uses relatedToMe() which finds objects that point TO this object as a child.
     * Returns array of TeamObjects from root down (root first, immediate parent last).
     *
     * @return array<TeamObject>
     */
    protected function getAncestorChain(TeamObject $object): array
    {
        $chain   = [];
        $current = $object;

        // relatedToMe() finds objects that have THIS object as related_team_object_id
        // i.e., parents that point TO this child
        while ($parentRelation = $current->relatedToMe()->first()) {
            $parent = TeamObject::find($parentRelation->team_object_id);
            if (!$parent) {
                break;
            }

            array_unshift($chain, $parent);
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Resolve the root object (level 0 ancestor) from a parent object.
     *
     * The root object is always the top-level object in the hierarchy (e.g., Demand).
     * - If parent has a root_object_id, that points to the root
     * - If parent has no root_object_id, then parent IS the root
     */
    protected function resolveRootObject(TeamObject $parentObject): TeamObject
    {
        if ($parentObject->root_object_id) {
            $root = TeamObject::find($parentObject->root_object_id);
            if ($root) {
                return $root;
            }
        }

        return $parentObject;
    }

    /**
     * Ensure a parent-child relationship exists between two TeamObjects.
     * Creates the relationship if it doesn't already exist.
     *
     * @param  string  $relationshipName  The exact relationship name from the schema (e.g., "providers", "care_summary")
     */
    protected function ensureParentRelationship(TeamObject $parent, TeamObject $child, string $relationshipName): void
    {
        app(JSONSchemaDataToDatabaseMapper::class)
            ->saveTeamObjectRelationship($parent, $relationshipName, $child);
    }
}
