<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\Audit;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin Audit
 * @property Audit $resource
 */
class AuditResource extends ActionResource
{
    protected static string $type = 'Audit';

    public function data(): array
    {
        return [
            'id'              => $this->id,
            'event'           => $this->event,
            'auditable_title' => $this->auditable_type . ' (' . $this->auditable_id . ')',
            'old_values'      => $this->old_values,
            'new_values'      => $this->new_values,
            'created_at'      => $this->created_at,
        ];
    }
}
