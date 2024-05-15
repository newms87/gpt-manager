<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\AuditRequest;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin AuditRequest
 * @property AuditRequest $resource
 */
class AuditRequestResource extends ActionResource
{
    protected static string $type = 'AuditRequest';

    public function data(): array
    {
        return [
            'id'                    => $this->id,
            'session_id'            => $this->session_id,
            'user_name'             => $this->user ? $this->user->email . ' (' . $this->user_id . ')' : 'N/A',
            'environment'           => $this->environment,
            'http_method'           => $this->requestMethod(),
            'http_status_code'      => $this->statusCode(),
            'url'                   => $this->url,
            'request'               => $this->request,
            'response'              => $this->response,
            'response_length'       => $this->response ? $this->response['length'] : 0,
            'max_memory'            => $this->response ? $this->response['max_memory_used'] : 0,
            'logs'                  => $this->logs,
            'time'                  => $this->time,
            'audits_count'          => $this->audits()->count(),
            'api_logs_count'        => $this->apiLogs()->count(),
            'ran_jobs_count'        => $this->ranJobs()->count(),
            'dispatched_jobs_count' => $this->dispatchedJobs()->count(),
            'errors_count'          => $this->errorLogEntries()->count(),
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
