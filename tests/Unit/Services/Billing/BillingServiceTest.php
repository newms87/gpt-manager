<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripePaymentServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class BillingServiceTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    private BillingService $billingService;
    private StripePaymentServiceInterface $mockStripeService;
    private Team $team;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        
        $this->mockStripeService = $this->mock(StripePaymentServiceInterface::class);
        $this->billingService = new BillingService($this->mockStripeService);
        
        // Use the team from AuthenticatedTestCase
        $this->team = $this->user->currentTeam;
    }

    public function test_setupTeamBilling_withValidTeam_createsCustomerAndUpdatesTeam(): void
    {
        // Given
        $customerData = ['email' => 'test@example.com', 'name' => 'Test Team'];
        $stripeCustomer = [
            'id' => 'cus_test123',
            'email' => 'test@example.com',
            'name' => 'Test Team'
        ];

        $this->mockStripeService
            ->shouldReceive('createCustomer')
            ->with($this->team, $customerData)
            ->once()
            ->andReturn($stripeCustomer);

        // When
        $result = $this->billingService->setupTeamBilling($this->team, $customerData);

        // Then
        $this->assertEquals('cus_test123', $result->stripe_customer_id);
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'stripe_customer_id' => 'cus_test123'
        ]);
    }

    public function test_setupTeamBilling_withExistingCustomer_throwsValidationError(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_existing123']);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team already has billing setup');

        // When
        $this->billingService->setupTeamBilling($this->team);
    }

    public function test_setupTeamBilling_withDifferentTeam_throwsValidationError(): void
    {
        // Given
        $differentTeam = Team::factory()->create();

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this team');

        // When
        $this->billingService->setupTeamBilling($differentTeam);
    }

    public function test_createSetupIntent_withValidTeam_returnsSetupIntent(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $setupIntent = [
            'id' => 'seti_test123',
            'client_secret' => 'seti_test123_secret',
            'status' => 'requires_payment_method'
        ];

        $this->mockStripeService
            ->shouldReceive('createSetupIntent')
            ->with('cus_test123')
            ->once()
            ->andReturn($setupIntent);

        // When
        $result = $this->billingService->createSetupIntent($this->team);

        // Then
        $this->assertEquals($setupIntent, $result);
    }

    public function test_createSetupIntent_withoutStripeCustomer_throwsValidationError(): void
    {
        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team billing not setup. Call setupTeamBilling first.');

        // When
        $this->billingService->createSetupIntent($this->team);
    }

    public function test_addPaymentMethod_withValidData_createsPaymentMethod(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $paymentMethodId = 'pm_test123';
        $stripePaymentMethod = [
            'id' => $paymentMethodId,
            'type' => 'card',
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2025
            ]
        ];

        $this->mockStripeService
            ->shouldReceive('attachPaymentMethod')
            ->with($paymentMethodId, 'cus_test123')
            ->once()
            ->andReturn($stripePaymentMethod);

        // When
        $result = $this->billingService->addPaymentMethod($this->team, $paymentMethodId);

        // Then
        $this->assertInstanceOf(PaymentMethod::class, $result);
        $this->assertEquals($this->team->id, $result->team_id);
        $this->assertEquals($paymentMethodId, $result->stripe_payment_method_id);
        $this->assertEquals('card', $result->type);
        $this->assertEquals('visa', $result->card_brand);
        $this->assertEquals('4242', $result->card_last_four);
        $this->assertTrue($result->is_default); // First payment method should be default
    }

    public function test_addPaymentMethod_withoutStripeCustomer_throwsValidationError(): void
    {
        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team billing not setup');

        // When
        $this->billingService->addPaymentMethod($this->team, 'pm_test123');
    }

    public function test_removePaymentMethod_withValidPaymentMethod_removesAndUpdatesDefault(): void
    {
        // Given
        $paymentMethod1 = PaymentMethod::factory()->create([
            'team_id' => $this->team->id,
            'is_default' => true,
            'stripe_payment_method_id' => 'pm_test1'
        ]);
        $paymentMethod2 = PaymentMethod::factory()->create([
            'team_id' => $this->team->id,
            'is_default' => false,
            'stripe_payment_method_id' => 'pm_test2'
        ]);

        $this->mockStripeService
            ->shouldReceive('detachPaymentMethod')
            ->with('pm_test1')
            ->once()
            ->andReturn(['id' => 'pm_test1', 'customer' => null]);

        // When
        $result = $this->billingService->removePaymentMethod($paymentMethod1);

        // Then
        $this->assertTrue($result);
        $this->assertSoftDeleted('payment_methods', ['id' => $paymentMethod1->id]);
        
        // Check that the other payment method became default
        $paymentMethod2->refresh();
        $this->assertTrue($paymentMethod2->is_default);
    }

    public function test_removePaymentMethod_withDifferentTeam_throwsValidationError(): void
    {
        // Given
        $differentTeam = Team::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['team_id' => $differentTeam->id]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this payment method');

        // When
        $this->billingService->removePaymentMethod($paymentMethod);
    }

    public function test_subscribeTeamToPlan_withValidData_createsSubscription(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $plan = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'stripe_price_id' => 'price_test123',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99
        ]);

        $stripeSubscription = [
            'id' => 'sub_test123',
            'status' => 'active',
            'current_period_start' => Carbon::now()->timestamp,
            'current_period_end' => Carbon::now()->addMonth()->timestamp,
            'trial_end' => null
        ];

        $this->mockStripeService
            ->shouldReceive('createSubscription')
            ->with('cus_test123', 'price_test123', [])
            ->once()
            ->andReturn($stripeSubscription);

        // When
        $result = $this->billingService->subscribeTeamToPlan($this->team, $plan);

        // Then
        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($this->team->id, $result->team_id);
        $this->assertEquals($plan->id, $result->subscription_plan_id);
        $this->assertEquals('sub_test123', $result->stripe_subscription_id);
        $this->assertEquals('active', $result->status);
    }

    public function test_subscribeTeamToPlan_withInactivePlan_throwsValidationError(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Subscription plan is not active');

        // When
        $this->billingService->subscribeTeamToPlan($this->team, $plan);
    }

    public function test_subscribeTeamToPlan_withExistingSubscription_throwsValidationError(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);
        Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active'
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team already has an active subscription');

        // When
        $this->billingService->subscribeTeamToPlan($this->team, $plan);
    }

    public function test_changeSubscriptionPlan_withValidData_updatesSubscription(): void
    {
        // Given
        $oldPlan = SubscriptionPlan::factory()->create(['is_active' => true]);
        $newPlan = SubscriptionPlan::factory()->create([
            'is_active' => true,
            'stripe_price_id' => 'price_new123',
            'monthly_price' => 49.99,
            'yearly_price' => 499.99
        ]);
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'subscription_plan_id' => $oldPlan->id,
            'stripe_subscription_id' => 'sub_test123'
        ]);

        $stripeSubscription = [
            'id' => 'sub_test123',
            'current_period_start' => Carbon::now()->timestamp,
            'current_period_end' => Carbon::now()->addMonth()->timestamp
        ];

        $this->mockStripeService
            ->shouldReceive('updateSubscription')
            ->with('sub_test123', ['price_id' => 'price_new123'])
            ->once()
            ->andReturn($stripeSubscription);

        // When
        $result = $this->billingService->changeSubscriptionPlan($subscription, $newPlan);

        // Then
        $this->assertEquals($newPlan->id, $result->subscription_plan_id);
        $this->assertEquals(49.99, $result->monthly_amount);
        $this->assertEquals(499.99, $result->yearly_amount);
    }

    public function test_cancelSubscription_withValidSubscriptionObject_cancelsAndUpdatesStatus(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active',
            'canceled_at' => null
        ]);

        $stripeSubscription = [
            'id' => 'sub_test123',
            'status' => 'canceled',
            'canceled_at' => Carbon::now()->timestamp,
            'current_period_end' => Carbon::now()->addMonth()->timestamp
        ];

        $this->mockStripeService
            ->shouldReceive('cancelSubscription')
            ->with('sub_test123', true)
            ->once()
            ->andReturn($stripeSubscription);

        // When
        $result = $this->billingService->cancelSubscription($subscription);

        // Then
        $this->assertEquals('canceled', $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertNotNull($result->ends_at);
    }

    public function test_cancelSubscription_withAlreadyCanceled_throwsValidationError(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'canceled_at' => Carbon::now()
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Subscription is already canceled');

        // When
        $this->billingService->cancelSubscription($subscription);
    }

    public function test_cancelTeamSubscription_withActiveSubscription_cancelsSubscription(): void
    {
        // Given
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test123'
        ]);

        $this->mockStripeService
            ->shouldReceive('cancelSubscription')
            ->with('sub_test123')
            ->once()
            ->andReturn(['id' => 'sub_test123', 'status' => 'canceled']);

        // When
        $result = $this->billingService->cancelTeamSubscription($this->team);

        // Then
        $this->assertTrue($result);
        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_cancelTeamSubscription_withNoActiveSubscription_returnsFalse(): void
    {
        // When
        $result = $this->billingService->cancelTeamSubscription($this->team);

        // Then
        $this->assertFalse($result);
    }

    public function test_processUsageCharges_withValidData_createsBillingHistory(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $amount = 15.50;
        $description = 'API usage charges';
        $metadata = ['usage_type' => 'api_calls'];

        $invoiceItem = [
            'id' => 'ii_test123',
            'customer' => 'cus_test123',
            'amount' => 1550,
            'description' => $description
        ];

        $this->mockStripeService
            ->shouldReceive('createInvoiceItem')
            ->with('cus_test123', $amount, 'USD', [
                'description' => $description,
                'metadata' => $metadata
            ])
            ->once()
            ->andReturn($invoiceItem);

        // When
        $result = $this->billingService->processUsageCharges($this->team, $amount, $description, $metadata);

        // Then
        $this->assertInstanceOf(BillingHistory::class, $result);
        $this->assertEquals($this->team->id, $result->team_id);
        $this->assertEquals('usage_charge', $result->type);
        $this->assertEquals($amount, $result->amount);
        $this->assertEquals('pending', $result->status);
        $this->assertArrayHasKey('stripe_invoice_item_id', $result->metadata);
    }

    public function test_processUsageCharges_withZeroAmount_throwsValidationError(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Usage charge amount must be greater than 0');

        // When
        $this->billingService->processUsageCharges($this->team, 0, 'Test charge');
    }

    public function test_confirmSetupIntent_withSuccessfulIntent_addsPaymentMethod(): void
    {
        // Given
        $setupIntentId = 'seti_test123';
        $setupIntentResult = [
            'status' => 'succeeded',
            'payment_method' => 'pm_test123'
        ];

        $stripePaymentMethod = [
            'id' => 'pm_test123',
            'type' => 'card',
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2025
            ]
        ];

        $this->team->update(['stripe_customer_id' => 'cus_test123']);

        $this->mockStripeService
            ->shouldReceive('confirmSetupIntent')
            ->with($setupIntentId)
            ->once()
            ->andReturn($setupIntentResult);

        $this->mockStripeService
            ->shouldReceive('attachPaymentMethod')
            ->with('pm_test123', 'cus_test123')
            ->once()
            ->andReturn($stripePaymentMethod);

        // When
        $result = $this->billingService->confirmSetupIntent($this->team, $setupIntentId);

        // Then
        $this->assertEquals('succeeded', $result['status']);
        $this->assertInstanceOf(PaymentMethod::class, $result['payment_method']);
    }

    public function test_validateWebhookSignature_withValidSignature_returnsEvent(): void
    {
        // Given
        $payload = '{"type": "invoice.payment_succeeded"}';
        $signature = 'test_signature';
        $expectedEvent = ['type' => 'invoice.payment_succeeded'];

        $this->mockStripeService
            ->shouldReceive('validateWebhookSignature')
            ->with($payload, $signature)
            ->once()
            ->andReturn($expectedEvent);

        // When
        $result = $this->billingService->validateWebhookSignature($payload, $signature);

        // Then
        $this->assertEquals($expectedEvent, $result);
    }

    public function test_validateWebhookSignature_withoutSignature_returnsNull(): void
    {
        // When
        $result = $this->billingService->validateWebhookSignature('payload', null);

        // Then
        $this->assertNull($result);
    }

    public function test_syncSubscriptionFromStripe_withExistingSubscription_updatesSubscription(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_test123'
        ]);

        $stripeSubscription = [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'past_due',
            'current_period_start' => Carbon::now()->timestamp,
            'current_period_end' => Carbon::now()->addMonth()->timestamp
        ];

        // When
        $this->billingService->syncSubscriptionFromStripe($stripeSubscription);

        // Then
        $subscription->refresh();
        $this->assertEquals('past_due', $subscription->status);
    }

    public function test_recordInvoicePayment_withSuccessfulPayment_createsBillingHistory(): void
    {
        // Given
        $this->team->update(['stripe_customer_id' => 'cus_test123']);
        $invoice = [
            'id' => 'in_test123',
            'customer' => 'cus_test123',
            'description' => 'Subscription payment',
            'amount_paid' => 2999, // $29.99 in cents
            'currency' => 'usd',
            'created' => Carbon::now()->timestamp,
            'invoice_pdf' => 'https://invoice.pdf',
            'number' => 'INV-001',
            'subscription' => 'sub_test123'
        ];

        // When
        $this->billingService->recordInvoicePayment($invoice, 'succeeded');

        // Then
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'amount' => 29.99,
            'status' => 'paid',
            'stripe_invoice_id' => 'in_test123'
        ]);
    }

}