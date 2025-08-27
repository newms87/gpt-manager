<?php

namespace Tests\Integration\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripePaymentServiceInterface;
use App\Services\Billing\UsageBillingService;
use Carbon\Carbon;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class BillingWorkflowIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private BillingService                $billingService;
    private UsageBillingService           $usageBillingService;
    private StripePaymentServiceInterface $mockStripeService;
    private Team                          $team;
    private SubscriptionPlan              $plan;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Mock only the external Stripe API service
        $this->mockStripeService = $this->mock(StripePaymentServiceInterface::class);

        // Use real services from the container
        $this->billingService      = app(BillingService::class);
        $this->usageBillingService = app(UsageBillingService::class);

        $this->team = $this->user->currentTeam;
        $this->plan = SubscriptionPlan::factory()->create([
            'is_active'       => true,
            'monthly_price'   => 29.99,
            'yearly_price'    => 299.99,
            'stripe_price_id' => 'price_test123',
            'usage_limits'    => ['usage_based_billing' => true],
        ]);
    }

    public function test_completeTeamBillingSetup_workflow_createsAllRequiredRecords(): void
    {
        // Given - Mock Stripe responses
        $stripeCustomer      = ['id' => 'cus_test123', 'email' => 'test@example.com'];
        $setupIntent         = [
            'id'            => 'seti_test123',
            'client_secret' => 'seti_test123_secret',
            'status'        => 'requires_payment_method',
        ];
        $confirmSetupIntent  = [
            'status'         => 'succeeded',
            'payment_method' => 'pm_test123',
        ];
        $stripePaymentMethod = [
            'id'   => 'pm_test123',
            'type' => 'card',
            'card' => [
                'brand'     => 'visa',
                'last4'     => '4242',
                'exp_month' => 12,
                'exp_year'  => 2025,
            ],
        ];
        $stripeSubscription  = [
            'id'                   => 'sub_test123',
            'status'               => 'active',
            'current_period_start' => Carbon::now()->timestamp,
            'current_period_end'   => Carbon::now()->addMonth()->timestamp,
            'trial_end'            => null,
        ];

        $this->mockStripeService
            ->shouldReceive('createCustomer')
            ->once()
            ->andReturn($stripeCustomer);

        $this->mockStripeService
            ->shouldReceive('createSetupIntent')
            ->once()
            ->andReturn($setupIntent);

        $this->mockStripeService
            ->shouldReceive('confirmSetupIntent')
            ->once()
            ->andReturn($confirmSetupIntent);

        $this->mockStripeService
            ->shouldReceive('attachPaymentMethod')
            ->once()
            ->andReturn($stripePaymentMethod);

        $this->mockStripeService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($stripeSubscription);

        // When - Execute complete billing setup workflow

        // Step 1: Setup team billing
        $teamWithBilling = $this->billingService->setupTeamBilling($this->team, [
            'email' => 'test@example.com',
            'name'  => 'Test Team',
        ]);

        // Step 2: Create setup intent
        $setupIntentResult = $this->billingService->createSetupIntent($teamWithBilling);

        // Step 3: Confirm setup intent (simulates frontend payment confirmation)
        $confirmResult = $this->billingService->confirmSetupIntent($teamWithBilling, $setupIntentResult['id']);

        // Step 4: Subscribe to plan
        $subscription = $this->billingService->subscribeTeamToPlan($teamWithBilling, $this->plan);

        // Then - Verify complete workflow results

        // Verify team has Stripe customer ID
        $this->assertEquals('cus_test123', $teamWithBilling->stripe_customer_id);

        // Verify payment method was created and is default
        $paymentMethod = PaymentMethod::where('team_id', $this->team->id)->first();
        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('pm_test123', $paymentMethod->stripe_payment_method_id);
        $this->assertTrue($paymentMethod->is_default);

        // Verify subscription was created
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($this->team->id, $subscription->team_id);
        $this->assertEquals($this->plan->id, $subscription->subscription_plan_id);
        $this->assertEquals('sub_test123', $subscription->stripe_subscription_id);
        $this->assertEquals('active', $subscription->status);

        // Verify database state
        $this->assertDatabaseHas('teams', [
            'id'                 => $this->team->id,
            'stripe_customer_id' => 'cus_test123',
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'team_id'                  => $this->team->id,
            'stripe_payment_method_id' => 'pm_test123',
            'is_default'               => true,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'team_id'                => $this->team->id,
            'subscription_plan_id'   => $this->plan->id,
            'stripe_subscription_id' => 'sub_test123',
            'status'                 => 'active',
        ]);
    }

    public function test_usageBillingWorkflow_withActiveSubscription_processesChargesCorrectly(): void
    {
        // Given - Setup team with billing and active subscription
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'subscription_plan_id' => $this->plan->id,
            'status'               => 'active',
            'cancel_at_period_end' => false,
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'team_id'    => $this->team->id,
            'is_default' => true,
        ]);

        // Create usage events for yesterday
        $yesterday   = Carbon::now()->subDay();
        $usageEvent1 = UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_cost'    => 3.25,
            'output_cost'   => 2.75,
            'input_tokens'  => 1000,
            'output_tokens' => 500,
            'request_count' => 1,
            'data_volume'   => 1024,
            'created_at'    => $yesterday,
        ]);

        $usageEvent2 = UsageEvent::factory()->create([
            'team_id'       => $this->team->id,
            'input_cost'    => 4.50,
            'output_cost'   => 3.25,
            'input_tokens'  => 1500,
            'output_tokens' => 750,
            'request_count' => 1,
            'data_volume'   => 2048,
            'created_at'    => $yesterday,
        ]);

        // Mock successful Stripe charge
        $expectedCharge = [
            'status' => 'succeeded',
            'id'     => 'ch_test123',
            'amount' => 1375, // $13.75 in cents
        ];

        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->with('cus_test123', 1375, 'USD', \Mockery::type('string'))
            ->once()
            ->andReturn($expectedCharge);

        // When - Process team billing
        $this->usageBillingService->processTeamBilling($this->team);

        // Then - Verify billing workflow results

        // Verify billing history record was created
        $billingHistory = BillingHistory::where('team_id', $this->team->id)->first();
        $this->assertInstanceOf(BillingHistory::class, $billingHistory);
        $this->assertEquals('usage_charge', $billingHistory->type);
        $this->assertEquals('processed', $billingHistory->status);
        $this->assertEquals(13.75, $billingHistory->amount);
        $this->assertEquals('ch_test123', $billingHistory->stripe_charge_id);

        // Verify metadata contains usage stats
        $this->assertArrayHasKey('usage_stats', $billingHistory->metadata);
        $usageStats = $billingHistory->metadata['usage_stats'];
        $this->assertEquals(2, $usageStats['event_count']);
        $this->assertEquals(2500, $usageStats['total_input_tokens']);
        $this->assertEquals(1250, $usageStats['total_output_tokens']);
        $this->assertEquals(13.75, $usageStats['total_cost']);

        // Verify database record
        $this->assertDatabaseHas('billing_history', [
            'team_id'          => $this->team->id,
            'type'             => 'usage_charge',
            'status'           => 'processed',
            'amount'           => 13.75,
            'stripe_charge_id' => 'ch_test123',
        ]);
    }

    public function test_subscriptionManagementWorkflow_changePlanAndCancel_worksEndToEnd(): void
    {
        // Given - Setup team with active subscription
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        $subscription = Subscription::factory()->create([
            'team_id'                => $this->team->id,
            'subscription_plan_id'   => $this->plan->id,
            'status'                 => 'active',
            'stripe_subscription_id' => 'sub_test123',
        ]);

        // Create a new plan to upgrade to
        $newPlan = SubscriptionPlan::factory()->create([
            'is_active'       => true,
            'monthly_price'   => 49.99,
            'yearly_price'    => 499.99,
            'stripe_price_id' => 'price_new123',
        ]);

        // Mock Stripe responses
        $updatedStripeSubscription = [
            'id'                   => 'sub_test123',
            'current_period_start' => Carbon::now()->timestamp,
            'current_period_end'   => Carbon::now()->addMonth()->timestamp,
        ];

        $canceledStripeSubscription = [
            'id'                 => 'sub_test123',
            'status'             => 'canceled',
            'canceled_at'        => Carbon::now()->timestamp,
            'current_period_end' => Carbon::now()->addMonth()->timestamp,
        ];

        $this->mockStripeService
            ->shouldReceive('updateSubscription')
            ->with('sub_test123', ['price_id' => 'price_new123'])
            ->once()
            ->andReturn($updatedStripeSubscription);

        $this->mockStripeService
            ->shouldReceive('cancelSubscription')
            ->with('sub_test123', true)
            ->once()
            ->andReturn($canceledStripeSubscription);

        // When - Execute subscription management workflow

        // Step 1: Change subscription plan
        $updatedSubscription = $this->billingService->changeSubscriptionPlan($subscription, $newPlan);

        // Step 2: Cancel subscription
        $canceledSubscription = $this->billingService->cancelSubscription($updatedSubscription);

        // Then - Verify subscription management results

        // Verify plan change
        $this->assertEquals($newPlan->id, $updatedSubscription->subscription_plan_id);
        $this->assertEquals(49.99, $updatedSubscription->monthly_amount);
        $this->assertEquals(499.99, $updatedSubscription->yearly_amount);

        // Verify cancellation
        $this->assertEquals('canceled', $canceledSubscription->status);
        $this->assertNotNull($canceledSubscription->canceled_at);
        $this->assertNotNull($canceledSubscription->ends_at);

        // Verify database state
        $this->assertDatabaseHas('subscriptions', [
            'id'                   => $subscription->id,
            'subscription_plan_id' => $newPlan->id,
            'status'               => 'canceled',
        ]);
    }

    public function test_paymentMethodManagement_addAndRemove_worksEndToEnd(): void
    {
        // Given - Setup team with billing
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        // Mock Stripe responses for payment method operations
        $stripePaymentMethod1 = [
            'id'   => 'pm_test123',
            'type' => 'card',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2025],
        ];

        $stripePaymentMethod2 = [
            'id'   => 'pm_test456',
            'type' => 'card',
            'card' => ['brand' => 'mastercard', 'last4' => '5555', 'exp_month' => 6, 'exp_year' => 2026],
        ];

        $this->mockStripeService
            ->shouldReceive('attachPaymentMethod')
            ->with('pm_test123', 'cus_test123')
            ->once()
            ->andReturn($stripePaymentMethod1);

        $this->mockStripeService
            ->shouldReceive('attachPaymentMethod')
            ->with('pm_test456', 'cus_test123')
            ->once()
            ->andReturn($stripePaymentMethod2);

        $this->mockStripeService
            ->shouldReceive('detachPaymentMethod')
            ->with('pm_test123')
            ->once()
            ->andReturn(['id' => 'pm_test123', 'customer' => null]);

        // When - Execute payment method management workflow

        // Step 1: Add first payment method (becomes default)
        $paymentMethod1 = $this->billingService->addPaymentMethod($this->team, 'pm_test123');

        // Step 2: Add second payment method (first remains default)
        $paymentMethod2 = $this->billingService->addPaymentMethod($this->team, 'pm_test456');

        // Step 3: Remove first payment method (second becomes default)
        $removeResult = $this->billingService->removePaymentMethod($paymentMethod1);

        // Then - Verify payment method management results

        // Verify first payment method was added and became default
        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod1);
        $this->assertEquals('pm_test123', $paymentMethod1->stripe_payment_method_id);
        $this->assertTrue($paymentMethod1->is_default);
        $this->assertEquals('visa', $paymentMethod1->card_brand);
        $this->assertEquals('4242', $paymentMethod1->card_last_four);

        // Verify second payment method was added but not default
        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod2);
        $this->assertEquals('pm_test456', $paymentMethod2->stripe_payment_method_id);
        $this->assertFalse($paymentMethod2->is_default);
        $this->assertEquals('mastercard', $paymentMethod2->card_brand);
        $this->assertEquals('5555', $paymentMethod2->card_last_four);

        // Verify first payment method was removed
        $this->assertTrue($removeResult);
        $this->assertSoftDeleted('payment_methods', ['id' => $paymentMethod1->id]);

        // Verify second payment method became default
        $paymentMethod2->refresh();
        $this->assertTrue($paymentMethod2->is_default);

        // Verify final database state
        $this->assertDatabaseHas('payment_methods', [
            'id'                       => $paymentMethod2->id,
            'is_default'               => true,
            'stripe_payment_method_id' => 'pm_test456',
        ]);
    }

    public function test_failedPaymentWorkflow_recordsFailureCorrectly(): void
    {
        // Given - Setup team with billing and usage
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        $subscription = Subscription::factory()->create([
            'team_id'              => $this->team->id,
            'subscription_plan_id' => $this->plan->id,
            'status'               => 'active',
            'cancel_at_period_end' => false,
        ]);

        PaymentMethod::factory()->create([
            'team_id'    => $this->team->id,
            'is_default' => true,
        ]);

        // Create usage events
        UsageEvent::factory()->create([
            'team_id'     => $this->team->id,
            'input_cost'  => 5.00,
            'output_cost' => 5.00,
            'created_at'  => Carbon::now()->subDay(),
        ]);

        // Mock failed Stripe charge
        $failedCharge = [
            'status' => 'failed',
            'id'     => 'ch_failed123',
            'error'  => 'Your card was declined.',
        ];

        $this->mockStripeService
            ->shouldReceive('createCharge')
            ->once()
            ->andReturn($failedCharge);

        // When - Process billing (which will fail)
        $this->usageBillingService->processTeamBilling($this->team);

        // Then - Verify failed payment is recorded correctly
        $billingHistory = BillingHistory::where('team_id', $this->team->id)->first();
        $this->assertInstanceOf(BillingHistory::class, $billingHistory);
        $this->assertEquals('usage_charge', $billingHistory->type);
        $this->assertEquals('failed', $billingHistory->status);
        $this->assertEquals(10.00, $billingHistory->amount);
        $this->assertEquals('ch_failed123', $billingHistory->stripe_charge_id);

        // Verify error information is stored in metadata
        $this->assertArrayHasKey('error', $billingHistory->metadata);
        $this->assertEquals('Your card was declined.', $billingHistory->metadata['error']);

        // Verify database record
        $this->assertDatabaseHas('billing_history', [
            'team_id'          => $this->team->id,
            'type'             => 'usage_charge',
            'status'           => 'failed',
            'stripe_charge_id' => 'ch_failed123',
        ]);
    }

    public function test_webhookProcessing_invoicePayment_createsCorrectBillingHistory(): void
    {
        // Given - Setup team with billing
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        $invoice = [
            'id'           => 'in_test123',
            'customer'     => 'cus_test123',
            'description'  => 'Subscription payment for Professional Plan',
            'amount_paid'  => 2999, // $29.99 in cents
            'currency'     => 'usd',
            'created'      => Carbon::now()->timestamp,
            'invoice_pdf'  => 'https://stripe.com/invoice.pdf',
            'number'       => 'INV-2024-001',
            'subscription' => 'sub_test123',
        ];

        // When - Process invoice payment webhook
        $this->billingService->recordInvoicePayment($invoice, 'succeeded');

        // Then - Verify invoice payment is recorded
        $billingHistory = BillingHistory::where('team_id', $this->team->id)->first();
        $this->assertInstanceOf(BillingHistory::class, $billingHistory);
        $this->assertEquals('invoice', $billingHistory->type);
        $this->assertEquals('paid', $billingHistory->status);
        $this->assertEquals(29.99, $billingHistory->amount);
        $this->assertEquals('in_test123', $billingHistory->stripe_invoice_id);
        $this->assertEquals('https://stripe.com/invoice.pdf', $billingHistory->invoice_url);

        // Verify metadata contains invoice details
        $this->assertArrayHasKey('invoice_number', $billingHistory->metadata);
        $this->assertEquals('INV-2024-001', $billingHistory->metadata['invoice_number']);
        $this->assertEquals('sub_test123', $billingHistory->metadata['subscription_id']);

        // Verify database record
        $this->assertDatabaseHas('billing_history', [
            'team_id'           => $this->team->id,
            'type'              => 'invoice',
            'status'            => 'paid',
            'amount'            => 29.99,
            'stripe_invoice_id' => 'in_test123',
        ]);
    }
}
