<?php

namespace App\Events;

use App\Models\Usage\UsageSummary;
use App\Resources\Usage\UsageSummaryResource;
use Newms87\Danx\Events\ModelSavedEvent;

class UsageSummaryUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected UsageSummary $usageSummary, protected string $event)
    {
        // Team ID resolved in getTeamId() due to complex polymorphic relationship
        parent::__construct(
            $usageSummary,
            $event,
            UsageSummaryResource::class
        );
    }

    public function getUsageSummary(): UsageSummary
    {
        return $this->usageSummary;
    }

    protected function getTeamId(): ?int
    {
        // Get the team_id from the related polymorphic object
        $relatedObject = $this->usageSummary->object;

        return $relatedObject?->team_id ?? $relatedObject?->currentTeam?->id;
    }

    protected function createdData(): array
    {
        return UsageSummaryResource::make($this->usageSummary);
    }

    protected function updatedData(): array
    {
        return UsageSummaryResource::make($this->usageSummary, [
            '*'             => false,
            'count'         => true,
            'run_time_ms'   => true,
            'input_tokens'  => true,
            'output_tokens' => true,
            'total_tokens'  => true,
            'input_cost'    => true,
            'output_cost'   => true,
            'total_cost'    => true,
        ]);
    }
}
