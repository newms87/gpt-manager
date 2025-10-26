<?php

namespace Database\Seeders;

use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Basic',
                'slug'            => 'basic',
                'description'     => 'Perfect for small teams and individual users',
                'monthly_price'   => 19.00,
                'yearly_price'    => 190.00,
                'stripe_price_id' => null, // Will be set when Stripe is configured
                'features'        => [
                    'max_users'           => 5,
                    'max_agents'          => 3,
                    'max_threads'         => 100,
                    'max_workflows'       => 10,
                    'api_calls_per_month' => 1000,
                    'storage_gb'          => 10,
                    'priority_support'    => false,
                    'custom_integrations' => false,
                ],
                'usage_limits' => [
                    'usage_based_billing'   => false,
                    'max_tokens_per_month'  => 100000,
                    'max_api_calls_per_day' => 100,
                ],
                'sort_order' => 1,
            ],
            [
                'name'            => 'Professional',
                'slug'            => 'professional',
                'description'     => 'For growing teams with advanced needs',
                'monthly_price'   => 49.00,
                'yearly_price'    => 490.00,
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'           => 20,
                    'max_agents'          => 10,
                    'max_threads'         => 500,
                    'max_workflows'       => 50,
                    'api_calls_per_month' => 5000,
                    'storage_gb'          => 50,
                    'priority_support'    => true,
                    'custom_integrations' => false,
                ],
                'usage_limits' => [
                    'usage_based_billing'   => false,
                    'max_tokens_per_month'  => 500000,
                    'max_api_calls_per_day' => 500,
                ],
                'sort_order' => 2,
            ],
            [
                'name'            => 'Enterprise',
                'slug'            => 'enterprise',
                'description'     => 'Unlimited power for large organizations',
                'monthly_price'   => 99.00,
                'yearly_price'    => 990.00,
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'           => -1, // Unlimited
                    'max_agents'          => -1,
                    'max_threads'         => -1,
                    'max_workflows'       => -1,
                    'api_calls_per_month' => -1,
                    'storage_gb'          => 200,
                    'priority_support'    => true,
                    'custom_integrations' => true,
                ],
                'usage_limits' => [
                    'usage_based_billing'   => false,
                    'max_tokens_per_month'  => 2000000,
                    'max_api_calls_per_day' => 2000,
                ],
                'sort_order' => 3,
            ],
            [
                'name'            => 'Pay As You Go',
                'slug'            => 'pay-as-you-go',
                'description'     => 'Usage-based pricing for variable workloads',
                'monthly_price'   => 0.00,
                'yearly_price'    => 0.00,
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'           => -1,
                    'max_agents'          => -1,
                    'max_threads'         => -1,
                    'max_workflows'       => -1,
                    'api_calls_per_month' => -1,
                    'storage_gb'          => 100,
                    'priority_support'    => true,
                    'custom_integrations' => true,
                ],
                'usage_limits' => [
                    'usage_based_billing'    => true,
                    'price_per_1k_tokens'    => 0.05,
                    'price_per_api_call'     => 0.001,
                    'minimum_monthly_charge' => 10.00,
                ],
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
