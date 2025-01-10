<?php

namespace App\Resources\ContentSource;

use App\Models\ContentSource\ContentSource;
use Newms87\Danx\Resources\ActionResource;

class ContentSourceResource extends ActionResource
{
    public static function data(ContentSource $contentSource): array
    {
        return [
            'id'                    => $contentSource->id,
            'name'                  => $contentSource->name,
            'type'                  => $contentSource->type,
            'url'                   => $contentSource->url,
            'polling_interval'      => $contentSource->polling_interval,
            'last_checkpoint'       => $contentSource->last_checkpoint,
            'fetched_at'            => $contentSource->fetched_at,
            'workflow_inputs_count' => $contentSource->workflow_inputs_count,
            'created_at'            => $contentSource->created_at,
            'config'                => fn() => $contentSource->config,
        ];
    }
}
