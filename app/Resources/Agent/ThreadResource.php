<?php

namespace App\Resources\Agent;

use App\Models\Agent\Thread;
use Newms87\Danx\Resources\ActionResource;

class ThreadResource extends ActionResource
{
    public static function data(Thread $thread): array
    {
        return [
            'id'               => $thread->id,
            'name'             => $thread->name,
            'summary'          => $thread->summary,
            'is_running'       => $thread->isRunning(),
            'logs'             => $thread->lastRun?->jobDispatch?->runningAuditRequest?->logs ?? '',
            'usage'            => $thread->getUsage(),
            'audit_request_id' => $thread->lastRun?->jobDispatch?->running_audit_request_id,
            'timestamp'        => $thread->updated_at,

            'messages' => fn($fields) => MessageResource::collection($thread->sortedMessages, $fields),
        ];
    }
}
