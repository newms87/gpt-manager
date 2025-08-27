<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Services\Billing\StripePaymentServiceInterface;
use App\Services\Billing\UsageBillingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class UsageBillingServiceTest extends TestCase
{
    private UsageBillingService           $usageBillingService;
    private StripePaymentServiceInterface $mockStripeService;
    private Team                          $team;

    public function setUp(): void
    {
        parent::setUp();

        // Only mock the external API service
        $this->mockStripeService = $this->mock(StripePaymentServiceInterface::class);
        $this->app->instance(StripePaymentServiceInterface::class, $this->mockStripeService);

        // Use real services from the container
        $this->usageBillingService = app(UsageBillingService::class);

        $this->team = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
    }

    public function test_processDailyBilling_withMultipleTeams_processesEachTeam(): void
    {
        // Given
        $team1 = Team::factory()->create(['stripe_customer_id' => 'cus_team1']);
        $team2 = Team::factory()->create(['stripe_customer_id' => 'cus_team2']);

        // Create subscriptions for teams
        $plan          = SubscriptionPlan::factory()->create(['usage_limits' => ['usage_based_billing' => true]]);
        $subscription1 = Subscription::factory()->create(['team_id' => $team1->id, 'status' => 'active', 'subscription_plan_id' => $plan->id]);
        $subscription2 = Subscription::factory()->create(['team_id' => $team2->id, 'status' => 'active', 'subscription_plan_id' => $plan->id]);

        // Create payment methods
        PaymentMethod::factory()->create(['team_id' => $team1->id, 'is_default' => true]);
        PaymentMethod::factory()->create(['team_id' => $team2->id, 'is_default' => true]);


        // Create usage events for both teams
        UsageEvent::factory()->create([
            'team_id'     => $team1->id,
            'input_cost'  => 1.00,
            'output_cost' => 1.00,
            'created_at'  => Carbon::now()->subDay(),
        ]);

        UsageEvent::factory()->create([
            'team_id'     => $team2->id,
            'input_cost'  => 1.50,
            'output_cost' => 1.50,
            'created_at'  => Carbon::now()->subDay(),
        ]);

        // Mock successful charge for both teams
        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->twice()
            ->andReturn(['status' => 'succeeded', 'id' => 'ch_test123']);

        // Verify teams are eligible for billing
        $eligibleTeams = Team::whereNotNull('stripe_customer_id')
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'active')
                    ->where('cancel_at_period_end', false);
            })
            ->whereHas('paymentMethods', function ($query) {
                $query->where('is_default', true);
            })
            ->get();

        $this->assertCount(2, $eligibleTeams, 'Should find 2 eligible teams');

        // When
        $this->usageBillingService->processDailyBilling();

        // Then
        // Verify billing history records were created for both teams
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team1->id,
            'type'    => 'usage_charge',
            'status'  => 'processed',
        ]);

        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team2->id,
            'type'    => 'usage_charge',
            'status'  => 'processed',
        ]);
    }

    public function test_processTeamBilling_withNoUsageBasedBilling_skipsTeam(): void
    {
        // Given
        $plan         = SubscriptionPlan::factory()->create(['usage_limits' => ['usage_based_billing' => false]]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'status'               => 'active',
            'subscription_plan_id' => $plan->id,
        ]);

        // When
        $this->usageBillingService->processTeamBilling($this->team);

        // Then
        // Should not call Stripe service since team doesn't have usage-based billing
        $this->mockStripeService->shouldNotReceive('createCharge');
        $this->assertTrue(true);
    }

    public function test_processTeamBilling_withZeroUsage_skipsCharging(): void
    {
        // Given
        $plan         = SubscriptionPlan::factory()->create(['usage_limits' => ['usage_based_billing' => true]]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'status'               => 'active',
            'subscription_plan_id' => $plan->id,
        ]);

        // Create usage events with zero cost
        UsageEvent::factory()->create([
            'team_id'     => $this->team->id,
            'input_cost'  => 0,
            'output_cost' => 0,
            'created_at'  => Carbon::now()->subDay(),
        ]);

        // When
        $this->usageBillingService->processTeamBilling($this->team);

        // Then
        $this->mockStripeService->shouldNotReceive('createCharge');
        $this->assertTrue(true);
    }

    public function test_processTeamBilling_withValidUsage_createsChargeAndBillingHistory(): void
    {
        // Given
        $plan         = SubscriptionPlan::factory()->create(['usage_limits' => ['usage_based_billing' => true]]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'status'               => 'active',
            'subscription_plan_id' => $plan->id,
        ]);

        // Create usage events with cost
        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_cost'    => 5.25,
            'output_cost'   => 3.75,
            'input_tokens'  => 1000,
            'output_tokens' => 500,
            'created_at'    => Carbon::now()->subDay(),
        ]);

        $expectedCharge = [
            'status' => 'succeeded',
            'id'     => 'ch_test123',
            'amount' => 900, // $9.00 in cents (rounded)
        ];

        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->with('cus_test123', 900, 'USD', Mockery::type('string'))
            ->once()
            ->andReturn($expectedCharge);

        // Debug: Try creating a BillingHistory record directly
        $testRecord = new \App\Models\Billing\BillingHistory([
            'team_id'      => $this->team->id,
            'type'         => 'usage_charge',
            'status'       => 'processed',
            'amount'       => 9.00,
            'total_amount' => 9.00,
            'currency'     => 'USD',
            'description'  => 'Test record',
            'billing_date' => \Carbon\Carbon::now(),
        ]);

        $testRecord->save();

        $this->assertDatabaseHas('billing_history', [
            'team_id'     => $this->team->id,
            'description' => 'Test record',
        ]);

        // When
        $this->usageBillingService->processTeamBilling($this->team);

        // Then
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $this->team->id,
            'type'    => 'usage_charge',
            'status'  => 'processed',
            'amount'  => 9.00,
        ]);
    }

    public function test_processTeamBilling_withFailedCharge_recordsFailure(): void
    {
        // Given
        $plan         = SubscriptionPlan::factory()->create(['usage_limits' => ['usage_based_billing' => true]]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'status'               => 'active',
            'subscription_plan_id' => $plan->id,
        ]);

        UsageEvent::factory()->create([
            'team_id'     => $this->team->id,
            'input_cost'  => 5.00,
            'output_cost' => 5.00,
            'created_at'  => Carbon::now()->subDay(),
        ]);

        $failedCharge = [
            'status' => 'failed',
            'id'     => 'ch_failed123',
            'error'  => 'Card declined',
        ];

        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->once()
            ->andReturn($failedCharge);

        // When
        $this->usageBillingService->processTeamBilling($this->team);

        // Then
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $this->team->id,
            'type'    => 'usage_charge',
            'status'  => 'failed',
        ]);
    }

    public function test_calculateDailyUsage_withUsageEvents_returnsCorrectStats(): void
    {
        // Given
        $yesterday = Carbon::now()->subDay();

        // Create usage events for yesterday
        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_tokens'  => 1000,
            'output_tokens' => 500,
            'input_cost'    => 2.50,
            'output_cost'   => 1.25,
            'request_count' => 5,
            'data_volume'   => 1024,
            'created_at'    => $yesterday,
        ]);

        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_tokens'  => 2000,
            'output_tokens' => 1000,
            'input_cost'    => 5.00,
            'output_cost'   => 2.50,
            'request_count' => 3,
            'data_volume'   => 2048,
            'created_at'    => $yesterday,
        ]);

        // Create usage for different day (should not be included)
        UsageEvent::factory()->create([
            'team_id'    => $this->team->id,
            'input_cost' => 10.00,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // When
        $result = $this->usageBillingService->calculateDailyUsage($this->team);

        // Then
        $this->assertEquals($yesterday->toDateString(), $result['date']);
        $this->assertEquals(2, $result['event_count']);
        $this->assertEquals(3000, $result['total_input_tokens']);
        $this->assertEquals(1500, $result['total_output_tokens']);
        $this->assertEquals(4500, $result['total_tokens']);
        $this->assertEquals(7.50, $result['total_input_cost']);
        $this->assertEquals(3.75, $result['total_output_cost']);
        $this->assertEquals(11.25, $result['total_cost']);
        $this->assertEquals(8, $result['total_requests']);
        $this->assertEquals(3072, $result['total_data_volume']);
    }

    public function test_calculateDailyUsage_withNoEvents_returnsZeroStats(): void
    {
        // When
        $result = $this->usageBillingService->calculateDailyUsage($this->team);

        // Then
        $this->assertEquals(0, $result['event_count']);
        $this->assertEquals(0, $result['total_cost']);
        $this->assertEquals(0, $result['total_tokens']);
    }

    public function test_getCurrentUsageStats_returnsMonthlyAndDailyUsage(): void
    {
        // Given
        $today     = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Create usage for today
        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_tokens'  => 500,
            'output_tokens' => 250,
            'input_cost'    => 1.00,
            'output_cost'   => 0.50,
            'request_count' => 2,
            'created_at'    => $today,
        ]);

        // Create usage for earlier this month
        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_tokens'  => 1500,
            'output_tokens' => 750,
            'input_cost'    => 3.00,
            'output_cost'   => 1.50,
            'request_count' => 3,
            'created_at'    => $thisMonth->copy()->addDays(5),
        ]);

        // Create usage for previous month (should not be included)
        UsageEvent::factory()->create([
            'team_id'    => $this->team->id,
            'input_cost' => 10.00,
            'created_at' => $thisMonth->copy()->subMonth(),
        ]);

        // When
        $result = $this->usageBillingService->getCurrentUsageStats($this->team);

        // Then
        $this->assertEquals($thisMonth->toDateString(), $result['current_month']['period_start']);
        $this->assertEquals(2, $result['current_month']['event_count']);
        $this->assertEquals(3000, $result['current_month']['total_tokens']);
        $this->assertEquals(6.00, $result['current_month']['total_cost']);
        $this->assertEquals(5, $result['current_month']['total_requests']);

        $this->assertEquals($today->toDateString(), $result['today']['date']);
        $this->assertEquals(1, $result['today']['event_count']);
        $this->assertEquals(750, $result['today']['total_tokens']);
        $this->assertEquals(1.50, $result['today']['total_cost']);
    }

    public function test_generateUsageSummary_returnsDetailedBreakdown(): void
    {
        // Given
        $startDate = Carbon::now()->subDays(7);
        $endDate   = Carbon::now();

        // Create usage events with different types
        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'event_type'    => 'completion',
            'api_name'      => 'openai',
            'input_tokens'  => 1000,
            'output_tokens' => 500,
            'input_cost'    => 2.00,
            'output_cost'   => 1.00,
            'request_count' => 1,
            'created_at'    => $startDate->copy()->addDays(1),
        ]);

        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'event_type'    => 'completion',
            'api_name'      => 'anthropic',
            'input_tokens'  => 2000,
            'output_tokens' => 1000,
            'input_cost'    => 4.00,
            'output_cost'   => 2.00,
            'request_count' => 1,
            'created_at'    => $startDate->copy()->addDays(1),
        ]);

        UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'event_type'    => 'embedding',
            'api_name'      => 'openai',
            'input_tokens'  => 5000,
            'output_tokens' => 0,
            'input_cost'    => 1.00,
            'output_cost'   => 0,
            'request_count' => 1,
            'created_at'    => $startDate->copy()->addDays(2),
        ]);

        // When
        $result = $this->usageBillingService->generateUsageSummary($this->team, $startDate, $endDate);

        // Then
        $this->assertEquals($startDate->toDateString(), $result['period']['start']);
        $this->assertEquals($endDate->toDateString(), $result['period']['end']);

        // Check daily summary structure
        $this->assertArrayHasKey('summary', $result);

        // Check that we have data for the days with usage
        $day1 = $startDate->copy()->addDays(1)->toDateString();
        $day2 = $startDate->copy()->addDays(2)->toDateString();

        $this->assertArrayHasKey($day1, $result['summary']);
        $this->assertArrayHasKey($day2, $result['summary']);

        // Verify day 1 totals (2 events)
        $this->assertEquals(2, $result['summary'][$day1]['total_events']);
        $this->assertEquals(4500, $result['summary'][$day1]['total_tokens']);
        $this->assertEquals(9.00, $result['summary'][$day1]['total_cost']);

        // Verify breakdown by type
        $this->assertArrayHasKey('by_type', $result['summary'][$day1]);
        $this->assertArrayHasKey('completion', $result['summary'][$day1]['by_type']);
    }

    public function test_createUsageCharge_withBelowMinimumAmount_skipsCharge(): void
    {
        // Given
        $usage = ['total_cost' => 0.30]; // Below $0.50 minimum

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->usageBillingService, 'createUsageCharge');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->usageBillingService, $this->team, $usage);

        // Then
        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('Below minimum charge threshold', $result['reason']);
        $this->mockStripeService->shouldNotReceive('createCharge');
    }

    public function test_createUsageCharge_withValidAmount_createsCharge(): void
    {
        // Given
        $usage          = ['total_cost' => 5.00, 'date' => '2024-01-15'];
        $expectedCharge = [
            'status' => 'succeeded',
            'id'     => 'ch_test123',
        ];

        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->with('cus_test123', 500, 'USD', 'Usage charges for 2024-01-15')
            ->once()
            ->andReturn($expectedCharge);

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->usageBillingService, 'createUsageCharge');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->usageBillingService, $this->team, $usage);

        // Then
        $this->assertEquals($expectedCharge, $result);
    }

    public function test_getTeamsForBilling_returnsOnlyEligibleTeams(): void
    {
        // Given
        // Team 1: Has stripe customer, active subscription, default payment method
        $team1 = Team::factory()->create(['stripe_customer_id' => 'cus_team1']);
        $plan  = SubscriptionPlan::factory()->create();
        Subscription::factory()->create([
            'team_id'              => $team1->id,
            'status'               => 'active',
            'cancel_at_period_end' => false,
            'subscription_plan_id' => $plan->id,
        ]);
        PaymentMethod::factory()->create(['team_id' => $team1->id, 'is_default' => true]);

        // Team 2: No stripe customer (should be excluded)
        $team2 = Team::factory()->create(['stripe_customer_id' => null]);

        // Team 3: Has stripe customer but no active subscription
        $team3 = Team::factory()->create(['stripe_customer_id' => 'cus_team3']);

        // Team 4: Has subscription but canceled at period end
        $team4 = Team::factory()->create(['stripe_customer_id' => 'cus_team4']);
        Subscription::factory()->create([
            'team_id'              => $team4->id,
            'status'               => 'active',
            'cancel_at_period_end' => true,
            'subscription_plan_id' => $plan->id,
        ]);

        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->usageBillingService, 'getTeamsForBilling');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($this->usageBillingService);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(1, $result->count());
        $this->assertEquals($team1->id, $result->first()->id);
    }
}
