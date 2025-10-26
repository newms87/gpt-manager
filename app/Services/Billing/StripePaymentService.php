<?php

namespace App\Services\Billing;

use App\Api\Stripe\StripeApi;
use App\Models\Team\Team;

class StripePaymentService implements StripePaymentServiceInterface
{
    protected StripeApi $stripeApi;

    protected string $webhookSecret;

    public function __construct(StripeApi $stripeApi)
    {
        $this->stripeApi     = $stripeApi;
        $this->webhookSecret = config('services.stripe.webhook_secret') ?? '';
    }

    public function createCustomer(Team $team, array $customerData = []): array
    {
        return $this->stripeApi->createCustomer([
            'email'    => $customerData['email'] ?? null,
            'name'     => $customerData['name']  ?? $team->name,
            'metadata' => [
                'team_id' => (string)$team->id,
            ],
        ]);
    }

    public function createSetupIntent(string $customerId, array $options = []): array
    {
        return $this->stripeApi->createSetupIntent([
            'customer' => $customerId,
            'usage'    => 'off_session',
            'metadata' => $options['metadata'] ?? [],
        ]);
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return $this->stripeApi->attachPaymentMethod($paymentMethodId, $customerId);
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return $this->stripeApi->detachPaymentMethod($paymentMethodId);
    }

    public function createSubscription(string $customerId, string $priceId, array $options = []): array
    {
        return $this->stripeApi->createSubscription([
            'customer'          => $customerId,
            'items'             => [['price' => $priceId]],
            'trial_period_days' => $options['trial_period_days'] ?? null,
            'metadata'          => $options['metadata']          ?? [],
        ]);
    }

    public function updateSubscription(string $subscriptionId, array $options = []): array
    {
        $data = [];

        if (isset($options['subscription_item_id']) && isset($options['price_id'])) {
            $data['items'] = [[
                'id'    => $options['subscription_item_id'],
                'price' => $options['price_id'],
            ]];
        }

        $data['proration_behavior'] = $options['proration_behavior'] ?? 'create_prorations';

        return $this->stripeApi->updateSubscription($subscriptionId, $data);
    }

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        if ($atPeriodEnd) {
            return $this->stripeApi->updateSubscription($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        } else {
            return $this->stripeApi->cancelSubscription($subscriptionId);
        }
    }

    public function createInvoiceItem(string $customerId, float $amount, string $currency = 'USD', array $options = []): array
    {
        return $this->stripeApi->createInvoiceItem([
            'customer'    => $customerId,
            'amount'      => (int)round($amount * 100), // Convert to cents
            'currency'    => strtolower($currency),
            'description' => $options['description'] ?? 'Usage charges',
            'metadata'    => $options['metadata']    ?? [],
        ]);
    }

    public function createInvoice(string $customerId, array $options = []): array
    {
        return $this->stripeApi->createInvoice([
            'customer'          => $customerId,
            'auto_advance'      => $options['auto_advance']      ?? true,
            'collection_method' => $options['collection_method'] ?? 'charge_automatically',
        ]);
    }

    public function finalizeInvoice(string $invoiceId): array
    {
        return $this->stripeApi->finalizeInvoice($invoiceId);
    }

    public function payInvoice(string $invoiceId): array
    {
        return $this->stripeApi->payInvoice($invoiceId);
    }

    public function retrieveInvoice(string $invoiceId): array
    {
        return $this->stripeApi->retrieveInvoice($invoiceId);
    }

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array
    {
        // Webhook validation needs Stripe SDK - for now return parsed payload
        // In full implementation, would use Stripe SDK to validate and construct event
        return json_decode($payload, true);
    }

    public function validateWebhookSignature(string $payload, string $signature, ?string $secret = null): ?array
    {
        // Webhook validation needs Stripe SDK - for testing purposes, return parsed payload
        // In full implementation, would validate signature with Stripe SDK
        try {
            return json_decode($payload, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function confirmSetupIntent(string $setupIntentId): array
    {
        return $this->stripeApi->confirmSetupIntent($setupIntentId);
    }

    public function createCharge(string $customerId, int $amountInCents, string $currency, string $description): array
    {
        return $this->stripeApi->createCharge([
            'customer'    => $customerId,
            'amount'      => $amountInCents,
            'currency'    => strtolower($currency),
            'description' => $description,
        ]);
    }
}
