<?php

namespace App\Http\Controllers\Api;

use App\Models\Billing\PaymentMethod;
use App\Repositories\Billing\BillingHistoryRepository;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Billing\SubscriptionRepository;
use App\Resources\Billing\BillingHistoryResource;
use App\Resources\Billing\PaymentMethodResource;
use App\Resources\Billing\SubscriptionResource;
use App\Services\Billing\BillingService;
use App\Services\Billing\UsageBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Newms87\Danx\Http\Controllers\ActionController;

class BillingController extends ActionController
{
    public static ?string $repo     = SubscriptionRepository::class;
    public static ?string $resource = SubscriptionResource::class;

    /**
     * Get current team subscription
     */
    public function getSubscription(): JsonResponse
    {
        $team = team();

        $subscription = app(SubscriptionRepository::class)->getActiveSubscription($team->id);

        return response()->json([
            'subscription' => $subscription ? SubscriptionResource::make($subscription) : null,
        ]);
    }

    /**
     * Create or update subscription
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'        => 'required|exists:subscription_plans,id',
            'billing_period' => 'required|in:monthly,yearly',
        ]);

        $team = team();

        $subscription = app(BillingService::class)->createSubscription(
            $team,
            $request->input('plan_id'),
            $request->input('billing_period')
        );

        return response()->json([
            'subscription' => SubscriptionResource::make($subscription),
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(): JsonResponse
    {
        $team = team();

        $cancelled = app(BillingService::class)->cancelTeamSubscription($team);

        return response()->json([
            'success' => $cancelled,
            'message' => $cancelled
                ? 'Subscription will be cancelled at the end of the current billing period'
                : 'No active subscription to cancel',
        ]);
    }

    /**
     * List team payment methods
     */
    public function listPaymentMethods(): JsonResponse
    {
        $team = team();

        $paymentMethods = app(PaymentMethodRepository::class)->getTeamPaymentMethods($team->id);

        return response()->json([
            'payment_methods' => PaymentMethodResource::collection($paymentMethods),
        ]);
    }

    /**
     * Add new payment method
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $team = team();

        try {
            $paymentMethod = app(BillingService::class)->addPaymentMethod(
                $team,
                $request->input('payment_method_id')
            );

            return response()->json([
                'payment_method' => PaymentMethodResource::make($paymentMethod),
            ], 201);
        } catch(\Newms87\Danx\Exceptions\ValidationError $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): JsonResponse
    {
        Gate::authorize('delete', $paymentMethod);

        $removed = app(BillingService::class)->removePaymentMethod($paymentMethod);

        return response()->json([
            'success' => $removed,
        ]);
    }

    /**
     * Create payment setup intent
     */
    public function createSetupIntent(): JsonResponse
    {
        $team = team();

        // Ensure team has billing setup
        if (!$team->stripe_customer_id) {
            app(BillingService::class)->setupTeamBilling($team);
            $team->refresh();
        }

        $setupIntent = app(BillingService::class)->createSetupIntent($team);

        return response()->json([
            'client_secret'   => $setupIntent['client_secret'],
            'setup_intent_id' => $setupIntent['id'] ?? null,
        ]);
    }

    /**
     * Confirm payment setup
     */
    public function confirmSetup(Request $request): JsonResponse
    {
        $request->validate([
            'setup_intent_id' => 'required|string',
        ]);

        $team = team();

        $result = app(BillingService::class)->confirmSetupIntent(
            $team,
            $request->input('setup_intent_id')
        );

        return response()->json([
            'success'        => $result['status'] === 'succeeded',
            'payment_method' => isset($result['payment_method'])
                ? PaymentMethodResource::make($result['payment_method'])
                : null,
        ]);
    }

    /**
     * Get billing history
     */
    public function getBillingHistory(Request $request): JsonResponse
    {
        $request->validate([
            'limit'  => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $team = team();

        $history = app(BillingHistoryRepository::class)->getTeamBillingHistory(
            $team->id,
            $request->input('limit', 20),
            $request->input('offset', 0)
        );

        return response()->json([
            'billing_history' => BillingHistoryResource::collection($history),
        ]);
    }

    /**
     * Get current usage stats
     */
    public function getUsageStats(): JsonResponse
    {
        $team = team();

        $stats = app(UsageBillingService::class)->getCurrentUsageStats($team);

        return response()->json([
            'usage' => $stats,
        ]);
    }
}
