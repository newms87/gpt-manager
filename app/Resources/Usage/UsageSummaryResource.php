<?php

namespace App\Resources\Usage;

use App\Models\Usage\UsageSummary;
use Newms87\Danx\Resources\ActionResource;

class UsageSummaryResource extends ActionResource
{
    public static function data(UsageSummary $usageSummary): array
    {
        return [
            'count'         => $usageSummary->count,
            'input_tokens'  => $usageSummary->input_tokens,
            'output_tokens' => $usageSummary->output_tokens,
            'input_cost'    => $usageSummary->input_cost,
            'output_cost'   => $usageSummary->output_cost,
            'total_cost'    => $usageSummary->total_cost,
            'created_at'    => $usageSummary->created_at,
        ];
    }
}
