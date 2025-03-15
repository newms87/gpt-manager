<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObjectAttribute;
use Newms87\Danx\Resources\ActionResource;

abstract class TeamObjectAttributeResource extends ActionResource
{
    public static function data(TeamObjectAttribute $teamObjectAttribute): array
    {
        $threadUrl = null;
        $thread    = $teamObjectAttribute->threadRun?->agentThread;

        if ($thread) {
            $threadUrl = app_url("agents/$thread->agent_id/threads/$thread->id");
        }

        return [
            'id'         => $teamObjectAttribute->id,
            'name'       => $teamObjectAttribute->name,
            'value'      => $teamObjectAttribute->json_value ?? $teamObjectAttribute->text_value,
            'confidence' => $teamObjectAttribute->confidence,
            'reason'     => $teamObjectAttribute->reason,
            'sources'    => TeamObjectAttributeSourceResource::collection($teamObjectAttribute->sources),
            'thread_url' => $threadUrl,
            'created_at' => $teamObjectAttribute->created_at,
            'updated_at' => $teamObjectAttribute->updated_at,
        ];
    }
}
