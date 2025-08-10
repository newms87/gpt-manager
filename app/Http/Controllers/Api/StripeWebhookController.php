<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = app(BillingService::class)->validateWebhookSignature($payload, $signature);

            if (!$event) {
                Log::warning('Invalid Stripe webhook signature');

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $this->processWebhookEvent($event);

            return response()->json(['success' => true]);
        } catch(\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage(), [
                'event_type' => $request->input('type'),
                'event_id'   => $request->input('id'),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Process webhook event based on type
     */
    protected function processWebhookEvent(array $event): void
    {
        Log::info('Processing Stripe webhook', [
            'type' => $event['type'],
            'id'   => $event['id'],
        ]);

        switch($event['type']) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;

            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event['data']['object']);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event['data']['object']);
                break;

            case 'payment_method.attached':
                $this->handlePaymentMethodAttached($event['data']['object']);
                break;

            case 'payment_method.detached':
                $this->handlePaymentMethodDetached($event['data']['object']);
                break;

            default:
                Log::info('Unhandled webhook event type: ' . $event['type']);
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSucceeded(array $paymentIntent): void
    {
        app(BillingService::class)->processSuccessfulPayment($paymentIntent);
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed(array $paymentIntent): void
    {
        app(BillingService::class)->processFailedPayment($paymentIntent);
    }

    /**
     * Handle subscription created
     */
    protected function handleSubscriptionCreated(array $subscription): void
    {
        app(BillingService::class)->syncSubscriptionFromStripe($subscription);
    }

    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated(array $subscription): void
    {
        app(BillingService::class)->syncSubscriptionFromStripe($subscription);
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted(array $subscription): void
    {
        app(BillingService::class)->handleSubscriptionCancelled($subscription);
    }

    /**
     * Handle successful invoice payment
     */
    protected function handleInvoicePaymentSucceeded(array $invoice): void
    {
        app(BillingService::class)->recordInvoicePayment($invoice, 'succeeded');
    }

    /**
     * Handle failed invoice payment
     */
    protected function handleInvoicePaymentFailed(array $invoice): void
    {
        app(BillingService::class)->recordInvoicePayment($invoice, 'failed');
    }

    /**
     * Handle payment method attached
     */
    protected function handlePaymentMethodAttached(array $paymentMethod): void
    {
        app(BillingService::class)->syncPaymentMethodFromStripe($paymentMethod);
    }

    /**
     * Handle payment method detached
     */
    protected function handlePaymentMethodDetached(array $paymentMethod): void
    {
        app(BillingService::class)->removePaymentMethodByStripeId($paymentMethod['id']);
    }
}
