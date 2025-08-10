<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Team\Team;
use App\Services\Billing\MockStripePaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockStripePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockStripePaymentService $mockStripeService;
    private Team $team;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->mockStripeService = new MockStripePaymentService();
        $this->team = Team::factory()->create();
    }

    public function test_createCustomer_withValidData_returnsCustomerData(): void
    {
        // Given
        $customerData = [
            'email' => 'test@example.com',
            'name' => 'Test Customer'
        ];

        // When
        $result = $this->mockStripeService->createCustomer($this->team, $customerData);

        // Then
        $this->assertIsArray($result);
        $this->assertStringStartsWith('cus_mock_', $result['id']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('Test Customer', $result['name']);
        $this->assertEquals($this->team->id, $result['metadata']['team_id']);
    }

    public function test_createCustomer_withMinimalData_usesTeamDefaults(): void
    {
        // When
        $result = $this->mockStripeService->createCustomer($this->team);

        // Then
        $this->assertStringStartsWith('cus_mock_', $result['id']);
        $this->assertEquals($this->team->name, $result['name']);
        $this->assertNull($result['email']);
        $this->assertEquals($this->team->id, $result['metadata']['team_id']);
    }

    public function test_createSetupIntent_returnsValidSetupIntent(): void
    {
        // Given
        $customerId = 'cus_test123';

        // When
        $result = $this->mockStripeService->createSetupIntent($customerId);

        // Then
        $this->assertIsArray($result);
        $this->assertStringStartsWith('seti_mock_', $result['id']);
        $this->assertStringContainsString('_secret_mock', $result['client_secret']);
        $this->assertEquals('requires_payment_method', $result['status']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals('off_session', $result['usage']);
    }

    public function test_createSetupIntent_withOptions_respectsOptions(): void
    {
        // Given
        $customerId = 'cus_test123';
        $options = ['usage' => 'on_session'];

        // When
        $result = $this->mockStripeService->createSetupIntent($customerId, $options);

        // Then
        $this->assertEquals('requires_payment_method', $result['status']);
        $this->assertEquals($customerId, $result['customer']);
    }

    public function test_attachPaymentMethod_returnsAttachedPaymentMethod(): void
    {
        // Given
        $paymentMethodId = 'pm_test123';
        $customerId = 'cus_test123';

        // When
        $result = $this->mockStripeService->attachPaymentMethod($paymentMethodId, $customerId);

        // Then
        $this->assertEquals($paymentMethodId, $result['id']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals('card', $result['type']);
        $this->assertArrayHasKey('card', $result);
        $this->assertEquals('visa', $result['card']['brand']);
        $this->assertEquals('4242', $result['card']['last4']);
        $this->assertEquals(12, $result['card']['exp_month']);
        $this->assertGreaterThan(date('Y'), $result['card']['exp_year']);
    }

    public function test_detachPaymentMethod_returnsDetachedPaymentMethod(): void
    {
        // Given
        $paymentMethodId = 'pm_test123';

        // When
        $result = $this->mockStripeService->detachPaymentMethod($paymentMethodId);

        // Then
        $this->assertEquals($paymentMethodId, $result['id']);
        $this->assertNull($result['customer']);
    }

    public function test_createSubscription_withBasicOptions_returnsActiveSubscription(): void
    {
        // Given
        $customerId = 'cus_test123';
        $priceId = 'price_test123';

        // When
        $result = $this->mockStripeService->createSubscription($customerId, $priceId);

        // Then
        $this->assertStringStartsWith('sub_mock_', $result['id']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals('active', $result['status']);
        $this->assertIsInt($result['current_period_start']);
        $this->assertIsInt($result['current_period_end']);
        $this->assertNull($result['trial_end']);
        $this->assertNull($result['canceled_at']);
        $this->assertArrayHasKey('items', $result);
        $this->assertEquals($priceId, $result['items']['data'][0]['price']['id']);
    }

    public function test_createSubscription_withTrialPeriod_returnsTrialingSubscription(): void
    {
        // Given
        $customerId = 'cus_test123';
        $priceId = 'price_test123';
        $options = ['trial_period_days' => 14];

        // When
        $result = $this->mockStripeService->createSubscription($customerId, $priceId, $options);

        // Then
        $this->assertEquals('trialing', $result['status']);
        $this->assertNotNull($result['trial_end']);
        $this->assertGreaterThan(Carbon::now()->timestamp, $result['trial_end']);
    }

    public function test_updateSubscription_returnsUpdatedSubscription(): void
    {
        // Given
        $subscriptionId = 'sub_test123';
        $options = ['price_id' => 'price_new123'];

        // When
        $result = $this->mockStripeService->updateSubscription($subscriptionId, $options);

        // Then
        $this->assertEquals($subscriptionId, $result['id']);
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('price_new123', $result['items']['data'][0]['price']['id']);
        $this->assertIsInt($result['current_period_start']);
        $this->assertIsInt($result['current_period_end']);
    }

    public function test_cancelSubscription_withAtPeriodEnd_returnsCancelAtPeriodEnd(): void
    {
        // Given
        $subscriptionId = 'sub_test123';

        // When
        $result = $this->mockStripeService->cancelSubscription($subscriptionId, true);

        // Then
        $this->assertEquals($subscriptionId, $result['id']);
        $this->assertEquals('active', $result['status']);
        $this->assertTrue($result['cancel_at_period_end']);
        $this->assertIsInt($result['canceled_at']);
    }

    public function test_cancelSubscription_immediately_returnsCanceledSubscription(): void
    {
        // Given
        $subscriptionId = 'sub_test123';

        // When
        $result = $this->mockStripeService->cancelSubscription($subscriptionId, false);

        // Then
        $this->assertEquals($subscriptionId, $result['id']);
        $this->assertEquals('canceled', $result['status']);
        $this->assertFalse($result['cancel_at_period_end']);
        $this->assertIsInt($result['canceled_at']);
    }

    public function test_createInvoiceItem_returnsInvoiceItem(): void
    {
        // Given
        $customerId = 'cus_test123';
        $amount = 15.50;
        $currency = 'USD';
        $options = [
            'description' => 'Usage charges',
            'metadata' => ['usage_type' => 'api_calls']
        ];

        // When
        $result = $this->mockStripeService->createInvoiceItem($customerId, $amount, $currency, $options);

        // Then
        $this->assertStringStartsWith('ii_mock_', $result['id']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals(1550, $result['amount']); // $15.50 in cents
        $this->assertEquals('usd', $result['currency']);
        $this->assertEquals('Usage charges', $result['description']);
        $this->assertEquals(['usage_type' => 'api_calls'], $result['metadata']);
    }

    public function test_createInvoice_returnsDraftInvoice(): void
    {
        // Given
        $customerId = 'cus_test123';

        // When
        $result = $this->mockStripeService->createInvoice($customerId);

        // Then
        $this->assertStringStartsWith('in_mock_', $result['id']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals('draft', $result['status']);
        $this->assertEquals(2000, $result['amount_due']);
        $this->assertEquals(0, $result['amount_paid']);
        $this->assertEquals('usd', $result['currency']);
        $this->assertIsInt($result['created']);
        $this->assertIsInt($result['due_date']);
    }

    public function test_finalizeInvoice_returnsOpenInvoice(): void
    {
        // Given
        $invoiceId = 'in_test123';

        // When
        $result = $this->mockStripeService->finalizeInvoice($invoiceId);

        // Then
        $this->assertEquals($invoiceId, $result['id']);
        $this->assertEquals('open', $result['status']);
        $this->assertIsInt($result['finalized_at']);
    }

    public function test_payInvoice_returnsPaidInvoice(): void
    {
        // Given
        $invoiceId = 'in_test123';

        // When
        $result = $this->mockStripeService->payInvoice($invoiceId);

        // Then
        $this->assertEquals($invoiceId, $result['id']);
        $this->assertEquals('paid', $result['status']);
        $this->assertIsInt($result['paid_at']);
        $this->assertStringStartsWith('pi_mock_', $result['payment_intent']);
    }

    public function test_retrieveInvoice_returnsInvoiceData(): void
    {
        // Given
        $invoiceId = 'in_test123';

        // When
        $result = $this->mockStripeService->retrieveInvoice($invoiceId);

        // Then
        $this->assertEquals($invoiceId, $result['id']);
        $this->assertEquals('paid', $result['status']);
        $this->assertEquals(2000, $result['amount_due']);
        $this->assertEquals(2000, $result['amount_paid']);
        $this->assertEquals('usd', $result['currency']);
        $this->assertIsInt($result['created']);
        $this->assertIsInt($result['paid_at']);
    }

    public function test_constructWebhookEvent_returnsDecodedPayload(): void
    {
        // Given
        $payload = '{"type": "invoice.payment_succeeded", "id": "evt_test123"}';
        $signature = 'test_signature';
        $secret = 'whsec_test';

        // When
        $result = $this->mockStripeService->constructWebhookEvent($payload, $signature, $secret);

        // Then
        $this->assertEquals('invoice.payment_succeeded', $result['type']);
        $this->assertEquals('evt_test123', $result['id']);
    }

    public function test_validateWebhookSignature_returnsDecodedPayload(): void
    {
        // Given
        $payload = '{"type": "customer.subscription.created"}';
        $signature = 'test_signature';

        // When
        $result = $this->mockStripeService->validateWebhookSignature($payload, $signature);

        // Then
        $this->assertEquals('customer.subscription.created', $result['type']);
    }

    public function test_confirmSetupIntent_returnsSucceededIntent(): void
    {
        // Given
        $setupIntentId = 'seti_test123';

        // When
        $result = $this->mockStripeService->confirmSetupIntent($setupIntentId);

        // Then
        $this->assertEquals($setupIntentId, $result['id']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertStringStartsWith('pm_mock_', $result['payment_method']);
        $this->assertStringStartsWith('cus_mock_', $result['customer']);
    }

    public function test_createCharge_withNormalAmount_returnsSuccessfulCharge(): void
    {
        // Given
        $customerId = 'cus_test123';
        $amountInCents = 1500; // $15.00
        $currency = 'USD';
        $description = 'Usage charges';

        // When
        $result = $this->mockStripeService->createCharge($customerId, $amountInCents, $currency, $description);

        // Then
        $this->assertStringStartsWith('ch_mock_', $result['id']);
        $this->assertEquals($customerId, $result['customer']);
        $this->assertEquals($amountInCents, $result['amount']);
        $this->assertEquals('usd', $result['currency']);
        $this->assertEquals($description, $result['description']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertNull($result['error']);
        $this->assertIsInt($result['created']);
    }

    public function test_createCharge_withFailureTestAmount_returnsFailedCharge(): void
    {
        // Given
        $customerId = 'cus_test123';
        $amountInCents = 99999; // Test failure amount
        $currency = 'USD';
        $description = 'Test failure';

        // When
        $result = $this->mockStripeService->createCharge($customerId, $amountInCents, $currency, $description);

        // Then
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Card declined', $result['error']);
        $this->assertEquals($amountInCents, $result['amount']);
    }
}