<?php

namespace App\Http\Resources\Assistant;

use App\Models\Assistant\AssistantAction;
use Newms87\Danx\Resources\ActionResource;

class AssistantActionResource extends ActionResource
{
    public function toArray($request): array
    {
        /** @var AssistantAction $this */
        return [
            'id'             => $this->id,
            'context'        => $this->context,
            'action_type'    => $this->action_type,
            'target_type'    => $this->target_type,
            'target_id'      => $this->target_id,
            'status'         => $this->status,
            'title'          => $this->title,
            'description'    => $this->description,
            'payload'        => $this->payload,
            'preview_data'   => $this->preview_data,
            'result_data'    => $this->result_data,
            'error_message'  => $this->error_message,
            'duration'       => $this->duration,
            'started_at'     => $this->started_at?->toISOString(),
            'completed_at'   => $this->completed_at?->toISOString(),
            'created_at'     => $this->created_at->toISOString(),
            'updated_at'     => $this->updated_at->toISOString(),
            'is_pending'     => $this->isPending(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed'   => $this->isCompleted(),
            'is_failed'      => $this->isFailed(),
            'is_cancelled'   => $this->isCancelled(),
            'is_finished'    => $this->isFinished(),
        ];
    }
}
