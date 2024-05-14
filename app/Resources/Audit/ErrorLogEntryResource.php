<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\ErrorLogEntry;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin ErrorLogEntry
 * @property ErrorLogEntry $resource
 */
class ErrorLogEntryResource extends ActionResource
{
    protected static string $type = 'ErrorLogEntry';

    public function data(): array
    {
        return [
            'id'           => $this->id,
            'error_class'  => $this->errorLog->error_class,
            'code'         => $this->errorLog->code,
            'level'        => $this->errorLog->level,
            'last_seen_at' => $this->errorLog->last_seen_at,
            'file'         => $this->errorLog->file,
            'line'         => $this->errorLog->line,
            'message'      => $this->full_message,
            'data'         => $this->data,
            'stack_trace'  => $this->errorLog->stack_trace,
            'created_at'   => $this->created_at,
        ];
    }
}
