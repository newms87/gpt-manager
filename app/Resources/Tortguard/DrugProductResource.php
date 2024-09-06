<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use App\Resources\TeamObject\TeamObjectResource;
use Illuminate\Database\Eloquent\Model;

class DrugProductResource extends TeamObjectResource
{
	/**
	 * @param TeamObject $model
	 */
	public static function details(Model $model): array
	{
		$company = $model->relatedObjects('company')->first();

		return static::make($model, [
			'company' => CompanyResource::make($company),
		]);
	}
}
