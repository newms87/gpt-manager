<?php

namespace Tests\Feature\Api;

use App\Models\Billing\SubscriptionPlan;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class SubscriptionPlansControllerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_index_withActiveOnly_returnsOnlyActivePlans(): void
    {
        // Given
        $activePlan1  = SubscriptionPlan::factory()->create([
            'name'       => 'Basic Plan',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
        $activePlan2  = SubscriptionPlan::factory()->create([
            'name'       => 'Pro Plan',
            'is_active'  => true,
            'sort_order' => 2,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'name'       => 'Legacy Plan',
            'is_active'  => false,
            'sort_order' => 3,
        ]);

        // When
        $response = $this->get('/api/subscription-plans');

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();
        $response->assertJsonCount(2, 'plans');
        $response->assertJsonStructure([
            'plans' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'monthly_price',
                    'yearly_price',
                    'features',
                    'usage_limits',
                    'is_active',
                    'sort_order',
                ],
            ],
        ]);

        // Verify only active plans are returned
        $planNames = collect($response->json('plans'))->pluck('name')->toArray();
        $this->assertContains('Basic Plan', $planNames);
        $this->assertContains('Pro Plan', $planNames);
        $this->assertNotContains('Legacy Plan', $planNames);
    }

    public function test_index_withIncludeInactive_returnsAllPlans(): void
    {
        // Given
        $activePlan   = SubscriptionPlan::factory()->create([
            'name'       => 'Active Plan',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'name'       => 'Inactive Plan',
            'is_active'  => false,
            'sort_order' => 2,
        ]);

        // When
        $response = $this->get('/api/subscription-plans?include_inactive=1');

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();
        $response->assertJsonCount(2, 'plans');

        // Verify both active and inactive plans are returned
        $planNames = collect($response->json('plans'))->pluck('name')->toArray();
        $this->assertContains('Active Plan', $planNames);
        $this->assertContains('Inactive Plan', $planNames);
    }

    public function test_index_withNoPlans_returnsEmptyArray(): void
    {
        // When
        $response = $this->get('/api/subscription-plans');

        // Then
        $response->assertOk();
        $response->assertJson(['plans' => []]);
    }

    public function test_show_withValidPlan_returnsPlan(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create([
            'name'          => 'Test Plan',
            'description'   => 'A test plan',
            'monthly_price' => 29.99,
            'yearly_price'  => 299.99,
            'features'      => [
                'api_calls'  => 10000,
                'storage_gb' => 100,
                'support'    => 'email',
            ],
            'usage_limits'  => [
                'max_requests_per_month' => 50000,
                'max_tokens_per_request' => 4000,
            ],
        ]);

        // When
        $response = $this->get("/api/subscription-plans/{$plan->id}");

        // Then
        $response->assertOk();
        $response->assertJson([
            'plan' => [
                'id'            => $plan->id,
                'name'          => 'Test Plan',
                'description'   => 'A test plan',
                'monthly_price' => 29.99,
                'yearly_price'  => 299.99,
                'features'      => [
                    'api_calls'  => 10000,
                    'storage_gb' => 100,
                    'support'    => 'email',
                ],
                'usage_limits'  => [
                    'max_requests_per_month' => 50000,
                    'max_tokens_per_request' => 4000,
                ],
            ],
        ]);
    }

    public function test_show_withInvalidPlan_returnsNotFound(): void
    {
        // When
        $response = $this->get('/api/subscription-plans/999999');

        // Then
        $response->assertNotFound();
    }

    public function test_compare_withNoPlanIds_returnsAllActivePlans(): void
    {
        // Given
        $basicPlan = SubscriptionPlan::factory()->create([
            'name'       => 'Basic',
            'slug'       => 'basic',
            'is_active'  => true,
            'sort_order' => 1,
            'features'   => [
                'api_calls'  => 1000,
                'storage_gb' => 10,
                'support'    => 'email',
            ],
        ]);

        $proPlan = SubscriptionPlan::factory()->create([
            'name'       => 'Pro',
            'slug'       => 'pro',
            'is_active'  => true,
            'sort_order' => 2,
            'features'   => [
                'api_calls'          => 10000,
                'storage_gb'         => 100,
                'support'            => 'priority',
                'advanced_analytics' => true,
            ],
        ]);

        $inactivePlan = SubscriptionPlan::factory()->create([
            'is_active' => false,
        ]);

        // When
        $response = $this->get('/api/subscription-plans/compare');

        // Then
        $response->assertOk();
        $response->assertJsonCount(2, 'plans');
        $response->assertJsonStructure([
            'plans'      => [
                '*' => ['id', 'name', 'slug', 'features'],
            ],
            'comparison' => [
                '*' => [
                    'feature',
                    'basic',
                    'pro',
                ],
            ],
        ]);

        // Verify comparison matrix includes all features
        $comparison = $response->json('comparison');
        $features   = collect($comparison)->pluck('feature')->toArray();

        $this->assertContains('api_calls', $features);
        $this->assertContains('storage_gb', $features);
        $this->assertContains('support', $features);
        $this->assertContains('advanced_analytics', $features);

        // Verify feature values
        $apiCallsRow = collect($comparison)->firstWhere('feature', 'api_calls');
        $this->assertEquals(1000, $apiCallsRow['basic']);
        $this->assertEquals(10000, $apiCallsRow['pro']);

        $analyticsRow = collect($comparison)->firstWhere('feature', 'advanced_analytics');
        $this->assertFalse($analyticsRow['basic']);
        $this->assertTrue($analyticsRow['pro']);
    }

    public function test_compare_withSpecificPlanIds_returnsOnlySpecifiedPlans(): void
    {
        // Given
        $plan1 = SubscriptionPlan::factory()->create([
            'name'       => 'Plan 1',
            'slug'       => 'plan1',
            'is_active'  => true,
            'sort_order' => 1,
            'features'   => ['feature1' => 'value1'],
        ]);

        $plan2 = SubscriptionPlan::factory()->create([
            'name'       => 'Plan 2',
            'slug'       => 'plan2',
            'is_active'  => true,
            'sort_order' => 2,
            'features'   => ['feature1' => 'value2', 'feature2' => 'value3'],
        ]);

        $plan3 = SubscriptionPlan::factory()->create([
            'name'      => 'Plan 3',
            'slug'      => 'plan3',
            'is_active' => true,
        ]);

        // When
        $response = $this->get("/api/subscription-plans/compare?plan_ids[]={$plan1->id}&plan_ids[]={$plan2->id}");

        // Then
        $response->assertOk();
        $response->assertJsonCount(2, 'plans');

        // Verify only specified plans are returned
        $planNames = collect($response->json('plans'))->pluck('name')->toArray();
        $this->assertContains('Plan 1', $planNames);
        $this->assertContains('Plan 2', $planNames);
        $this->assertNotContains('Plan 3', $planNames);

        // Verify comparison matrix only includes specified plans
        $comparison  = $response->json('comparison');
        $feature1Row = collect($comparison)->firstWhere('feature', 'feature1');
        $this->assertEquals('value1', $feature1Row['plan1']);
        $this->assertEquals('value2', $feature1Row['plan2']);
        $this->assertArrayNotHasKey('plan3', $feature1Row);
    }

    public function test_compare_withInvalidPlanIds_returnsValidationError(): void
    {
        // When
        $response = $this->getJson('/api/subscription-plans/compare?plan_ids[]=999999');

        // Then
        if ($response->status() !== 422) {
            $this->fail('Expected 422 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['plan_ids.0']);
    }

    public function test_compare_withEmptyFeatures_returnsEmptyComparison(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create([
            'features'  => null,
            'is_active' => true,
        ]);

        // When
        $response = $this->get('/api/subscription-plans/compare');

        // Then
        $response->assertOk();
        $response->assertJson([
            'comparison' => [],
        ]);
    }

    public function test_compare_withMixedFeatureStructures_handlesCorrectly(): void
    {
        // Given
        $plan1 = SubscriptionPlan::factory()->create([
            'name'      => 'Plan 1',
            'slug'      => 'plan1',
            'is_active' => true,
            'features'  => [
                'shared_feature' => 100,
                'plan1_only'     => 'special',
            ],
        ]);

        $plan2 = SubscriptionPlan::factory()->create([
            'name'      => 'Plan 2',
            'slug'      => 'plan2',
            'is_active' => true,
            'features'  => [
                'shared_feature' => 500,
                'plan2_only'     => true,
            ],
        ]);

        // When
        $response = $this->get('/api/subscription-plans/compare');

        // Then
        $response->assertOk();

        $comparison = $response->json('comparison');

        // Verify shared feature
        $sharedFeatureRow = collect($comparison)->firstWhere('feature', 'shared_feature');
        $this->assertEquals(100, $sharedFeatureRow['plan1']);
        $this->assertEquals(500, $sharedFeatureRow['plan2']);

        // Verify plan-specific features with defaults
        $plan1OnlyRow = collect($comparison)->firstWhere('feature', 'plan1_only');
        $this->assertEquals('special', $plan1OnlyRow['plan1']);
        $this->assertFalse($plan1OnlyRow['plan2']); // Default to false for missing features

        $plan2OnlyRow = collect($comparison)->firstWhere('feature', 'plan2_only');
        $this->assertFalse($plan2OnlyRow['plan1']); // Default to false for missing features
        $this->assertTrue($plan2OnlyRow['plan2']);
    }

    public function test_endpoints_requireAuthentication(): void
    {
        // Given - not authenticated
        $this->app['auth']->logout();

        // When/Then - All endpoints should require authentication
        $endpoints = [
            ['GET', '/api/subscription-plans'],
            ['GET', '/api/subscription-plans/1'],
            ['GET', '/api/subscription-plans/compare'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            if ($response->getStatusCode() !== 401) {
                $this->fail("Endpoint $method $endpoint should require authentication. Got {$response->getStatusCode()}: " . $response->getContent());
            }
            $this->assertEquals(401, $response->getStatusCode(), "Endpoint $method $endpoint should require authentication");
        }
    }
}
