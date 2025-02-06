<?php

namespace App\Resources\Audit;

use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Resources\ActionResource;

class JobDispatchResource extends ActionResource
{
    public static function data(JobDispatch $jobDispatch): array
    {
        return [
            'id'                        => $jobDispatch->id,
            'name'                      => $jobDispatch->name,
            'ref'                       => $jobDispatch->ref,
            'job_batch_id'              => $jobDispatch->job_batch_id,
            'running_audit_request_id'  => $jobDispatch->running_audit_request_id,
            'dispatch_audit_request_id' => $jobDispatch->dispatch_audit_request_id,
            'status'                    => $jobDispatch->status,
            'ran_at'                    => $jobDispatch->ran_at,
            'completed_at'              => $jobDispatch->completed_at,
            'timeout_at'                => $jobDispatch->timeout_at,
            'run_time'                  => $jobDispatch->run_time,
            'count'                     => $jobDispatch->count,
            'created_at'                => $jobDispatch->created_at,

            'logs'    => fn() => $jobDispatch->runningAuditRequest?->logs ?? '',
            'errors'  => fn($fields) => ErrorLogEntryResource::collection($jobDispatch->runningAuditRequest?->errorLogEntries, $fields),
            'apiLogs' => fn($fields) => ApiLogResource::collection($jobDispatch->runningAuditRequest?->apiLogs, $fields),
        ];
    }
}
