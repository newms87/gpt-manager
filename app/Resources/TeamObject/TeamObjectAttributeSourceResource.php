<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObjectAttributeSource;
use App\Resources\Agent\MessageResource;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

abstract class TeamObjectAttributeSourceResource extends ActionResource
{
    public static function data(TeamObjectAttributeSource $attributeSource): array
    {
        return [
            'id'            => $attributeSource->id,
            'source_type'   => $attributeSource->source_type,
            'source_id'     => $attributeSource->source_id,
            'explanation'   => $attributeSource->explanation,
            'sourceFile'    => StoredFileResource::make($attributeSource->sourceFile),
            'sourceMessage' => $attributeSource->sourceMessage ? MessageResource::details($attributeSource->sourceMessage) : null,
            'created_at'    => $attributeSource->created_at,
        ];
    }
}
