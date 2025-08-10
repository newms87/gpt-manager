<?php

namespace App\Resources\Billing;

use App\Models\Billing\PaymentMethod;
use Newms87\Danx\Resources\ActionResource;

class PaymentMethodResource extends ActionResource
{
    public static function data(PaymentMethod $paymentMethod): array
    {
        return [
            'id'                       => $paymentMethod->id,
            'team_id'                  => $paymentMethod->team_id,
            'stripe_payment_method_id' => $paymentMethod->stripe_payment_method_id,
            'type'                     => $paymentMethod->type,
            'card_brand'               => $paymentMethod->card_brand,
            'card_last_four'           => $paymentMethod->card_last_four,
            'card_exp_month'           => $paymentMethod->card_exp_month,
            'card_exp_year'            => $paymentMethod->card_exp_year,
            'is_default'               => $paymentMethod->is_default,
            'created_at'               => $paymentMethod->created_at,
            'updated_at'               => $paymentMethod->updated_at,
        ];
    }
}