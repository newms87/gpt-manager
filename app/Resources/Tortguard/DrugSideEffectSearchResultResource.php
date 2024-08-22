<?php

namespace App\Resources\Tortguard;

use Illuminate\Http\Resources\Json\JsonResource;

class DrugSideEffectSearchResultResource extends JsonResource
{
    public function toArray($request): array
    {
        $result = $this->resource;

        return [
            'product_url'        => $result['product_url'] ?? '',
            'product_name'       => $result['product_name'] ?? '',
            'description'        => $result['description'] ?? '',
            'side_effects'       => $result['side_effects'] ?? [],
            'companies'          => $result['companies'] ?? [],
            'indications'        => $result['indications'] ?? [],
            'generic_drug_names' => $result['generic_drug_names'] ?? [],
        ];
    }
}
