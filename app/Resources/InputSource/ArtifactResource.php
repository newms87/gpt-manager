<?php

namespace App\Resources\InputSource;

use App\Models\Shared\Artifact;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin Artifact
 * @property Artifact $resource
 */
class ArtifactResource extends ActionResource
{
    protected static string $type = 'Artifact';

    public function data(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'group'      => $this->group,
            'model'      => $this->model,
            'content'    => $this->content,
            'data'       => $this->data,
            'created_at' => $this->created_at,
        ];
    }
}
