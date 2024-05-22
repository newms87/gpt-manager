<?php

namespace App\Resources\Audit;

use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin JobDispatch
 * @property JobDispatch $resource
 */
class JobDispatchResource extends ActionResource
{
    protected static string $type = 'JobDispatch';

    public function data(): array
    {
        return [
            'id'                        => $this->id,
            'name'                      => $this->name,
            'ref'                       => $this->ref,
            'job_batch_id'              => $this->job_batch_id,
            'running_audit_request_id'  => $this->running_audit_request_id,
            'dispatch_audit_request_id' => $this->dispatch_audit_request_id,
            'status'                    => $this->status,
            'ran_at'                    => $this->ran_at,
            'completed_at'              => $this->completed_at,
            'timeout_at'                => $this->timeout_at,
            'run_time'                  => $this->run_time,
            'count'                     => $this->count,
            'created_at'                => $this->created_at,
        ];
    }
}
