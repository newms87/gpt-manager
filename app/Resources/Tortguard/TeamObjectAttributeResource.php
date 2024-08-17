<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObjectAttribute;
use App\Resources\Agent\MessageResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

abstract class TeamObjectAttributeResource extends ActionResource
{
    /**
     * @param TeamObjectAttribute $model
     */
    public static function data(Model $model): array
    {
        $threadUrl = null;

        if ($model->sourceMessages?->isNotEmpty()) {
            $thread    = $model->sourceMessages->first()->thread;
            $threadUrl = app_url("agents/$thread->agent_id/threads/$thread->id");
        }

        return [
            'id'             => $model->id,
            'name'           => $model->name,
            'date'           => $model->date,
            'value'          => $model->json_value ?? $model->text_value,
            'confidence'     => $model->confidence,
            'description'    => $model->description,
            'source'         => StoredFileResource::make($model->sourceFile),
            'sourceMessages' => MessageResource::collection($model->sourceMessages),
            'thread_url'     => $threadUrl,
            'created_at'     => $model->created_at,
            'updated_at'     => $model->updated_at,
        ];
    }
}
