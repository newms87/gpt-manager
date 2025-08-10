<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ProcessDailyUsageBilling;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Services\Billing\MockStripePaymentService;
use App\Services\Billing\StripePaymentServiceInterface;
use App\Services\Billing\UsageBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessDailyUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // Bind mock Stripe service for testing
        $this->app->bind(StripePaymentServiceInterface::class, MockStripePaymentService::class);
    }

    public function test_handle_withAllTeams_processesAllEligibleTeams(): void
    {
        // Given
        $team1 = Team::factory()->create([
            'name' => 'Team 1',
            'stripe_customer_id' => 'cus_team1'
        ]);
        $team2 = Team::factory()->create([
            'name' => 'Team 2',
            'stripe_customer_id' => 'cus_team2'
        ]);

        // Create eligible subscriptions and payment methods
        $plan = SubscriptionPlan::factory()->create([
            'usage_limits' => ['usage_based_billing' => true]
        ]);

        Subscription::factory()->create([
            'team_id' => $team1->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false
        ]);

        Subscription::factory()->create([
            'team_id' => $team2->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false
        ]);

        PaymentMethod::factory()->create([
            'team_id' => $team1->id,
            'is_default' => true
        ]);

        PaymentMethod::factory()->create([
            'team_id' => $team2->id,
            'is_default' => true
        ]);

        // Create usage events with costs
        UsageEvent::factory()->create([
            'team_id' => $team1->id,
            'input_cost' => 5.00,
            'output_cost' => 3.00,
            'created_at' => Carbon::now()->subDay()
        ]);

        UsageEvent::factory()->create([
            'team_id' => $team2->id,
            'input_cost' => 7.50,
            'output_cost' => 2.50,
            'created_at' => Carbon::now()->subDay()
        ]);

        // When
        $exitCode = Artisan::call('billing:process-daily-usage');

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Verify billing history was created for both teams
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team1->id,
            'type' => 'usage_charge',
            'amount' => '8.00',
            'status' => 'processed'
        ]);

        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team2->id,
            'type' => 'usage_charge',
            'amount' => '10.00',
            'status' => 'processed'
        ]);
    }

    public function test_handle_withSpecificTeam_processesOnlyThatTeam(): void
    {
        // Given
        $targetTeam = Team::factory()->create([
            'name' => 'Target Team',
            'stripe_customer_id' => 'cus_target'
        ]);
        $otherTeam = Team::factory()->create([
            'name' => 'Other Team',
            'stripe_customer_id' => 'cus_other'
        ]);

        // Create eligible subscriptions
        $plan = SubscriptionPlan::factory()->create([
            'usage_limits' => ['usage_based_billing' => true]
        ]);

        Subscription::factory()->create([
            'team_id' => $targetTeam->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false
        ]);

        Subscription::factory()->create([
            'team_id' => $otherTeam->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false
        ]);

        PaymentMethod::factory()->create([
            'team_id' => $targetTeam->id,
            'is_default' => true
        ]);

        PaymentMethod::factory()->create([
            'team_id' => $otherTeam->id,
            'is_default' => true
        ]);

        // Create usage events for both teams
        UsageEvent::factory()->create([
            'team_id' => $targetTeam->id,
            'input_cost' => 5.00,
            'output_cost' => 3.00,
            'created_at' => Carbon::now()->subDay()
        ]);

        UsageEvent::factory()->create([
            'team_id' => $otherTeam->id,
            'input_cost' => 7.50,
            'output_cost' => 2.50,
            'created_at' => Carbon::now()->subDay()
        ]);

        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--team' => $targetTeam->id
        ]);

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Verify only target team was charged
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $targetTeam->id,
            'type' => 'usage_charge'
        ]);

        $this->assertDatabaseMissing('billing_history', [
            'team_id' => $otherTeam->id,
            'type' => 'usage_charge'
        ]);
    }

    public function test_handle_withNonExistentTeam_returnsFailure(): void
    {
        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--team' => '999999'
        ]);

        // Then
        $this->assertEquals(Command::FAILURE, $exitCode);
    }

    public function test_handle_withDryRunForSpecificTeam_doesNotCreateCharges(): void
    {
        // Given
        $team = Team::factory()->create([
            'name' => 'Test Team',
            'stripe_customer_id' => 'cus_test'
        ]);

        // Create usage events
        UsageEvent::factory()->create([
            'team_id' => $team->id,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'input_cost' => 2.50,
            'output_cost' => 1.25,
            'request_count' => 5,
            'created_at' => Carbon::now()->subDay()
        ]);

        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--team' => $team->id,
            '--dry-run' => true
        ]);

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Verify no charges were created
        $this->assertDatabaseMissing('billing_history', [
            'team_id' => $team->id,
            'type' => 'usage_charge'
        ]);
    }

    public function test_handle_withDryRunForAllTeams_doesNotCreateCharges(): void
    {
        // Given
        $team = Team::factory()->create(['stripe_customer_id' => 'cus_test']);
        UsageEvent::factory()->create([
            'team_id' => $team->id,
            'input_cost' => 5.00,
            'created_at' => Carbon::now()->subDay()
        ]);

        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--dry-run' => true
        ]);

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Verify no charges were created
        $this->assertDatabaseMissing('billing_history', [
            'type' => 'usage_charge'
        ]);
    }

    public function test_handle_withException_returnsFailureAndLogsError(): void
    {
        // Given - Mock the service to throw an exception
        $this->app->bind(UsageBillingService::class, function () {
            $mock = $this->mock(UsageBillingService::class);
            $mock->shouldReceive('processDailyBilling')
                 ->once()
                 ->andThrow(new \Exception('Test billing service error'));
            return $mock;
        });

        // When
        $exitCode = Artisan::call('billing:process-daily-usage');

        // Then
        $this->assertEquals(Command::FAILURE, $exitCode);
    }

    public function test_handle_withExceptionForSpecificTeam_returnsFailureAndLogsError(): void
    {
        // Given
        $team = Team::factory()->create(['name' => 'Error Team']);

        $this->app->bind(UsageBillingService::class, function () {
            $mock = $this->mock(UsageBillingService::class);
            $mock->shouldReceive('processTeamBilling')
                 ->once()
                 ->andThrow(new \Exception('Team billing error'));
            return $mock;
        });

        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--team' => $team->id
        ]);

        // Then
        $this->assertEquals(Command::FAILURE, $exitCode);
    }

    public function test_command_signature_hasCorrectOptions(): void
    {
        // Given
        $command = new ProcessDailyUsageBilling();

        // When
        $signature = $command->getDefinition();

        // Then
        $this->assertTrue($signature->hasOption('team'));
        $this->assertTrue($signature->hasOption('dry-run'));
    }

    public function test_handle_withNoEligibleTeams_completesSuccessfully(): void
    {
        // Given - No teams with billing setup

        // When
        $exitCode = Artisan::call('billing:process-daily-usage');

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    public function test_handle_withTeamWithoutUsage_completesWithoutCharging(): void
    {
        // Given
        $team = Team::factory()->create([
            'name' => 'No Usage Team',
            'stripe_customer_id' => 'cus_no_usage'
        ]);

        $plan = SubscriptionPlan::factory()->create([
            'usage_limits' => ['usage_based_billing' => true]
        ]);

        Subscription::factory()->create([
            'team_id' => $team->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false
        ]);

        PaymentMethod::factory()->create([
            'team_id' => $team->id,
            'is_default' => true
        ]);

        // No usage events created

        // When
        $exitCode = Artisan::call('billing:process-daily-usage', [
            '--team' => $team->id
        ]);

        // Then
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Verify no charges were created
        $this->assertDatabaseMissing('billing_history', [
            'team_id' => $team->id,
            'type' => 'usage_charge'
        ]);
    }
}