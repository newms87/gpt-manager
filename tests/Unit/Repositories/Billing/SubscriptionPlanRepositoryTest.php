<?php

namespace Tests\Unit\Repositories\Billing;

use App\Models\Billing\SubscriptionPlan;
use App\Repositories\Billing\SubscriptionPlanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlanRepository $subscriptionPlanRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->subscriptionPlanRepository = new SubscriptionPlanRepository();
    }

    public function test_query_returnsOrderedResults(): void
    {
        // Given
        $plan3 = SubscriptionPlan::factory()->create(['sort_order' => 30]);
        $plan1 = SubscriptionPlan::factory()->create(['sort_order' => 10]);
        $plan2 = SubscriptionPlan::factory()->create(['sort_order' => 20]);

        // When
        $results = $this->subscriptionPlanRepository->query()->get();

        // Then
        $this->assertCount(3, $results);
        // Verify they are ordered by the ordered() scope (assuming it orders by sort_order)
        $this->assertEquals($plan1->id, $results[0]->id);
        $this->assertEquals($plan2->id, $results[1]->id);
        $this->assertEquals($plan3->id, $results[2]->id);
    }

    public function test_applyAction_create_withValidData_createsPlan(): void
    {
        // Given
        $data = [
            'name' => 'Professional Plan',
            'slug' => 'professional',
            'description' => 'For professional users',
            'monthly_price' => 49.99,
            'yearly_price' => 499.99,
            'stripe_price_id' => 'price_test123',
            'features' => ['feature1', 'feature2'],
            'usage_limits' => ['api_calls' => 10000],
            'is_active' => true,
            'sort_order' => 20,
        ];

        // When
        $result = $this->subscriptionPlanRepository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(SubscriptionPlan::class, $result);
        $this->assertEquals('Professional Plan', $result->name);
        $this->assertEquals('professional', $result->slug);
        $this->assertEquals(49.99, $result->monthly_price);
        $this->assertEquals(499.99, $result->yearly_price);
        $this->assertEquals('price_test123', $result->stripe_price_id);
        $this->assertTrue($result->is_active);
        $this->assertEquals(['feature1', 'feature2'], $result->features);
        $this->assertEquals(['api_calls' => 10000], $result->usage_limits);
        
        // Verify database record
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Professional Plan',
            'slug' => 'professional',
            'monthly_price' => 49.99,
            'yearly_price' => 499.99,
        ]);
    }

    public function test_applyAction_update_withValidPlan_updatesPlan(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Basic Plan',
            'monthly_price' => 19.99,
            'is_active' => true,
        ]);
        $updateData = [
            'name' => 'Updated Basic Plan',
            'monthly_price' => 24.99,
            'description' => 'Updated description',
        ];

        // When
        $result = $this->subscriptionPlanRepository->applyAction('update', $plan, $updateData);

        // Then
        $this->assertEquals('Updated Basic Plan', $result->name);
        $this->assertEquals(24.99, $result->monthly_price);
        $this->assertEquals('Updated description', $result->description);
        
        // Verify database was updated
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'name' => 'Updated Basic Plan',
            'monthly_price' => 24.99,
            'description' => 'Updated description',
        ]);
    }

    public function test_applyAction_activate_withInactivePlan_activatesPlan(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

        // When
        $result = $this->subscriptionPlanRepository->applyAction('activate', $plan);

        // Then
        $this->assertTrue($result->is_active);
        
        // Verify database was updated
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'is_active' => true,
        ]);
    }

    public function test_applyAction_activate_withAlreadyActivePlan_remainsActive(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        // When
        $result = $this->subscriptionPlanRepository->applyAction('activate', $plan);

        // Then
        $this->assertTrue($result->is_active);
    }

    public function test_applyAction_deactivate_withActivePlan_deactivatesPlan(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        // When
        $result = $this->subscriptionPlanRepository->applyAction('deactivate', $plan);

        // Then
        $this->assertFalse($result->is_active);
        
        // Verify database was updated
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'is_active' => false,
        ]);
    }

    public function test_applyAction_deactivate_withAlreadyInactivePlan_remainsInactive(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

        // When
        $result = $this->subscriptionPlanRepository->applyAction('deactivate', $plan);

        // Then
        $this->assertFalse($result->is_active);
    }

    public function test_getActivePlans_returnsOnlyActivePlans(): void
    {
        // Given
        $activePlan1 = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $activePlan2 = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'is_active' => false,
            'sort_order' => 15,
        ]);

        // When
        $result = $this->subscriptionPlanRepository->getActivePlans();

        // Then
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Verify only active plans are returned
        $planIds = array_column($result, 'id');
        $this->assertContains($activePlan1->id, $planIds);
        $this->assertContains($activePlan2->id, $planIds);
        $this->assertNotContains($inactivePlan->id, $planIds);
    }

    public function test_getAvailablePlans_withIncludeInactiveTrue_returnsAllPlans(): void
    {
        // Given
        $activePlan = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'is_active' => false,
            'sort_order' => 20,
        ]);

        // When
        $result = $this->subscriptionPlanRepository->getAvailablePlans(true);

        // Then
        $this->assertCount(2, $result);
        $planIds = $result->pluck('id')->toArray();
        $this->assertContains($activePlan->id, $planIds);
        $this->assertContains($inactivePlan->id, $planIds);
    }

    public function test_getAvailablePlans_withIncludeInactiveFalse_returnsOnlyActivePlans(): void
    {
        // Given
        $activePlan = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'is_active' => false,
            'sort_order' => 20,
        ]);

        // When
        $result = $this->subscriptionPlanRepository->getAvailablePlans(false);

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals($activePlan->id, $result->first()->id);
    }

    public function test_getAvailablePlans_withDefaultParameter_returnsOnlyActivePlans(): void
    {
        // Given
        $activePlan = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $inactivePlan = SubscriptionPlan::factory()->create([
            'is_active' => false,
            'sort_order' => 20,
        ]);

        // When
        $result = $this->subscriptionPlanRepository->getAvailablePlans(); // Default is false

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals($activePlan->id, $result->first()->id);
    }

    public function test_getAvailablePlans_resultsAreOrderedBySortOrder(): void
    {
        // Given
        $plan3 = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 30,
            'name' => 'Plan C',
        ]);
        $plan1 = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 10,
            'name' => 'Plan A',
        ]);
        $plan2 = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'sort_order' => 20,
            'name' => 'Plan B',
        ]);

        // When
        $result = $this->subscriptionPlanRepository->getAvailablePlans();

        // Then
        $this->assertCount(3, $result);
        $this->assertEquals($plan1->id, $result[0]->id);
        $this->assertEquals($plan2->id, $result[1]->id);
        $this->assertEquals($plan3->id, $result[2]->id);
    }

    public function test_createPlan_withComplexData_handlesAllFields(): void
    {
        // Given
        $data = [
            'name' => 'Enterprise Plan',
            'slug' => 'enterprise',
            'description' => 'For large organizations',
            'monthly_price' => 199.99,
            'yearly_price' => 1999.99,
            'stripe_price_id' => 'price_enterprise123',
            'features' => [
                'unlimited_api_calls',
                'priority_support',
                'custom_integrations',
                'dedicated_account_manager'
            ],
            'usage_limits' => [
                'api_calls' => -1, // Unlimited
                'storage' => 1000000, // 1TB
                'users' => 100,
                'usage_based_billing' => true
            ],
            'is_active' => true,
            'sort_order' => 100,
        ];

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->subscriptionPlanRepository, 'createPlan');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->subscriptionPlanRepository, $data);

        // Then
        $this->assertInstanceOf(SubscriptionPlan::class, $result);
        $this->assertEquals('Enterprise Plan', $result->name);
        $this->assertEquals('enterprise', $result->slug);
        $this->assertEquals(199.99, $result->monthly_price);
        $this->assertEquals(1999.99, $result->yearly_price);
        $this->assertEquals(['unlimited_api_calls', 'priority_support', 'custom_integrations', 'dedicated_account_manager'], $result->features);
        $this->assertEquals([
            'api_calls' => -1,
            'storage' => 1000000,
            'users' => 100,
            'usage_based_billing' => true
        ], $result->usage_limits);
        $this->assertTrue($result->is_active);
        $this->assertEquals(100, $result->sort_order);
    }

    public function test_updatePlan_preservesExistingData_whenNotUpdated(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Basic Plan',
            'monthly_price' => 19.99,
            'yearly_price' => 199.99,
            'features' => ['basic_feature'],
            'usage_limits' => ['api_calls' => 1000],
            'is_active' => true,
        ]);
        $partialUpdateData = [
            'monthly_price' => 24.99, // Only update monthly price
        ];

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->subscriptionPlanRepository, 'updatePlan');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->subscriptionPlanRepository, $plan, $partialUpdateData);

        // Then
        $this->assertEquals('Basic Plan', $result->name); // Unchanged
        $this->assertEquals(24.99, $result->monthly_price); // Updated
        $this->assertEquals(199.99, $result->yearly_price); // Unchanged
        $this->assertEquals(['basic_feature'], $result->features); // Unchanged
        $this->assertEquals(['api_calls' => 1000], $result->usage_limits); // Unchanged
        $this->assertTrue($result->is_active); // Unchanged
    }

    public function test_activatePlan_returnsRefreshedModel(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->subscriptionPlanRepository, 'activatePlan');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->subscriptionPlanRepository, $plan);

        // Then
        $this->assertInstanceOf(SubscriptionPlan::class, $result);
        $this->assertTrue($result->is_active);
        
        // Verify it's a fresh model (not the same instance)
        $this->assertNotSame($plan, $result);
    }

    public function test_deactivatePlan_returnsRefreshedModel(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->subscriptionPlanRepository, 'deactivatePlan');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->subscriptionPlanRepository, $plan);

        // Then
        $this->assertInstanceOf(SubscriptionPlan::class, $result);
        $this->assertFalse($result->is_active);
        
        // Verify it's a fresh model (not the same instance)
        $this->assertNotSame($plan, $result);
    }
}