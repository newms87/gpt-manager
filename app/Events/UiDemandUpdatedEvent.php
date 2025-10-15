<?php

namespace App\Events;

use App\Models\Demand\UiDemand;
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
        return [
            'id'     => $this->uiDemand->id,
            'name'   => $this->uiDemand->name,
            'status' => $this->uiDemand->status,
            '__type' => 'UiDemandResource',

            // Workflow state helpers
            'can_extract_data' => $this->uiDemand->canExtractData(),
            'can_write_demand_letter' => $this->uiDemand->canWriteDemandLetter(),
            'is_extract_data_running' => $this->uiDemand->isExtractDataRunning(),
            'is_write_demand_letter_running' => $this->uiDemand->isWriteDemandLetterRunning(),
        ];
    }
}
