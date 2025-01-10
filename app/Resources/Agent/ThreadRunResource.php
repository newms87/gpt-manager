<?php

namespace App\Resources\Agent;

use App\Models\Agent\ThreadRun;
use Newms87\Danx\Resources\ActionResource;

class ThreadRunResource extends ActionResource
{
    public static function data(ThreadRun $threadRun): array
    {
        return [
            'id'            => $threadRun->id,
            'status'        => $threadRun->status,
            'started_at'    => $threadRun->started_at,
            'completed_at'  => $threadRun->completed_at,
            'failed_at'     => $threadRun->failed_at,
            'refreshed_at'  => $threadRun->refreshed_at,
            'input_tokens'  => $threadRun->input_tokens,
            'output_tokens' => $threadRun->output_tokens,
            'thread'        => fn($fields) => ThreadResource::make($threadRun->thread, $fields),
        ];
    }
}
