<?php

namespace Database\Factories\Billing;

use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true) . ' Plan',
            'slug'          => $this->faker->slug(2),
            'description'   => $this->faker->sentence(),
            'monthly_price' => $this->faker->randomFloat(2, 9.99, 99.99),
            'yearly_price'  => function (array $attributes) {
                return $attributes['monthly_price'] * 10; // 10x monthly for yearly
            },
            'stripe_price_id' => 'price_' . $this->faker->uuid(),
            'features'        => [
                'api_calls'           => $this->faker->numberBetween(1000, 100000),
                'storage_gb'          => $this->faker->numberBetween(10, 1000),
                'support'             => $this->faker->randomElement(['email', 'chat', 'priority']),
                'advanced_analytics'  => $this->faker->boolean(),
                'custom_integrations' => $this->faker->boolean(),
            ],
            'usage_limits' => [
                'max_requests_per_month' => $this->faker->numberBetween(5000, 500000),
                'max_tokens_per_request' => $this->faker->numberBetween(1000, 10000),
                'usage_based_billing'    => $this->faker->boolean(),
            ],
            'is_active'  => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function usageBasedBilling(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limits' => array_merge($attributes['usage_limits'] ?? [], [
                'usage_based_billing' => true,
            ]),
        ]);
    }

    public function fixedBilling(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limits' => array_merge($attributes['usage_limits'] ?? [], [
                'usage_based_billing' => false,
            ]),
        ]);
    }
}
