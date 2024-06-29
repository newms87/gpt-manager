<?php

namespace App\Resources\Audit;

use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Resources\ActionResource;

class JobDispatchResource extends ActionResource
{
    /**
     * @param JobDispatch $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                        => $model->id,
            'name'                      => $model->name,
            'ref'                       => $model->ref,
            'job_batch_id'              => $model->job_batch_id,
            'running_audit_request_id'  => $model->running_audit_request_id,
            'dispatch_audit_request_id' => $model->dispatch_audit_request_id,
            'status'                    => $model->status,
            'ran_at'                    => $model->ran_at,
            'completed_at'              => $model->completed_at,
            'timeout_at'                => $model->timeout_at,
            'run_time'                  => $model->run_time,
            'count'                     => $model->count,
            'created_at'                => $model->created_at,
        ];
    }
}
