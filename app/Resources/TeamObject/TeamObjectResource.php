<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
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
        
        // Resolve relationships
        $relations      = $model->relationships()->get();
        $relatedObjects = [];
        foreach($relations as $relation) {
            $relatedObjects[$relation->relationship_name][] = TeamObjectResource::make($relation->related);
        }

        return [
                'id'          => $model->id,
                'name'        => $model->name,
                'description' => $model->description,
                'date'        => $model->date,
                'url'         => $model->url,
                'meta'        => $model->meta,
                'created_at'  => $model->created_at,
                'updated_at'  => $model->updated_at,
            ] + $attributes->toArray() + $relatedObjects;
    }
}
