<?php

namespace Tests\Unit\Services\Billing;

use App\Api\Stripe\StripeApi;
use App\Models\Team\Team;
use App\Services\Billing\StripePaymentService;
use Tests\TestCase;

class StripePaymentServiceTest extends TestCase
{
    private StripePaymentService $stripeService;
    private Team                 $team;
    private StripeApi            $mockStripeApi;

    public function setUp(): void
    {
        parent::setUp();

        // Set up config for testing
        config(['services.stripe.secret' => 'sk_test_fake_key']);
        config(['services.stripe.webhook_secret' => 'whsec_fake_secret']);

        // Create a mock StripeApi
        $this->mockStripeApi = $this->createMock(StripeApi::class);
        $this->stripeService = new StripePaymentService($this->mockStripeApi);
        $this->team          = Team::factory()->create();
    }

    public function test_constructor_withoutApiKey_throwsValidationError(): void
    {
        // Given
        config(['services.stripe.secret' => null]);

        // Then
        $this->expectException(\Newms87\Danx\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Stripe API key not configured');

        // When
        new StripeApi(); // The validation happens in StripeApi constructor
    }

    public function test_createCustomer_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'cus_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createCustomer')
            ->with([
                'email'    => null,
                'name'     => $this->team->name,
                'metadata' => [
                    'team_id' => (string)$this->team->id,
                ],
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createCustomer($this->team);

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_createSetupIntent_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'seti_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createSetupIntent')
            ->with([
                'customer' => 'cus_test123',
                'usage'    => 'off_session',
                'metadata' => [],
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createSetupIntent('cus_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_attachPaymentMethod_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'pm_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('attachPaymentMethod')
            ->with('pm_test123', 'cus_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->attachPaymentMethod('pm_test123', 'cus_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_detachPaymentMethod_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'pm_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('detachPaymentMethod')
            ->with('pm_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->detachPaymentMethod('pm_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_createSubscription_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'sub_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createSubscription')
            ->with([
                'customer'          => 'cus_test123',
                'items'             => [['price' => 'price_test123']],
                'trial_period_days' => null,
                'metadata'          => [],
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createSubscription('cus_test123', 'price_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_updateSubscription_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'sub_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('updateSubscription')
            ->with('sub_test123', [
                'proration_behavior' => 'create_prorations',
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->updateSubscription('sub_test123', ['price_id' => 'price_new123']);

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_cancelSubscription_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'sub_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('updateSubscription')
            ->with('sub_test123', [
                'cancel_at_period_end' => true,
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->cancelSubscription('sub_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_createInvoiceItem_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'ii_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createInvoiceItem')
            ->with([
                'customer'    => 'cus_test123',
                'amount'      => 1550, // 15.50 * 100
                'currency'    => 'usd',
                'description' => 'Usage charges',
                'metadata'    => [],
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createInvoiceItem('cus_test123', 15.50);

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_createInvoice_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'in_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createInvoice')
            ->with([
                'customer'          => 'cus_test123',
                'auto_advance'      => true,
                'collection_method' => 'charge_automatically',
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createInvoice('cus_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_finalizeInvoice_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'in_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('finalizeInvoice')
            ->with('in_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->finalizeInvoice('in_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_payInvoice_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'in_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('payInvoice')
            ->with('in_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->payInvoice('in_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_retrieveInvoice_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'in_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('retrieveInvoice')
            ->with('in_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->retrieveInvoice('in_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_constructWebhookEvent_returnsDecodedPayload(): void
    {
        // Given
        $payload   = '{"id": "evt_test123"}';
        $signature = 'test_signature';
        $secret    = 'test_secret';

        // When
        $result = $this->stripeService->constructWebhookEvent($payload, $signature, $secret);

        // Then
        $this->assertEquals(['id' => 'evt_test123'], $result);
    }

    public function test_validateWebhookSignature_returnsDecodedPayload(): void
    {
        // Given
        $payload   = '{"id": "evt_test123"}';
        $signature = 'test_signature';

        // When
        $result = $this->stripeService->validateWebhookSignature($payload, $signature);

        // Then
        $this->assertEquals(['id' => 'evt_test123'], $result);
    }

    public function test_confirmSetupIntent_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'seti_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('confirmSetupIntent')
            ->with('seti_test123')
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->confirmSetupIntent('seti_test123');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_createCharge_delegatesToStripeApi(): void
    {
        // Given
        $expectedResponse = ['id' => 'ch_test123'];
        $this->mockStripeApi->expects($this->once())
            ->method('createCharge')
            ->with([
                'customer'    => 'cus_test123',
                'amount'      => 1500,
                'currency'    => 'usd',
                'description' => 'Test charge',
            ])
            ->willReturn($expectedResponse);

        // When
        $result = $this->stripeService->createCharge('cus_test123', 1500, 'USD', 'Test charge');

        // Then
        $this->assertEquals($expectedResponse, $result);
    }
}
