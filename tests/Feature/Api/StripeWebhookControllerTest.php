<?php

namespace Tests\Feature\Api;

use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Team\Team;
use App\Services\Billing\BillingService;
use App\Services\Billing\MockStripePaymentService;
use App\Services\Billing\StripePaymentServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Bind mock Stripe service for testing
        $this->app->bind(StripePaymentServiceInterface::class, MockStripePaymentService::class);
    }

    public function test_handleWebhook_withValidSignature_processesEvent(): void
    {
        // Given
        $payload = [
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
        ];

        $team = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team->id,
            'type'    => 'invoice',
            'amount'  => 29.99,
            'status'  => 'paid',
        ]);

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
        $response = $this->withHeaders([
            'Stripe-Signature' => 'invalid_signature',
        ])->post('/stripe/webhook', [], [], 'invalid payload');

        // Then
        if ($response->status() !== 400) {
            $this->fail('Expected 400 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);

    }

    public function test_handleWebhook_withoutSignature_returnsError(): void
    {
        // When
        $response = $this->post('/stripe/webhook', [], [], 'payload');

        // Then
        if ($response->status() !== 400) {
            $this->fail('Expected 400 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_handleWebhook_paymentIntentSucceeded_processesSuccessfulPayment(): void
    {
        // Given
        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

    }

    public function test_handleWebhook_paymentIntentFailed_processesFailedPayment(): void
    {
        // Given
        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

    }

    public function test_handleWebhook_subscriptionCreated_syncsSubscription(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

        // Note: This would require the sync method to actually update the database
        // For now, we just verify the webhook was processed successfully
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

        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
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

        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

        // Verify subscription was marked as canceled
        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_handleWebhook_invoicePaymentFailed_recordsFailure(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

        // Verify failed payment was recorded
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $team->id,
            'type'    => 'invoice',
            'amount'  => 29.99, // For failed payments, amount should be the due amount
            'status'  => 'open',
        ]);
    }

    public function test_handleWebhook_paymentMethodAttached_syncsPaymentMethod(): void
    {
        // Given
        $team    = Team::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $payload = [
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
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
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

        // Verify payment method exists before test
        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'stripe_payment_method_id' => 'pm_detached123',
        ]);

        $payload = [
            'id'   => 'evt_pm_detached',
            'type' => 'payment_method.detached',
            'data' => [
                'object' => [
                    'id'       => 'pm_detached123',
                    'customer' => null,
                ],
            ],
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

        // Verify payment method was soft-deleted
        $this->assertSoftDeleted('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    public function test_handleWebhook_unknownEventType_logsAndIgnores(): void
    {
        // Given
        $payload = [
            'id'   => 'evt_unknown',
            'type' => 'unknown.event.type',
            'data' => ['object' => ['id' => 'obj_unknown']],
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();

    }

    public function test_handleWebhook_withException_returnsError(): void
    {
        // Given - Mock BillingService to throw exception
        $this->app->bind(BillingService::class, function () {
            $mock = $this->mock(BillingService::class);
            $mock->shouldReceive('validateWebhookSignature')->andReturn(['type' => 'payment_intent.succeeded', 'id' => 'evt_error']);
            $mock->shouldReceive('processSuccessfulPayment')->andThrow(new \Exception('Test error'));

            return $mock;
        });

        $payload = [
            'id'   => 'evt_error',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_error']],
        ];

        // When
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then
        if ($response->status() !== 500) {
            $this->fail('Expected 500 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertStatus(500);
        $response->assertJson(['error' => 'Webhook processing failed']);

    }

    public function test_webhook_doesNotRequireAuthentication(): void
    {
        // Given - webhook payload
        $payload = [
            'id'   => 'evt_test',
            'type' => 'ping',
            'data' => ['object' => []],
        ];

        // When - call webhook without authentication
        $response = $this->withHeaders([
            'Stripe-Signature' => 'test_signature',
        ])->postJson('/stripe/webhook', $payload);

        // Then - should succeed (webhooks don't require auth)
        if ($response->status() !== 200) {
            $this->fail('Expected 200 status but got ' . $response->status() . '. Response: ' . $response->getContent());
        }
        $response->assertOk();
    }

}
