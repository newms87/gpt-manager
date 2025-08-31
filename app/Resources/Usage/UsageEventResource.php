<?php

namespace App\Resources\Usage;

use App\Models\Usage\UsageEvent;
use App\Resources\Auth\UserResource;
use Newms87\Danx\Resources\ActionResource;

class UsageEventResource extends ActionResource
{
    public static function data(UsageEvent $usageEvent): array
    {
        return [
            'id'            => $usageEvent->id,
            'event_type'    => $usageEvent->event_type,
            'api_name'      => $usageEvent->api_name,
            'run_time_ms'   => $usageEvent->run_time_ms,
            'input_tokens'  => $usageEvent->input_tokens,
            'output_tokens' => $usageEvent->output_tokens,
            'total_tokens'  => $usageEvent->total_tokens,
            'input_cost'    => $usageEvent->input_cost,
            'output_cost'   => $usageEvent->output_cost,
            'total_cost'    => $usageEvent->total_cost,
            'request_count' => $usageEvent->request_count,
            'data_volume'   => $usageEvent->data_volume,
            'metadata'      => $usageEvent->metadata,
            'created_at'    => $usageEvent->created_at,
            'updated_at'    => $usageEvent->updated_at,

            // Relationships
            'user' => fn($fields) => UserResource::make($usageEvent->user, $fields),
        ];
    }
}