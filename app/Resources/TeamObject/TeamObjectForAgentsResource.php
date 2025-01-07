<?php

namespace App\Resources\TeamObject;

class TeamObjectForAgentsResource
{
    public static function make(array $teamObject): array
    {
        // Filter out only desired and non-empty attributes
        $resolvedObject = collect(collect($teamObject)->only(['type', 'name', 'description', 'value', 'date', 'url', 'meta']))->filter();

        return $resolvedObject->toArray();
    }
}
