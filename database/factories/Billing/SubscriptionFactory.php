<?php

namespace Database\Factories\Billing;

use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd   = $currentPeriodStart->copy()->addMonth();

        return [
            'team_id'                => Team::factory(),
            'subscription_plan_id'   => SubscriptionPlan::factory(),
            'stripe_customer_id'     => 'cus_' . $this->faker->uuid(),
            'stripe_subscription_id' => 'sub_' . $this->faker->uuid(),
            'status'                 => $this->faker->randomElement(['active', 'trialing', 'past_due', 'canceled']),
            'billing_cycle'          => $this->faker->randomElement(['monthly', 'yearly']),
            'monthly_amount'         => $this->faker->randomFloat(2, 19.99, 99.99),
            'yearly_amount'          => function (array $attributes) {
                return $attributes['monthly_amount'] * 10;
            },
            'trial_ends_at'        => null,
            'current_period_start' => $currentPeriodStart,
            'current_period_end'   => $currentPeriodEnd,
            'canceled_at'          => null,
            'ends_at'              => null,
            'cancel_at_period_end' => false,
            'metadata'             => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'               => 'active',
            'canceled_at'          => null,
            'ends_at'              => null,
            'cancel_at_period_end' => false,
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'        => 'trialing',
            'trial_ends_at' => Carbon::now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'      => 'canceled',
            'canceled_at' => Carbon::now()->subDays(1),
            'ends_at'     => Carbon::now()->addDays(15),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'past_due',
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn(array $attributes) => [
            'billing_cycle'        => 'monthly',
            'current_period_start' => Carbon::now()->startOfMonth(),
            'current_period_end'   => Carbon::now()->startOfMonth()->addMonth(),
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn(array $attributes) => [
            'billing_cycle'        => 'yearly',
            'current_period_start' => Carbon::now()->startOfYear(),
            'current_period_end'   => Carbon::now()->startOfYear()->addYear(),
        ]);
    }

    public function cancelAtPeriodEnd(): static
    {
        return $this->state(fn(array $attributes) => [
            'cancel_at_period_end' => true,
            'canceled_at'          => Carbon::now(),
        ]);
    }
}
