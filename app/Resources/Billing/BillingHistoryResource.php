<?php

namespace App\Resources\Billing;

use App\Models\Billing\BillingHistory;
use Newms87\Danx\Resources\ActionResource;

class BillingHistoryResource extends ActionResource
{
    public static function data(BillingHistory $billingHistory): array
    {
        return [
            'id'           => $billingHistory->id,
            'team_id'      => $billingHistory->team_id,
            'type'         => $billingHistory->type,
            'description'  => $billingHistory->description,
            'amount'       => $billingHistory->amount,
            'status'       => $billingHistory->status,
            'billing_date' => $billingHistory->billing_date,
            'metadata'     => $billingHistory->metadata,
            'created_at'   => $billingHistory->created_at,
            'updated_at'   => $billingHistory->updated_at,
        ];
    }
}
