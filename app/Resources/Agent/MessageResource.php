<?php

namespace App\Resources\Agent;

use App\Models\Agent\Message;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class MessageResource extends ActionResource
{
    /**
     * @param Message $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'         => $model->id,
            'role'       => $model->role,
            'title'      => $model->title,
            'summary'    => $model->summary,
            'content'    => $model->content,
            'data'       => $model->data,
            'timestamp'  => $model->updated_at,
            'created_at' => $model->created_at,
        ];
    }

    /**
     * @param Message $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'files' => StoredFileResource::collection($model->storedFiles()->with('transcodes')->get(), fn(StoredFile $storedFile) => [
                'transcodes' => StoredFileResource::collection($storedFile->transcodes),
            ]),
        ]);
    }
}
