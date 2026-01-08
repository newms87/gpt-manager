<?php

namespace App\Traits;

use App\Models\TeamObject\TeamObject;

/**
 * Helper trait for traversing TeamObject relationships.
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
}
