<?php

namespace App\Resources\Billing;

use App\Models\Billing\Subscription;
use Newms87\Danx\Resources\ActionResource;

class SubscriptionResource extends ActionResource
{
    public static function data(Subscription $subscription): array
    {
        return [
            'id'                     => $subscription->id,
            'team_id'                => $subscription->team_id,
            'subscription_plan_id'   => $subscription->subscription_plan_id,
            'stripe_customer_id'     => $subscription->stripe_customer_id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'status'                 => $subscription->status,
            'billing_cycle'          => $subscription->billing_cycle,
            'monthly_amount'         => $subscription->monthly_amount,
            'yearly_amount'          => $subscription->yearly_amount,
            'current_amount'         => $subscription->getCurrentAmount(),
            'trial_ends_at'          => $subscription->trial_ends_at,
            'current_period_start'   => $subscription->current_period_start,
            'current_period_end'     => $subscription->current_period_end,
            'canceled_at'            => $subscription->canceled_at,
            'ends_at'                => $subscription->ends_at,
            'metadata'               => $subscription->metadata,
            'created_at'             => $subscription->created_at,
            'updated_at'             => $subscription->updated_at,
            'is_active'              => $subscription->isActive(),
            'is_canceled'            => $subscription->isCanceled(),
            'is_on_trial'            => $subscription->isOnTrial(),
            'subscription_plan'      => $subscription->subscriptionPlan ? [
                'id'              => $subscription->subscriptionPlan->id,
                'name'            => $subscription->subscriptionPlan->name,
                'description'     => $subscription->subscriptionPlan->description,
                'stripe_price_id' => $subscription->subscriptionPlan->stripe_price_id,
                'monthly_price'   => $subscription->subscriptionPlan->monthly_price,
                'yearly_price'    => $subscription->subscriptionPlan->yearly_price,
            ] : null,
        ];
    }
}
