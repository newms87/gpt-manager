<?php

namespace App\Resources\Agent;

use App\Models\Agent\Thread;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class ThreadResource extends ActionResource
{
    /**
     * @param Thread $model
     */
    public static function data(Model $model, $attributes = []): array
    {
        return static::make($model, [
                'id'               => $model->id,
                'name'             => $model->name,
                'summary'          => $model->summary,
                'is_running'       => $model->isRunning(),
                'logs'             => $model->lastRun?->jobDispatch?->runningAuditRequest?->logs ?? '',
                'usage'            => $model->getUsage(),
                'audit_request_id' => $model->lastRun?->jobDispatch?->running_audit_request_id,
            ] + $attributes);
    }

    /**
     * @param Thread $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'messages' => MessageResource::collection($model->messages()->orderBy('id')->get()),
        ]);
    }
}
