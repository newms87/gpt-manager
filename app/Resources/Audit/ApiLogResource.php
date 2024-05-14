<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\ApiLog;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin ApiLog
 * @property ApiLog $resource
 */
class ApiLogResource extends ActionResource
{
    protected static string $type = 'ApiLog';

    public function data(): array
    {
        return [
            'id'               => $this->id,
            'api_class'        => $this->api_class,
            'service_name'     => $this->service_name,
            'status_code'      => $this->status_code,
            'method'           => $this->method,
            'url '             => $this->full_url,
            'request'          => $this->request,
            'response'         => $this->response,
            'request_headers'  => $this->request_headers,
            'response_headers' => $this->response_headers,
            'created_at'       => $this->created_at,
        ];
    }
}
