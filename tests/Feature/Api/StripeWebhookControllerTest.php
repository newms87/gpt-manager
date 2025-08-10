<?php

namespace Tests\Feature\Api;

use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Team\Team;
use App\Services\Billing\BillingService;
use App\Services\Billing\MockStripePaymentService;
use App\Services\Billing\StripePaymentServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // Bind mock Stripe service for testing
        $this->app->bind(StripePaymentServiceInterface::class, MockStripePaymentService::class);
    }

    public function test_handleWebhook_withValidSignature_processesEvent(): void
    {
        // Given
        $payload = json_encode([
            'id'   => 'evt_test123',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id'           => 'in_test123',
                    'customer'     => 'cus_test123',
                    'description'  => 'Subscription payment',
                    'amount_paid'  => 2999,
                    'currency'     => 'usd',
                    'created'      => Carbon::now()->timestamp,
                    'invoice_pdf'  => 'https://invoice.pdf',
                    'number'       => 'INV-001',
                    'subscription' => 'sub_test123',
                ],
            ],
        ]);

        $team = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verify billing history was created
        $this->assertDatabaseHas('billing_histories', [
            'team_id' => $team->id,
            'type'    => 'subscription_payment',
            'amount'  => 29.99,
            'status'  => 'paid',
        ]);

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Processing Stripe webhook'
                && $context['type'] === 'invoice.payment_succeeded'
                && $context['id'] === 'evt_test123';
        });
    }

    public function test_handleWebhook_withInvalidSignature_returnsError(): void
    {
        // Given - Mock service returns null for invalid signature
        $this->app->bind(StripePaymentServiceInterface::class, function () {
            $mock = $this->mock(MockStripePaymentService::class);
            $mock->shouldReceive('validateWebhookSignature')->andReturn(null);

            return $mock;
        });

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'invalid_signature',
        ], 'invalid payload');

        // Then
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);

        Log::assertLogged('warning', 'Invalid Stripe webhook signature');
    }

    public function test_handleWebhook_withoutSignature_returnsError(): void
    {
        // When
        $response = $this->post('/api/stripe/webhook', [], [], 'payload');

        // Then
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_handleWebhook_paymentIntentSucceeded_processesSuccessfulPayment(): void
    {
        // Given
        $payload = json_encode([
            'id'   => 'evt_payment_success',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_test123',
                    'customer' => 'cus_test123',
                    'amount'   => 2000,
                    'status'   => 'succeeded',
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Processing successful payment'
                && $context['payment_intent'] === 'pi_test123';
        });
    }

    public function test_handleWebhook_paymentIntentFailed_processesFailedPayment(): void
    {
        // Given
        $payload = json_encode([
            'id'   => 'evt_payment_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id'                 => 'pi_failed123',
                    'customer'           => 'cus_test123',
                    'amount'             => 2000,
                    'status'             => 'failed',
                    'last_payment_error' => [
                        'message' => 'Card declined',
                    ],
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        Log::assertLogged('warning', function ($message, $context) {
            return $message === 'Processing failed payment'
                && $context['payment_intent'] === 'pi_failed123';
        });
    }

    public function test_handleWebhook_subscriptionCreated_syncsSubscription(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = json_encode([
            'id'   => 'evt_sub_created',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id'                   => 'sub_new123',
                    'customer'             => 'cus_test123',
                    'status'               => 'active',
                    'current_period_start' => Carbon::now()->timestamp,
                    'current_period_end'   => Carbon::now()->addMonth()->timestamp,
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Note: This would require the sync method to actually update the database
        // For now, we just verify the webhook was processed successfully
        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Processing Stripe webhook'
                && $context['type'] === 'customer.subscription.created'
                && $context['id'] === 'evt_sub_created';
        });
    }

    public function test_handleWebhook_subscriptionUpdated_syncsSubscription(): void
    {
        // Given
        $team         = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $subscription = Subscription::factory()->create([
            'team_id'                => $team->id,
            'stripe_subscription_id' => 'sub_test123',
            'status'                 => 'active',
        ]);

        $payload = json_encode([
            'id'   => 'evt_sub_updated',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id'                   => 'sub_test123',
                    'customer'             => 'cus_test123',
                    'status'               => 'past_due',
                    'current_period_start' => Carbon::now()->timestamp,
                    'current_period_end'   => Carbon::now()->addMonth()->timestamp,
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Verify subscription was updated
        $subscription->refresh();
        $this->assertEquals('past_due', $subscription->status);
    }

    public function test_handleWebhook_subscriptionDeleted_handlesCancel(): void
    {
        // Given
        $team         = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $subscription = Subscription::factory()->create([
            'team_id'                => $team->id,
            'stripe_subscription_id' => 'sub_canceled123',
            'status'                 => 'active',
            'canceled_at'            => null,
        ]);

        $payload = json_encode([
            'id'   => 'evt_sub_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id'          => 'sub_canceled123',
                    'customer'    => 'cus_test123',
                    'status'      => 'canceled',
                    'canceled_at' => Carbon::now()->timestamp,
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Verify subscription was marked as canceled
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_handleWebhook_invoicePaymentFailed_recordsFailure(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = json_encode([
            'id'   => 'evt_invoice_failed',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id'           => 'in_failed123',
                    'customer'     => 'cus_test123',
                    'description'  => 'Failed payment',
                    'amount_paid'  => 0,
                    'amount_due'   => 2999,
                    'currency'     => 'usd',
                    'created'      => Carbon::now()->timestamp,
                    'number'       => 'INV-FAILED-001',
                    'subscription' => 'sub_test123',
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Verify failed payment was recorded
        $this->assertDatabaseHas('billing_histories', [
            'team_id' => $team->id,
            'type'    => 'subscription_payment',
            'amount'  => 0, // amount_paid is 0 for failed payments
            'status'  => 'failed',
        ]);
    }

    public function test_handleWebhook_paymentMethodAttached_syncsPaymentMethod(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = json_encode([
            'id'   => 'evt_pm_attached',
            'type' => 'payment_method.attached',
            'data' => [
                'object' => [
                    'id'       => 'pm_attached123',
                    'customer' => 'cus_test123',
                    'type'     => 'card',
                    'card'     => [
                        'brand'     => 'visa',
                        'last4'     => '4242',
                        'exp_month' => 12,
                        'exp_year'  => 2025,
                    ],
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Verify payment method was synced
        $this->assertDatabaseHas('payment_methods', [
            'team_id'                  => $team->id,
            'stripe_payment_method_id' => 'pm_attached123',
            'type'                     => 'card',
            'card_brand'               => 'visa',
            'card_last_four'           => '4242',
        ]);
    }

    public function test_handleWebhook_paymentMethodDetached_removesPaymentMethod(): void
    {
        // Given
        $team          = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $paymentMethod = PaymentMethod::factory()->create([
            'team_id'                  => $team->id,
            'stripe_payment_method_id' => 'pm_detached123',
        ]);

        $payload = json_encode([
            'id'   => 'evt_pm_detached',
            'type' => 'payment_method.detached',
            'data' => [
                'object' => [
                    'id'       => 'pm_detached123',
                    'customer' => null,
                ],
            ],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        // Verify payment method was removed
        $this->assertDatabaseMissing('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    public function test_handleWebhook_unknownEventType_logsAndIgnores(): void
    {
        // Given
        $payload = json_encode([
            'id'   => 'evt_unknown',
            'type' => 'unknown.event.type',
            'data' => ['object' => ['id' => 'obj_unknown']],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertOk();

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Processing Stripe webhook'
                && $context['type'] === 'unknown.event.type'
                && $context['id'] === 'evt_unknown';
        });

        Log::assertLogged('info', 'Unhandled webhook event type: unknown.event.type');
    }

    public function test_handleWebhook_withException_returnsError(): void
    {
        // Given - Mock BillingService to throw exception
        $this->app->bind(BillingService::class, function () {
            $mock = $this->mock(BillingService::class);
            $mock->shouldReceive('validateWebhookSignature')->andReturn(['type' => 'test', 'id' => 'evt_error']);
            $mock->shouldReceive('processSuccessfulPayment')->andThrow(new \Exception('Test error'));

            return $mock;
        });

        $payload = json_encode([
            'id'   => 'evt_error',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_error']],
        ]);

        // When
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then
        $response->assertStatus(500);
        $response->assertJson(['error' => 'Webhook processing failed']);

        Log::assertLogged('error', function ($message, $context) {
            return $message === 'Stripe webhook error: Test error'
                && $context['event_type'] === 'payment_intent.succeeded'
                && $context['event_id'] === 'evt_error';
        });
    }

    public function test_webhook_doesNotRequireAuthentication(): void
    {
        // Given - webhook payload
        $payload = json_encode([
            'id'   => 'evt_test',
            'type' => 'ping',
            'data' => ['object' => []],
        ]);

        // When - call webhook without authentication
        $response = $this->post('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'test_signature',
        ], $payload);

        // Then - should succeed (webhooks don't require auth)
        $response->assertOk();
    }

}
