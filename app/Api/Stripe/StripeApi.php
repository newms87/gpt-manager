<?php

namespace App\Api\Stripe;

use Newms87\Danx\Api\BearerTokenApi;
use Newms87\Danx\Exceptions\ApiException;
use Newms87\Danx\Exceptions\ApiRequestException;

class StripeApi extends BearerTokenApi
{
    public static string $serviceName = 'Stripe';

    public function __construct()
    {
        $this->baseApiUrl = config('stripe.api_url', 'https://api.stripe.com/v1/');
        $this->token = config('services.stripe.secret');
        
        if (!$this->token) {
            throw new ApiException('Stripe API key not configured');
        }
    }

    public function getRequestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Create a customer
     */
    public function createCustomer(array $customerData): array
    {
        return $this->post('customers', $customerData)->json();
    }

    /**
     * Create a setup intent
     */
    public function createSetupIntent(array $data): array
    {
        return $this->post('setup_intents', $data)->json();
    }

    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return $this->post("payment_methods/$paymentMethodId/attach", [
            'customer' => $customerId
        ])->json();
    }

    /**
     * Detach payment method
     */
    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return $this->post("payment_methods/$paymentMethodId/detach")->json();
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $subscriptionData): array
    {
        return $this->post('subscriptions', $subscriptionData)->json();
    }

    /**
     * Update subscription
     */
    public function updateSubscription(string $subscriptionId, array $data): array
    {
        return $this->post("subscriptions/$subscriptionId", $data)->json();
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscriptionId, array $options = []): array
    {
        return $this->delete("subscriptions/$subscriptionId", $options)->json();
    }

    /**
     * Create invoice item
     */
    public function createInvoiceItem(array $itemData): array
    {
        return $this->post('invoiceitems', $itemData)->json();
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $invoiceData): array
    {
        return $this->post('invoices', $invoiceData)->json();
    }

    /**
     * Finalize invoice
     */
    public function finalizeInvoice(string $invoiceId): array
    {
        return $this->post("invoices/$invoiceId/finalize")->json();
    }

    /**
     * Pay invoice
     */
    public function payInvoice(string $invoiceId): array
    {
        return $this->post("invoices/$invoiceId/pay")->json();
    }

    /**
     * Retrieve invoice
     */
    public function retrieveInvoice(string $invoiceId): array
    {
        return $this->get("invoices/$invoiceId")->json();
    }

    /**
     * Create a charge
     */
    public function createCharge(array $chargeData): array
    {
        return $this->post('charges', $chargeData)->json();
    }

    /**
     * Confirm setup intent
     */
    public function confirmSetupIntent(string $setupIntentId, array $data = []): array
    {
        return $this->post("setup_intents/$setupIntentId/confirm", $data)->json();
    }
}