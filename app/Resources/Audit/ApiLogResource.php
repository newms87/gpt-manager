<?php

namespace App\Resources\Audit;

use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Resources\ActionResource;

class ApiLogResource extends ActionResource
{
    public static function data(ApiLog $apiLog): array
    {
        return [
            'id'               => $apiLog->id,
            'api_class'        => $apiLog->api_class,
            'service_name'     => $apiLog->service_name,
            'status_code'      => $apiLog->status_code,
            'method'           => $apiLog->method,
            'url'              => $apiLog->full_url,
            'request'          => $apiLog->request,
            'response'         => $apiLog->response,
            'request_headers'  => $apiLog->request_headers,
            'response_headers' => $apiLog->response_headers,
            'run_time_ms'      => $apiLog->run_time_ms,
            'created_at'       => $apiLog->created_at,
        ];
    }
}
