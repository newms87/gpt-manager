<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObject;

class TeamObjectForAgentsResource
{
    public static function make(TeamObject $teamObject): array
    {
        // Filter out only desired and non-empty attributes
        $resolvedObject = collect($teamObject->only(['type', 'name', 'description', 'value', 'date', 'url', 'meta']))->filter();

        // Format dates
        if ($resolvedObject->has('date')) {
            $resolvedObject['date'] = $resolvedObject['date']->toDateString();
        }

        // Add all relationships
        foreach($teamObject->relationships as $relationship) {
            $resolvedObject[$relationship->relationship_name] = static::make($relationship->related);
        }

        return $resolvedObject->toArray();
    }
}
