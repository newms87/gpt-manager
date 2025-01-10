<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Resources\ActionResource;

abstract class TeamObjectResource extends ActionResource
{
    public static function data(TeamObject $teamObject): array
    {
        return [
            'id'          => $teamObject->id,
            'type'        => $teamObject->type,
            'name'        => $teamObject->name,
            'description' => $teamObject->description,
            'date'        => $teamObject->date?->toDateTimeString(),
            'url'         => $teamObject->url,
            'meta'        => $teamObject->meta,
            'created_at'  => $teamObject->created_at,
            'updated_at'  => $teamObject->updated_at,
            'attributes'  => static::loadAttributes($teamObject),
            'relations'   => static::loadRelations($teamObject),
        ];
    }

    public static function loadAttributes(TeamObject $teamObject): array
    {
        // Resolve attributes
        $loadedAttributes = $teamObject->attributes()
            ->with('sources.sourceFile', 'sources.sourceMessage')
            ->get();

        $attributes = [];

        // Load the most recent or primary attributes for each attribute name
        foreach($loadedAttributes as $loadedAttribute) {
            $name = $loadedAttribute->name;
            // If the attribute is for a specific date and we already have a newer attribute set for this object, skip it
            if (isset($attributes[$name]) && $loadedAttribute->date && (empty($attributes[$name]['date']) || $loadedAttribute->date->isBefore($attributes[$name]['date']))) {
                continue;
            }

            $attributes[$name] = TeamObjectAttributeResource::make($loadedAttribute);
        }

        return $attributes;
    }

    public static function loadRelations(TeamObject $teamObject): array
    {
        // Resolve relationships w/ recently deleted included (so we can show the deletion in our response)
        $deletedAtColumn = $teamObject->relationships()->make()->getQualifiedDeletedAtColumn();
        $relations       = $teamObject->relationships()->withTrashed()->where(fn(Builder $builder) => $builder->where($deletedAtColumn, '>', now()->subMinute())->orWhereNull($deletedAtColumn))->joinRelation('related')->orderBy('related.name')->get();
        $relatedObjects  = [];
        foreach($relations as $relation) {
            // Always make sure the relationship name is set if this was recently deleted so FE can identify deleted resources
            if (!isset($relatedObjects[$relation->relationship_name])) {
                $relatedObjects[$relation->relationship_name] = [];
            }

            if (!$relation->deleted_at) {
                $relatedObjects[$relation->relationship_name][] = TeamObjectResource::make($relation->related);
            }
        }

        return $relatedObjects;
    }
}
