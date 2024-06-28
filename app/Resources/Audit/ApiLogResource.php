<?php

namespace App\Resources\Audit;

use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Resources\ActionResource;

class ApiLogResource extends ActionResource
{
    /**
     * @param ApiLog $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return [
            'id'               => $model->id,
            'api_class'        => $model->api_class,
            'service_name'     => $model->service_name,
            'status_code'      => $model->status_code,
            'method'           => $model->method,
            'url'              => $model->full_url,
            'request'          => $model->request,
            'response'         => $model->response,
            'request_headers'  => $model->request_headers,
            'response_headers' => $model->response_headers,
            'created_at'       => $model->created_at,
        ];
    }
}
