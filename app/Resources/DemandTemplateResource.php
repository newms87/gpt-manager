<?php

namespace App\Resources;

use App\Models\Demand\DemandTemplate;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class DemandTemplateResource extends ActionResource
{
    public static function data(DemandTemplate $demandTemplate): array
    {
        return [
            'id'            => $demandTemplate->id,
            'name'          => $demandTemplate->name,
            'description'   => $demandTemplate->description,
            'category'      => $demandTemplate->category,
            'is_active'     => $demandTemplate->is_active,
            'metadata'      => $demandTemplate->metadata,
            'template_url'  => $demandTemplate->getTemplateUrl(),
            'google_doc_id' => $demandTemplate->extractGoogleDocId(),
            'created_at'    => $demandTemplate->created_at,
            'updated_at'    => $demandTemplate->updated_at,

            'stored_file'        => fn($fields) => $demandTemplate->storedFile ? StoredFileResource::make($demandTemplate->storedFile, $fields) : null,
            'user'               => fn($fields) => $demandTemplate->user ? [
                'id'    => $demandTemplate->user->id,
                'name'  => $demandTemplate->user->name,
                'email' => $demandTemplate->user->email,
            ] : null,
            'template_variables' => fn($fields) => TemplateVariableResource::collection($demandTemplate->templateVariables, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'stored_file'        => true,
            'user'               => true,
            'template_variables' => true,
        ]);
    }
}
