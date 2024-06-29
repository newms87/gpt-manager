<?php

namespace App\Resources\Audit;

use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Audit\Audit;
use Newms87\Danx\Resources\ActionResource;

class AuditResource extends ActionResource
{
    /**
     * @param Audit $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'              => $model->id,
            'event'           => $model->event,
            'auditable_title' => $model->auditable_type . ' (' . $model->auditable_id . ')',
            'old_values'      => $model->old_values,
            'new_values'      => $model->new_values,
            'created_at'      => $model->created_at,
        ];
    }
}
