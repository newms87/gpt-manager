<?php

namespace App\Http\Resources\Assistant;

use App\Models\Assistant\AssistantAction;
use Newms87\Danx\Resources\ActionResource;

class AssistantActionResource extends ActionResource
{
    public static function data(AssistantAction $action): array
    {
        return [
            'id'             => $action->id,
            'context'        => $action->context,
            'action_type'    => $action->action_type,
            'target_type'    => $action->target_type,
            'target_id'      => $action->target_id,
            'status'         => $action->status,
            'title'          => $action->title,
            'description'    => $action->description,
            'payload'        => $action->payload,
            'preview_data'   => $action->preview_data,
            'result_data'    => $action->result_data,
            'error_message'  => $action->error_message,
            'duration'       => $action->duration,
            'started_at'     => $action->started_at?->toISOString(),
            'completed_at'   => $action->completed_at?->toISOString(),
            'created_at'     => $action->created_at->toISOString(),
            'updated_at'     => $action->updated_at->toISOString(),
            'is_pending'     => $action->isPending(),
            'is_in_progress' => $action->isInProgress(),
            'is_completed'   => $action->isCompleted(),
            'is_failed'      => $action->isFailed(),
            'is_cancelled'   => $action->isCancelled(),
            'is_finished'    => $action->isFinished(),
        ];
    }
}
