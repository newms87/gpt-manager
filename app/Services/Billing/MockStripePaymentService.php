<?php

namespace App\Services\Billing;

use App\Models\Team\Team;
use Carbon\Carbon;

class MockStripePaymentService implements StripePaymentServiceInterface
{
    public function createCustomer(Team $team, array $customerData = []): array
    {
        return [
            'id'       => 'cus_mock_' . uniqid(),
            'email'    => $customerData['email'] ?? null,
            'name'     => $customerData['name']  ?? $team->name,
            'metadata' => [
                'team_id' => $team->id,
            ],
        ];
    }

    public function createSetupIntent(string $customerId, array $options = []): array
    {
        return [
            'id'            => 'seti_mock_' . uniqid(),
            'client_secret' => 'seti_mock_' . uniqid() . '_secret_mock',
            'status'        => 'requires_payment_method',
            'customer'      => $customerId,
            'usage'         => 'off_session',
        ];
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return [
            'id'       => $paymentMethodId,
            'customer' => $customerId,
            'type'     => 'card',
            'card'     => [
                'brand'     => 'visa',
                'last4'     => '4242',
                'exp_month' => 12,
                'exp_year'  => date('Y') + 2,
            ],
        ];
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return [
            'id'       => $paymentMethodId,
            'customer' => null,
        ];
    }

    public function createSubscription(string $customerId, string $priceId, array $options = []): array
    {
        $now      = Carbon::now();
        $trialEnd = isset($options['trial_period_days']) ?
            $now->copy()->addDays($options['trial_period_days']) : null;

        return [
            'id'                   => 'sub_mock_' . uniqid(),
            'customer'             => $customerId,
            'status'               => $trialEnd ? 'trialing' : 'active',
            'current_period_start' => $now->timestamp,
            'current_period_end'   => $now->copy()->addMonth()->timestamp,
            'trial_end'            => $trialEnd?->timestamp,
            'canceled_at'          => null,
            'items'                => [
                'data' => [
                    [
                        'id'    => 'si_mock_' . uniqid(),
                        'price' => [
                            'id' => $priceId,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function updateSubscription(string $subscriptionId, array $options = []): array
    {
        $now = Carbon::now();

        return [
            'id'                   => $subscriptionId,
            'status'               => 'active',
            'current_period_start' => $now->timestamp,
            'current_period_end'   => $now->copy()->addMonth()->timestamp,
            'items'                => [
                'data' => [
                    [
                        'id'    => 'si_mock_' . uniqid(),
                        'price' => [
                            'id' => $options['price_id'] ?? 'price_mock_default',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        $now = Carbon::now();

        return [
            'id'                   => $subscriptionId,
            'status'               => $atPeriodEnd ? 'active' : 'canceled',
            'canceled_at'          => $now->timestamp,
            'cancel_at_period_end' => $atPeriodEnd,
            'current_period_end'   => $now->copy()->addMonth()->timestamp,
        ];
    }

    public function createInvoiceItem(string $customerId, float $amount, string $currency = 'USD', array $options = []): array
    {
        return [
            'id'          => 'ii_mock_' . uniqid(),
            'customer'    => $customerId,
            'amount'      => $amount * 100, // Stripe uses cents
            'currency'    => strtolower($currency),
            'description' => $options['description'] ?? 'Usage charges',
            'metadata'    => $options['metadata']    ?? [],
        ];
    }

    public function createInvoice(string $customerId, array $options = []): array
    {
        $now = Carbon::now();

        return [
            'id'          => 'in_mock_' . uniqid(),
            'customer'    => $customerId,
            'status'      => 'draft',
            'amount_due'  => 2000, // $20.00 in cents
            'amount_paid' => 0,
            'currency'    => 'usd',
            'created'     => $now->timestamp,
            'due_date'    => $now->copy()->addDays(30)->timestamp,
        ];
    }

    public function finalizeInvoice(string $invoiceId): array
    {
        return [
            'id'           => $invoiceId,
            'status'       => 'open',
            'finalized_at' => Carbon::now()->timestamp,
        ];
    }

    public function payInvoice(string $invoiceId): array
    {
        return [
            'id'             => $invoiceId,
            'status'         => 'paid',
            'paid_at'        => Carbon::now()->timestamp,
            'payment_intent' => 'pi_mock_' . uniqid(),
        ];
    }

    public function retrieveInvoice(string $invoiceId): array
    {
        $now = Carbon::now();

        return [
            'id'          => $invoiceId,
            'status'      => 'paid',
            'amount_due'  => 2000,
            'amount_paid' => 2000,
            'currency'    => 'usd',
            'created'     => $now->timestamp,
            'paid_at'     => $now->timestamp,
        ];
    }

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array
    {
        // Mock webhook event - in real implementation this would validate signature
        return json_decode($payload, true);
    }

    public function validateWebhookSignature(string $payload, string $signature, ?string $secret = null): ?array
    {
        // Mock validation - return parsed payload for testing
        return json_decode($payload, true);
    }

    public function confirmSetupIntent(string $setupIntentId): array
    {
        return [
            'id'             => $setupIntentId,
            'status'         => 'succeeded',
            'payment_method' => 'pm_mock_' . uniqid(),
            'customer'       => 'cus_mock_' . uniqid(),
        ];
    }

    public function createCharge(string $customerId, int $amountInCents, string $currency, string $description): array
    {
        // Simulate different charge outcomes based on amount
        $status = 'succeeded';
        $error  = null;

        // Simulate failure for specific test amounts
        if ($amountInCents === 99999) {
            $status = 'failed';
            $error  = 'Card declined';
        }

        return [
            'id'          => 'ch_mock_' . uniqid(),
            'customer'    => $customerId,
            'amount'      => $amountInCents,
            'currency'    => strtolower($currency),
            'description' => $description,
            'status'      => $status,
            'error'       => $error,
            'created'     => Carbon::now()->timestamp,
        ];
    }
}
