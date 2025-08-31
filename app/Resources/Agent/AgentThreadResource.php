<?php

namespace App\Resources\Agent;

use App\Http\Resources\Assistant\AssistantActionResource;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Resources\Audit\JobDispatchResource;
use App\Resources\Usage\UsageSummaryResource;
use Newms87\Danx\Resources\ActionResource;

class AgentThreadResource extends ActionResource
{
    public static function data(AgentThread $agentThread): array
    {
        return [
            'id'               => $agentThread->id,
            'name'             => $agentThread->name,
            'summary'          => $agentThread->summary,
            'is_running'       => $agentThread->isRunning(),
            'logs'             => $agentThread->lastRun?->jobDispatch?->runningAuditRequest?->logs ?? '',
            'usage'            => UsageSummaryResource::make($agentThread->usageSummary),
            'audit_request_id' => $agentThread->lastRun?->jobDispatch?->running_audit_request_id,
            'timestamp'        => $agentThread->updated_at,
            'can'              => $agentThread->can(),

            'messages'    => fn($fields) => $agentThread->canView() ? MessageResource::collection(
                $agentThread->sortedVisibleMessages,
                $fields
            ) : [
                new AgentThreadMessage([
                    'role'    => AgentThreadMessage::ROLE_USER,
                    'title'   => "Not Authorized",
                    'content' => "The contents of this message are hidden. You are not authorized to view this thread as it contains sensitive information belonging to another team",
                ]),
            ],
            'actions'     => fn($fields) => AssistantActionResource::collection($agentThread->assistantActions, $fields),
            'jobDispatch' => fn($fields) => JobDispatchResource::make($agentThread->lastRun?->jobDispatch, $fields),
        ];
    }
}
