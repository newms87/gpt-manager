<?php

namespace App\Resources\Audit;

use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Audit\ErrorLogEntry;
use Newms87\Danx\Resources\ActionResource;

class ErrorLogEntryResource extends ActionResource
{
    /**
     * @param ErrorLogEntry $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'           => $model->id,
            'error_class'  => $model->errorLog->error_class,
            'code'         => $model->errorLog->code,
            'level'        => $model->errorLog->level,
            'last_seen_at' => $model->errorLog->last_seen_at,
            'file'         => $model->errorLog->file,
            'line'         => $model->errorLog->line,
            'message'      => $model->full_message,
            'data'         => $model->data,
            'stack_trace'  => $model->errorLog->stack_trace,
            'created_at'   => $model->created_at,
        ];
    }
}
