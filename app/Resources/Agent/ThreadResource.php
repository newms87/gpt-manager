<?php

namespace App\Resources\Agent;

use App\Models\Agent\Thread;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin Thread
 * @property Thread $resource
 */
class ThreadResource extends ActionResource
{
    public static string $type = 'Thread';

    public function data(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'summary'          => $this->summary,
            'messages'         => MessageResource::collection($this->messages()->orderBy('id')->get()),
            'is_running'       => $this->isRunning(),
            'logs'             => $this->lastRun?->jobDispatch?->runningAuditRequest?->logs ?? '',
            'usage'            => $this->getUsage(),
            'audit_request_id' => $this->lastRun?->jobDispatch?->running_audit_request_id,
        ];
    }
}
