<?php

namespace App\Resources\Agent;

use App\Models\Agent\AgentThreadRun;
use App\Resources\Usage\UsageSummaryResource;
use Newms87\Danx\Resources\ActionResource;

class AgentThreadRunResource extends ActionResource
{
    public static function data(AgentThreadRun $threadRun): array
    {
        return [
            'id'                   => $threadRun->id,
            'status'               => $threadRun->status,
            'started_at'           => $threadRun->started_at,
            'completed_at'         => $threadRun->completed_at,
            'failed_at'            => $threadRun->failed_at,
            'refreshed_at'         => $threadRun->refreshed_at,
            'usage'                => UsageSummaryResource::make($threadRun->usageSummary),
            'response_format'      => $threadRun->response_format,
            'response_schema_id'   => $threadRun->response_schema_id,
            'response_fragment_id' => $threadRun->response_fragment_id,
            'agent_thread_id'      => $threadRun->agent_thread_id,
            'thread'               => fn($fields) => AgentThreadResource::make($threadRun->agentThread, $fields),
        ];
    }
}
