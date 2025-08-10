<?php

namespace Database\Factories\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Team\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingHistoryFactory extends Factory
{
    protected $model = BillingHistory::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'type' => $this->faker->randomElement(['invoice', 'payment', 'refund', 'usage_charge']),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 1.00, 999.99),
            'total_amount' => function (array $attributes) {
                return $attributes['amount'];
            },
            'currency' => 'USD',
            'status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'stripe_invoice_id' => 'in_' . $this->faker->uuid(),
            'stripe_charge_id' => 'ch_' . $this->faker->uuid(),
            'invoice_url' => $this->faker->url(),
            'billing_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'metadata' => [
                'source' => 'stripe',
                'processed_at' => Carbon::now()->toISOString(),
            ],
        ];
    }

    public function subscriptionPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'invoice',
            'description' => 'Monthly subscription payment',
        ]);
    }

    public function usageCharge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'usage_charge',
            'description' => 'API usage charges',
            'metadata' => [
                'usage_stats' => [
                    'total_tokens' => $this->faker->numberBetween(1000, 100000),
                    'total_requests' => $this->faker->numberBetween(10, 1000),
                    'event_count' => $this->faker->numberBetween(1, 100),
                ],
            ],
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'refund',
            'description' => 'Refund for overpayment',
            'amount' => -abs($attributes['amount']), // Negative amount for refunds
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_date' => $this->faker->dateTimeBetween('first day of this month', 'now'),
        ]);
    }

    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_date' => $this->faker->dateTimeBetween('first day of last month', 'last day of last month'),
        ]);
    }
}