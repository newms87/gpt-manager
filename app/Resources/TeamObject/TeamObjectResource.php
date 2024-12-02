<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

abstract class TeamObjectResource extends ActionResource
{
    /**
     * @param TeamObject $model
     */
    public static function data(Model $model): array
    {
        // Resolve attributes
        $attributes = $model->attributes()
            ->get()
            ->keyBy('name')
            ->map(fn(TeamObjectAttribute $attribute) => TeamObjectAttributeResource::make($attribute));

        // Resolve relationships w/ recently deleted included (so we can show the deletion in our response)
        $relations      = $model->relationships()->withTrashed()->where(fn(Builder $builder) => $builder->where('deleted_at', '>', now()->subMinute())->orWhereNull('deleted_at'))->get();
        $relatedObjects = [];
        foreach($relations as $relation) {
            // Always make sure the relationship name is set if this was recently deleted so FE can identify deleted resources
            if (!isset($relatedObjects[$relation->relationship_name])) {
                $relatedObjects[$relation->relationship_name] = [];
            }

            if (!$relation->deleted_at) {
                $relatedObjects[$relation->relationship_name][] = TeamObjectResource::make($relation->related);
            }
        }

        return [
                'id'          => $model->id,
                'type'        => $model->type,
                'name'        => $model->name,
                'description' => $model->description,
                'date'        => $model->date?->toDateTimeString(),
                'url'         => $model->url,
                'meta'        => $model->meta,
                'created_at'  => $model->created_at,
                'updated_at'  => $model->updated_at,
            ] + $attributes->toArray() + $relatedObjects;
    }
}
