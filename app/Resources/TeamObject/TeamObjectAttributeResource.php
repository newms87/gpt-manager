<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObjectAttribute;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

abstract class TeamObjectAttributeResource extends ActionResource
{
    /**
     * @param TeamObjectAttribute $model
     */
    public static function data(Model $model): array
    {
        $threadUrl = null;
        $thread    = $model->threadRun?->thread;

        if ($thread) {
            $threadUrl = app_url("agents/$thread->agent_id/threads/$thread->id");
        }

        return [
            'id'          => $model->id,
            'name'        => $model->name,
            'date'        => $model->date,
            'value'       => $model->json_value ?? $model->text_value,
            'confidence'  => $model->confidence,
            'description' => $model->description,
            'sources'     => TeamObjectAttributeSourceResource::collection($model->sources),
            'thread_url'  => $threadUrl,
            'created_at'  => $model->created_at,
            'updated_at'  => $model->updated_at,
        ];
    }
}
