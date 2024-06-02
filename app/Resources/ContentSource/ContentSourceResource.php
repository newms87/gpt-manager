<?php

namespace App\Resources\ContentSource;

use App\Models\ContentSource\ContentSource;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin ContentSource
 * @property ContentSource $resource
 */
class ContentSourceResource extends ActionResource
{
    protected static string $type = 'ContentSource';

    public function data(): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'type'                  => $this->type,
            'url'                   => $this->url,
            'config'                => $this->config,
            'polling_interval'      => $this->polling_interval,
            'last_checkpoint'       => $this->last_checkpoint,
            'fetched_at'            => $this->fetched_at,
            'workflow_inputs_count' => $this->workflow_inputs_count,
            'created_at'            => $this->created_at,
        ];
    }
}
