<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Model;

class DrugInjuryResource extends TeamObjectResource
{
    /**
     * @param TeamObject $model
     */
    public static function details(Model $model): array
    {
        $product = $model->relatedObjects('product')->first();
        $company = $product?->relatedObjects('company')->first();

        return static::make($model, [
            'product' => DrugProductResource::make($product, [
                'company' => CompanyResource::make($company),
            ]),
        ]);
    }
}
