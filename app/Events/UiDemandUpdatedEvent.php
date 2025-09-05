<?php

namespace App\Events;

use App\Models\Demand\UiDemand;
use App\Resources\UiDemandResource;
use Illuminate\Broadcasting\PrivateChannel;
use Newms87\Danx\Events\ModelSavedEvent;

class UiDemandUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected UiDemand $uiDemand, protected string $event)
    {
        parent::__construct($uiDemand, $event);
    }

    public function getUiDemand(): UiDemand
    {
        return $this->uiDemand;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('UiDemand.' . $this->uiDemand->team_id);
    }

    public function data(): array
    {
        return UiDemandResource::make($this->uiDemand);
    }
}
