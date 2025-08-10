<?php

namespace App\Resources\Billing;

use App\Models\Billing\SubscriptionPlan;
use Newms87\Danx\Resources\ActionResource;

class SubscriptionPlanResource extends ActionResource
{
    public static function data(SubscriptionPlan $subscriptionPlan): array
    {
        return [
            'id'              => $subscriptionPlan->id,
            'name'            => $subscriptionPlan->name,
            'slug'            => $subscriptionPlan->slug,
            'description'     => $subscriptionPlan->description,
            'stripe_price_id' => $subscriptionPlan->stripe_price_id,
            'monthly_price'   => $subscriptionPlan->monthly_price,
            'yearly_price'    => $subscriptionPlan->yearly_price,
            'features'        => $subscriptionPlan->features,
            'usage_limits'    => $subscriptionPlan->usage_limits,
            'is_active'       => $subscriptionPlan->is_active,
            'sort_order'      => $subscriptionPlan->sort_order,
            'created_at'      => $subscriptionPlan->created_at,
            'updated_at'      => $subscriptionPlan->updated_at,
        ];
    }
}