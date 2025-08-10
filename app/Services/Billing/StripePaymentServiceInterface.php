<?php

namespace App\Services\Billing;

use App\Models\Team\Team;

interface StripePaymentServiceInterface
{
    /**
     * Create a Stripe customer
     */
    public function createCustomer(Team $team, array $customerData = []): array;

    /**
     * Create a setup intent for adding payment methods
     */
    public function createSetupIntent(string $customerId, array $options = []): array;

    /**
     * Attach a payment method to a customer
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array;

    /**
     * Detach a payment method from a customer
     */
    public function detachPaymentMethod(string $paymentMethodId): array;

    /**
     * Create a subscription
     */
    public function createSubscription(string $customerId, string $priceId, array $options = []): array;

    /**
     * Update a subscription
     */
    public function updateSubscription(string $subscriptionId, array $options = []): array;

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array;

    /**
     * Create an invoice item for usage charges
     */
    public function createInvoiceItem(string $customerId, float $amount, string $currency = 'USD', array $options = []): array;

    /**
     * Create an invoice
     */
    public function createInvoice(string $customerId, array $options = []): array;

    /**
     * Finalize an invoice
     */
    public function finalizeInvoice(string $invoiceId): array;

    /**
     * Pay an invoice
     */
    public function payInvoice(string $invoiceId): array;

    /**
     * Retrieve an invoice
     */
    public function retrieveInvoice(string $invoiceId): array;

    /**
     * Construct webhook event from payload
     */
    public function constructWebhookEvent(string $payload, string $signature, string $secret): array;

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature, string $secret = null): ?array;
    
    /**
     * Confirm a setup intent
     */
    public function confirmSetupIntent(string $setupIntentId): array;
    
    /**
     * Create a charge
     */
    public function createCharge(string $customerId, int $amountInCents, string $currency, string $description): array;
}