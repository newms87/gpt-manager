<?php

namespace App\Resources\Agent;

use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
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
        /** @var ThreadRun $currentRun */
        $currentRun = $this->currentRun()->first();

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'summary'    => $this->summary,
            'messages'   => MessageResource::collection($this->messages()->orderBy('id')->get()),
            'is_running' => (bool)$currentRun,
            'logs'       => $currentRun?->jobDispatch?->runningAuditRequest?->logs ?? '',
        ];
    }
}
