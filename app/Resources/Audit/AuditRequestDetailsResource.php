<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\AuditRequest;

/**
 * @mixin AuditRequest
 * @property AuditRequest $resource
 */
class AuditRequestDetailsResource extends AuditRequestResource
{
    public function data(): array
    {
        return [
                'audits'         => AuditResource::collection($this->audits),
                'api_logs'       => ApiLogResource::collection($this->apiLogs),
                'ran_jobs'       => JobDispatchResource::collection($this->ranJobs),
                'job_dispatches' => JobDispatchResource::collection($this->dispatchedJobs),
                'errors'         => ErrorLogEntryResource::collection($this->errorLogEntries),
            ] + parent::data();
    }
}
