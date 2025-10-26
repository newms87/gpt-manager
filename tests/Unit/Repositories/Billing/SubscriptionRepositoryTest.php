<?php

namespace Tests\Unit\Repositories\Billing;

use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Repositories\Billing\SubscriptionRepository;
use Carbon\Carbon;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class SubscriptionRepositoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private SubscriptionRepository $subscriptionRepository;

    private Team $team;

    private Team $differentTeam;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->subscriptionRepository = new SubscriptionRepository();
        $this->team                   = $this->user->currentTeam;
        $this->differentTeam          = Team::factory()->create();
    }

    public function test_query_withAuthenticatedUser_returnsOnlyTeamSubscriptions(): void
    {
        // Given
        $teamSubscription      = Subscription::factory()->create([
            'team_id' => $this->team->id,
        ]);
        $otherTeamSubscription = Subscription::factory()->create([
            'team_id' => $this->differentTeam->id,
        ]);

        // When
        $results = $this->subscriptionRepository->query()->get();

        // Then
        $this->assertCount(1, $results);
        $this->assertEquals($teamSubscription->id, $results->first()->id);
        $this->assertFalse($results->contains('id', $otherTeamSubscription->id));

        // Verify relationships are loaded
        $this->assertTrue($results->first()->relationLoaded('subscriptionPlan'));
        $this->assertTrue($results->first()->relationLoaded('team'));
    }

    public function test_applyAction_create_withValidData_createsSubscription(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create();
        $data = [
            'subscription_plan_id'   => $plan->id,
            'stripe_subscription_id' => 'sub_test123',
            'status'                 => 'active',
            'billing_cycle'          => 'monthly',
            'monthly_amount'         => 29.99,
            'yearly_amount'          => 299.99,
            'current_period_start'   => Carbon::now(),
            'current_period_end'     => Carbon::now()->addMonth(),
        ];

        // When
        $result = $this->subscriptionRepository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($this->team->id, $result->team_id);
        $this->assertEquals($plan->id, $result->subscription_plan_id);
        $this->assertEquals('sub_test123', $result->stripe_subscription_id);
        $this->assertEquals('active', $result->status);

        // Verify relationships are loaded
        $this->assertTrue($result->relationLoaded('subscriptionPlan'));
        $this->assertTrue($result->relationLoaded('team'));

        // Verify database record
        $this->assertDatabaseHas('subscriptions', [
            'team_id'                => $this->team->id,
            'subscription_plan_id'   => $plan->id,
            'stripe_subscription_id' => 'sub_test123',
        ]);
    }

    public function test_applyAction_create_withoutTeamContext_throwsValidationError(): void
    {
        // Given
        // Remove user from all teams to ensure no team context
        $this->user->teams()->detach();
        $this->user->currentTeam = null;
        $this->user->save();

        // Re-authenticate the user without teams
        $this->actingAs($this->user->fresh());

        $data = ['subscription_plan_id' => 1];

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No team context available');

        // When
        $this->subscriptionRepository->applyAction('create', null, $data);
    }

    public function test_applyAction_update_withValidSubscription_updatesSubscription(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status'  => 'active',
        ]);
        $updateData   = [
            'status'             => 'past_due',
            'current_period_end' => Carbon::now()->addMonth()->startOfSecond(),
        ];

        // When
        $result = $this->subscriptionRepository->applyAction('update', $subscription, $updateData);

        // Then
        $this->assertEquals('past_due', $result->status);
        $this->assertEquals($updateData['current_period_end']->format('Y-m-d H:i:s'), $result->current_period_end->format('Y-m-d H:i:s'));

        // Verify database was updated
        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => 'past_due',
        ]);
    }

    public function test_applyAction_update_withDifferentTeam_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->differentTeam->id,
        ]);
        $updateData   = ['status' => 'canceled'];

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this subscription');

        // When
        $this->subscriptionRepository->applyAction('update', $subscription, $updateData);
    }

    public function test_applyAction_changePlan_withValidData_updatesSubscriptionPlan(): void
    {
        // Given
        $oldPlan      = SubscriptionPlan::factory()->create([
            'is_active'     => true,
            'monthly_price' => 29.99,
            'yearly_price'  => 299.99,
        ]);
        $newPlan      = SubscriptionPlan::factory()->create([
            'is_active'     => true,
            'monthly_price' => 49.99,
            'yearly_price'  => 499.99,
        ]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'subscription_plan_id' => $oldPlan->id,
        ]);
        $data         = ['subscription_plan_id' => $newPlan->id];

        // When
        $result = $this->subscriptionRepository->applyAction('change-plan', $subscription, $data);

        // Then
        $this->assertEquals($newPlan->id, $result->subscription_plan_id);
        $this->assertEquals(49.99, $result->monthly_amount);
        $this->assertEquals(499.99, $result->yearly_amount);

        // Verify database was updated
        $this->assertDatabaseHas('subscriptions', [
            'id'                   => $subscription->id,
            'subscription_plan_id' => $newPlan->id,
            'monthly_amount'       => 49.99,
            'yearly_amount'        => 499.99,
        ]);
    }

    public function test_applyAction_changePlan_withInactivePlan_throwsValidationError(): void
    {
        // Given
        $activePlan   = SubscriptionPlan::factory()->create(['is_active' => true]);
        $inactivePlan = SubscriptionPlan::factory()->create(['is_active' => false]);
        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'subscription_plan_id' => $activePlan->id,
        ]);
        $data         = ['subscription_plan_id' => $inactivePlan->id];

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid or inactive subscription plan');

        // When
        $this->subscriptionRepository->applyAction('change-plan', $subscription, $data);
    }

    public function test_applyAction_changePlan_withMissingPlanId_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create(['team_id' => $this->team->id]);
        $data         = []; // Missing subscription_plan_id

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('New subscription plan ID is required');

        // When
        $this->subscriptionRepository->applyAction('change-plan', $subscription, $data);
    }

    public function test_applyAction_cancel_withActiveSubscription_cancelsSubscription(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id'     => $this->team->id,
            'status'      => 'active',
            'canceled_at' => null,
        ]);
        $endsAt       = Carbon::now()->addMonth()->startOfSecond();
        $data         = ['ends_at' => $endsAt];

        // When
        $result = $this->subscriptionRepository->applyAction('cancel', $subscription, $data);

        // Then
        $this->assertEquals('canceled', $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertEquals($endsAt->format('Y-m-d H:i:s'), $result->ends_at->format('Y-m-d H:i:s'));

        // Verify database was updated
        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => 'canceled',
        ]);
    }

    public function test_applyAction_cancel_withAlreadyCanceled_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id'     => $this->team->id,
            'canceled_at' => Carbon::now(),
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Subscription is already canceled');

        // When
        $this->subscriptionRepository->applyAction('cancel', $subscription, []);
    }

    public function test_applyAction_reactivate_withCanceledSubscription_reactivatesSubscription(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id'     => $this->team->id,
            'status'      => 'canceled',
            'canceled_at' => Carbon::now()->subDays(5),
            'ends_at'     => Carbon::now()->addDays(25), // Not yet expired
        ]);

        // When
        $result = $this->subscriptionRepository->applyAction('reactivate', $subscription);

        // Then
        $this->assertEquals('active', $result->status);
        $this->assertNull($result->canceled_at);
        $this->assertNull($result->ends_at);

        // Verify database was updated
        $this->assertDatabaseHas('subscriptions', [
            'id'          => $subscription->id,
            'status'      => 'active',
            'canceled_at' => null,
            'ends_at'     => null,
        ]);
    }

    public function test_applyAction_reactivate_withActiveSubscription_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id'     => $this->team->id,
            'status'      => 'active',
            'canceled_at' => null,
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Subscription is not canceled');

        // When
        $this->subscriptionRepository->applyAction('reactivate', $subscription);
    }

    public function test_applyAction_reactivate_withExpiredSubscription_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id'     => $this->team->id,
            'status'      => 'canceled',
            'canceled_at' => Carbon::now()->subMonths(2),
            'ends_at'     => Carbon::now()->subMonth(), // Already expired
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Cannot reactivate expired subscription');

        // When
        $this->subscriptionRepository->applyAction('reactivate', $subscription);
    }

    public function test_getActiveSubscriptionForTeam_withActiveSubscription_returnsSubscription(): void
    {
        // Given
        $activeSubscription   = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status'  => 'active',
        ]);
        $canceledSubscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status'  => 'canceled',
        ]);

        // When
        $result = $this->subscriptionRepository->getActiveSubscriptionForTeam();

        // Then
        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($activeSubscription->id, $result->id);
        $this->assertEquals('active', $result->status);
    }

    public function test_getActiveSubscriptionForTeam_withoutActiveSubscription_returnsNull(): void
    {
        // Given
        Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status'  => 'canceled',
        ]);

        // When
        $result = $this->subscriptionRepository->getActiveSubscriptionForTeam();

        // Then
        $this->assertNull($result);
    }

    public function test_getActiveSubscription_withSpecificTeamId_returnsSubscription(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->differentTeam->id,
            'status'  => 'active',
        ]);

        // When
        $result = $this->subscriptionRepository->getActiveSubscription($this->differentTeam->id);

        // Then
        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($subscription->id, $result->id);
        $this->assertEquals($this->differentTeam->id, $result->team_id);
    }

    public function test_validateOwnership_withOwnSubscription_doesNotThrowException(): void
    {
        // Given
        $subscription = Subscription::factory()->create(['team_id' => $this->team->id]);
        $repository   = new SubscriptionRepository();

        // When & Then - Should not throw exception
        $method = new \ReflectionMethod($repository, 'validateOwnership');
        $method->setAccessible(true);
        $method->invoke($repository, $subscription);

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    public function test_validateOwnership_withDifferentTeamSubscription_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create(['team_id' => $this->differentTeam->id]);
        $repository   = new SubscriptionRepository();

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this subscription');

        // When
        $method = new \ReflectionMethod($repository, 'validateOwnership');
        $method->setAccessible(true);
        $method->invoke($repository, $subscription);
    }

    public function test_validateTeamOwnership_withValidTeamContext_doesNotThrowException(): void
    {
        // Given
        $repository = new SubscriptionRepository();

        // When & Then - Should not throw exception
        $method = new \ReflectionMethod($repository, 'validateTeamOwnership');
        $method->setAccessible(true);
        $method->invoke($repository);

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    public function test_validateTeamOwnership_withoutTeamContext_throwsValidationError(): void
    {
        // Given
        // Remove user from all teams to ensure no team context
        $this->user->teams()->detach();
        $this->user->currentTeam = null;
        $this->user->save();

        // Re-authenticate the user without teams
        $this->actingAs($this->user->fresh());

        $repository = new SubscriptionRepository();

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No team context available');

        // When
        $method = new \ReflectionMethod($repository, 'validateTeamOwnership');
        $method->setAccessible(true);
        $method->invoke($repository);
    }
}
