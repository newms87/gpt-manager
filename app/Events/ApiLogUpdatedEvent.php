<?php

namespace App\Events;

use App\Resources\Audit\ApiLogResource;
use Newms87\Danx\Events\ModelSavedEvent;
use Newms87\Danx\Models\Audit\ApiLog;

class ApiLogUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected ApiLog $apiLog, protected string $event)
    {
        // Team ID resolved in getTeamId() - works in job context
        parent::__construct(
            $apiLog,
            $event,
            ApiLogResource::class
        );
    }

    protected function getTeamId(): ?int
    {
        return $this->apiLog->auditRequest?->team_id;
    }

    protected function createdData(): array
    {
        return ApiLogResource::make($this->apiLog, [
            '*'           => false,
            'id'          => true,
            'status_code' => true,
            'method'      => true,
            'url'         => true,
            'started_at'  => true,
            'created_at'  => true,
        ]);
    }

    protected function updatedData(): array
    {
        return ApiLogResource::make($this->apiLog, [
            '*'           => false,
            'id'          => true,
            'status_code' => true,
            'response'    => true,
            'finished_at' => true,
            'run_time_ms' => true,
        ]);
    }
}
