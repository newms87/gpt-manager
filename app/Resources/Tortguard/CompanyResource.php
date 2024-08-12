<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Model;

class CompanyResource extends TeamObjectResource
{
    /**
     * @param TeamObject $model
     */
    public static function details(Model $model): array
    {
        $parent = $model->relatedObjects('parent')->first();

        return static::make($model, [
            'parent' => CompanyResource::make($parent),
        ]);
    }
}
