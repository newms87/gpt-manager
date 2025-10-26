<?php

namespace App\Events;

use App\Models\Demand\UiDemand;
use App\Resources\UiDemandResource;
use Newms87\Danx\Events\ModelSavedEvent;

class UiDemandUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected UiDemand $uiDemand, protected string $event)
    {
        parent::__construct(
            $uiDemand,
            $event,
            UiDemandResource::class,
            $uiDemand->team_id
        );
    }

    protected function createdData(): array
    {
        return UiDemandResource::make($this->uiDemand, [
            '*'          => false,
            'title'      => true,
            'status'     => true,
            'created_at' => true,
        ]);
    }

    protected function updatedData(): array
    {
        return UiDemandResource::make($this->uiDemand, [
            '*'                                => false,
            'title'                            => true,
            'status'                           => true,
            'updated_at'                       => true,
            'completed_at'                     => true,
            'can_extract_data'                 => true,
            'can_write_medical_summary'        => true,
            'can_write_demand_letter'          => true,
            'is_extract_data_running'          => true,
            'is_write_medical_summary_running' => true,
            'is_write_demand_letter_running'   => true,
        ]);
    }
}
