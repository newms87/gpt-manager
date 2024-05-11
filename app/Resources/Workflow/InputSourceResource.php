<?php

namespace App\Resources\Workflow;

use App\Models\Shared\InputSource;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin InputSource
 * @property InputSource $resource
 */
class InputSourceResource extends ActionResource
{
    protected static string $type = 'InputSource';

    public function data(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'data'       => $this->data,
            'tokens'     => $this->tokens,
            'created_at' => $this->created_at,
        ];
    }
}
