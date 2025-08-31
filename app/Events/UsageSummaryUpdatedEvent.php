<?php

namespace App\Events;

use App\Models\Usage\UsageSummary;
use App\Resources\Usage\UsageSummaryResource;
use Illuminate\Broadcasting\PrivateChannel;
use Newms87\Danx\Events\ModelSavedEvent;

class UsageSummaryUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected UsageSummary $usageSummary, protected string $event)
    {
        parent::__construct($usageSummary, $event);
    }

    public function getUsageSummary(): UsageSummary
    {
        return $this->usageSummary;
    }

    public function broadcastOn()
    {
        // Get the team_id from the related object (UiDemand)
        $relatedObject = $this->usageSummary->object;
        $teamId = $relatedObject?->team_id ?? $relatedObject?->currentTeam?->id;
        
        return new PrivateChannel('UsageSummary.' . $teamId);
    }

    public function data(): array
    {
        return array_merge(
            UsageSummaryResource::make($this->usageSummary),
            [
                'id' => $this->usageSummary->id,
                'object_type' => $this->usageSummary->object_type,
                'object_id' => $this->usageSummary->object_id,
                'object_id_int' => $this->usageSummary->object_id_int,
                '__type' => 'UsageSummaryResource',
            ]
        );
    }
}