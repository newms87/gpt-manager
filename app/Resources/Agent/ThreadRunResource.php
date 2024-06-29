<?php

namespace App\Resources\Agent;

use App\Models\Agent\ThreadRun;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class ThreadRunResource extends ActionResource
{
    /**
     * @param ThreadRun $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'            => $model->id,
            'status'        => $model->status,
            'started_at'    => $model->started_at,
            'completed_at'  => $model->completed_at,
            'failed_at'     => $model->failed_at,
            'refreshed_at'  => $model->refreshed_at,
            'input_token'   => $model->input_tokens,
            'output_tokens' => $model->output_tokens,
        ];
    }

    /**
     * @param ThreadRun $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'thread' => ThreadResource::make($model->thread),
        ]);
    }
}
