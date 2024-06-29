<?php

namespace App\Resources\Audit;

use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Resources\ActionResource;

class AuditRequestResource extends ActionResource
{
    /**
     * @param AuditRequest $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                    => $model->id,
            'session_id'            => $model->session_id,
            'user_name'             => $model->user ? $model->user->email . ' (' . $model->user_id . ')' : 'N/A',
            'environment'           => $model->environment,
            'http_method'           => $model->requestMethod(),
            'http_status_code'      => $model->statusCode(),
            'url'                   => $model->url,
            'request'               => $model->request,
            'response'              => $model->response,
            'response_length'       => $model->response ? $model->response['length'] : 0,
            'max_memory'            => $model->response ? $model->response['max_memory_used'] : 0,
            'logs'                  => $model->logs,
            'time'                  => $model->time,
            'audits_count'          => $model->audits()->count(),
            'api_logs_count'        => $model->apiLogs()->count(),
            'ran_jobs_count'        => $model->ranJobs()->count(),
            'dispatched_jobs_count' => $model->dispatchedJobs()->count(),
            'errors_count'          => $model->errorLogEntries()->count(),
            'created_at'            => $model->created_at,
            'updated_at'            => $model->updated_at,
        ];
    }

    /**
     * @param AuditRequest $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'audits'          => AuditResource::collection($model->audits),
            'api_logs'        => ApiLogResource::collection($model->apiLogs),
            'ran_jobs'        => JobDispatchResource::collection($model->ranJobs),
            'dispatched_jobs' => JobDispatchResource::collection($model->dispatchedJobs),
            'errors'          => ErrorLogEntryResource::collection($model->errorLogEntries),
        ]);
    }
}
