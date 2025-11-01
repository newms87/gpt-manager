<?php

namespace App\Services\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Traits\HasDebugLogging;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;

class BillingService
{
    use HasDebugLogging;

    public function __construct(
        protected StripePaymentServiceInterface $stripeService
    ) {
    }

    /**
     * Setup billing for a team by creating Stripe customer
     */
    public function setupTeamBilling(Team $team, array $customerData = []): Team
    {
        $this->validateTeamAccess($team);

        if ($team->stripe_customer_id) {
            throw new ValidationError('Team already has billing setup', 400);
        }

        return DB::transaction(function () use ($team, $customerData) {
            $customer = $this->stripeService->createCustomer($team, $customerData);

            $team->update(['stripe_customer_id' => $customer['id']]);

            return $team->fresh();
        });
    }

    /**
     * Create setup intent for adding payment methods
     */
    public function createSetupIntent(Team $team): array
    {
        $this->validateTeamAccess($team);

        if (!$team->stripe_customer_id) {
            throw new ValidationError('Team billing not setup. Call setupTeamBilling first.', 400);
        }

        return $this->stripeService->createSetupIntent($team->stripe_customer_id);
    }

    /**
     * Add payment method to team
     */
    public function addPaymentMethod(Team $team, string $paymentMethodId): PaymentMethod
    {
        $this->validateTeamAccess($team);

        if (!$team->stripe_customer_id) {
            throw new ValidationError('Team billing not setup', 400);
        }

        return DB::transaction(function () use ($team, $paymentMethodId) {
            $stripePaymentMethod = $this->stripeService->attachPaymentMethod($paymentMethodId, $team->stripe_customer_id);

            $paymentMethod = new PaymentMethod([
                'team_id'                  => $team->id,
                'stripe_payment_method_id' => $stripePaymentMethod['id'],
                'type'                     => $stripePaymentMethod['type'],
                'card_brand'               => $stripePaymentMethod['card']['brand']     ?? null,
                'card_last_four'           => $stripePaymentMethod['card']['last4']     ?? null,
                'card_exp_month'           => $stripePaymentMethod['card']['exp_month'] ?? null,
                'card_exp_year'            => $stripePaymentMethod['card']['exp_year']  ?? null,
            ]);

            $paymentMethod->validate();
            $paymentMethod->save();

            // If this is the first payment method, make it default
            if (PaymentMethod::forTeam($team->id)->count() === 1) {
                $paymentMethod->makeDefault();
            }

            return $paymentMethod->fresh();
        });
    }

    /**
     * Remove payment method from team
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $this->validatePaymentMethodAccess($paymentMethod);

        return DB::transaction(function () use ($paymentMethod) {
            $this->stripeService->detachPaymentMethod($paymentMethod->stripe_payment_method_id);

            // If this was the default, make another one default
            if ($paymentMethod->is_default) {
                $nextPaymentMethod = PaymentMethod::forTeam($paymentMethod->team_id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->first();

                if ($nextPaymentMethod) {
                    $nextPaymentMethod->makeDefault();
                }
            }

            return $paymentMethod->delete();
        });
    }

    /**
     * Subscribe team to a plan
     */
    public function subscribeTeamToPlan(Team $team, SubscriptionPlan $plan, array $options = []): Subscription
    {
        $this->validateTeamAccess($team);

        if (!$team->stripe_customer_id) {
            throw new ValidationError('Team billing not setup', 400);
        }

        if (!$plan->is_active) {
            throw new ValidationError('Subscription plan is not active', 400);
        }

        // Check for existing active subscription
        $existingSubscription = Subscription::forTeam($team->id)->active()->first();
        if ($existingSubscription) {
            throw new ValidationError('Team already has an active subscription', 409);
        }

        return DB::transaction(function () use ($team, $plan, $options) {
            $stripeSubscription = $this->stripeService->createSubscription(
                $team->stripe_customer_id,
                $plan->stripe_price_id,
                $options
            );

            $subscription = new Subscription([
                'team_id'                => $team->id,
                'subscription_plan_id'   => $plan->id,
                'stripe_customer_id'     => $team->stripe_customer_id,
                'stripe_subscription_id' => $stripeSubscription['id'],
                'status'                 => $stripeSubscription['status'],
                'billing_cycle'          => $options['billing_cycle'] ?? 'monthly',
                'monthly_amount'         => $plan->monthly_price,
                'yearly_amount'          => $plan->yearly_price,
                'trial_ends_at'          => $stripeSubscription['trial_end'] ?
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                'current_period_end'   => \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]);

            $subscription->validate();
            $subscription->save();

            return $subscription->fresh(['subscriptionPlan', 'team']);
        });
    }

    /**
     * Change subscription plan
     */
    public function changeSubscriptionPlan(Subscription $subscription, SubscriptionPlan $newPlan): Subscription
    {
        $this->validateSubscriptionAccess($subscription);

        if (!$newPlan->is_active) {
            throw new ValidationError('New subscription plan is not active', 400);
        }

        if ($subscription->subscription_plan_id === $newPlan->id) {
            throw new ValidationError('Subscription is already on this plan', 400);
        }

        return DB::transaction(function () use ($subscription, $newPlan) {
            $stripeSubscription = $this->stripeService->updateSubscription(
                $subscription->stripe_subscription_id,
                ['price_id' => $newPlan->stripe_price_id]
            );

            $subscription->update([
                'subscription_plan_id' => $newPlan->id,
                'monthly_amount'       => $newPlan->monthly_price,
                'yearly_amount'        => $newPlan->yearly_price,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                'current_period_end'   => \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]);

            return $subscription->fresh(['subscriptionPlan', 'team']);
        });
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        $this->validateSubscriptionAccess($subscription);

        if ($subscription->isCanceled()) {
            throw new ValidationError('Subscription is already canceled', 400);
        }

        return DB::transaction(function () use ($subscription, $atPeriodEnd) {
            $stripeSubscription = $this->stripeService->cancelSubscription(
                $subscription->stripe_subscription_id,
                $atPeriodEnd
            );

            $subscription->update([
                'status'      => $stripeSubscription['status'],
                'canceled_at' => $stripeSubscription['canceled_at'] ?
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['canceled_at']) : now(),
                'ends_at' => $atPeriodEnd && isset($stripeSubscription['current_period_end']) ?
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']) : now(),
            ]);

            return $subscription->fresh(['subscriptionPlan', 'team']);
        });
    }

    /**
     * Process usage charges for a team
     */
    public function processUsageCharges(Team $team, float $amount, string $description, array $metadata = []): BillingHistory
    {
        $this->validateTeamAccess($team);

        if (!$team->stripe_customer_id) {
            throw new ValidationError('Team billing not setup', 400);
        }

        if ($amount <= 0) {
            throw new ValidationError('Usage charge amount must be greater than 0', 400);
        }

        return DB::transaction(function () use ($team, $amount, $description, $metadata) {
            // Create invoice item for usage
            $invoiceItem = $this->stripeService->createInvoiceItem(
                $team->stripe_customer_id,
                $amount,
                'USD',
                [
                    'description' => $description,
                    'metadata'    => $metadata,
                ]
            );

            // Create billing history record
            $billingHistory = new BillingHistory([
                'team_id'      => $team->id,
                'type'         => 'usage_charge',
                'status'       => 'pending',
                'amount'       => $amount,
                'total_amount' => $amount,
                'currency'     => 'USD',
                'description'  => $description,
                'metadata'     => array_merge($metadata, [
                    'stripe_invoice_item_id' => $invoiceItem['id'],
                ]),
            ]);

            $billingHistory->validate();
            $billingHistory->save();

            return $billingHistory->fresh();
        });
    }

    protected function validateTeamAccess(Team $team): void
    {
        $currentTeam = team();
        if (!$currentTeam || $team->id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this team', 403);
        }
    }

    protected function validateSubscriptionAccess(Subscription $subscription): void
    {
        $currentTeam = team();
        if (!$currentTeam || $subscription->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this subscription', 403);
        }
    }

    protected function validatePaymentMethodAccess(PaymentMethod $paymentMethod): void
    {
        $currentTeam = team();
        if (!$currentTeam || $paymentMethod->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this payment method', 403);
        }
    }

    /**
     * Create subscription for team
     */
    public function createSubscription(Team $team, int $planId, string $billingPeriod): Subscription
    {
        $this->validateTeamAccess($team);

        $plan = SubscriptionPlan::findOrFail($planId);

        if (!$plan->is_active) {
            throw new ValidationError('Subscription plan is not active', 400);
        }

        $existingSubscription = Subscription::where('team_id', $team->id)
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            throw new ValidationError('Team already has an active subscription', 409);
        }

        return DB::transaction(function () use ($team, $plan, $billingPeriod) {
            $priceId = $plan->stripe_price_id;

            $stripeSubscription = $this->stripeService->createSubscription(
                $team->stripe_customer_id,
                $priceId ?? 'mock_price_id'
            );

            $subscription = new Subscription([
                'team_id'                => $team->id,
                'subscription_plan_id'   => $plan->id,
                'stripe_subscription_id' => $stripeSubscription['id'],
                'status'                 => 'active',
                'billing_cycle'          => $billingPeriod,
                'monthly_amount'         => $plan->monthly_price,
                'yearly_amount'          => $plan->yearly_price,
                'current_period_start'   => now(),
                'current_period_end'     => $billingPeriod === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);

            $subscription->save();

            return $subscription->load('subscriptionPlan');
        });
    }

    /**
     * Cancel team subscription
     */
    public function cancelTeamSubscription(Team $team): bool
    {
        $this->validateTeamAccess($team);

        $subscription = Subscription::where('team_id', $team->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return false;
        }

        return DB::transaction(function () use ($subscription) {
            $this->stripeService->cancelSubscription($subscription->stripe_subscription_id);

            $subscription->update([
                'status'               => 'canceled',
                'cancel_at_period_end' => true,
                'canceled_at'          => now(),
            ]);

            return true;
        });
    }

    /**
     * Confirm setup intent
     */
    public function confirmSetupIntent(Team $team, string $setupIntentId): array
    {
        $this->validateTeamAccess($team);

        $result = $this->stripeService->confirmSetupIntent($setupIntentId);

        if ($result['status'] === 'succeeded' && isset($result['payment_method'])) {
            $paymentMethod            = $this->addPaymentMethod($team, $result['payment_method']);
            $result['payment_method'] = $paymentMethod;
        }

        return $result;
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $payload, ?string $signature): ?array
    {
        if (!$signature) {
            return null;
        }

        return $this->stripeService->validateWebhookSignature($payload, $signature);
    }

    /**
     * Process successful payment
     */
    public function processSuccessfulPayment(array $paymentIntent): void
    {
        Log::info('Processing successful payment', ['payment_intent' => $paymentIntent['id']]);

        // TODO: Update billing history record
    }

    /**
     * Process failed payment
     */
    public function processFailedPayment(array $paymentIntent): void
    {
        static::logWarning('Processing failed payment', ['payment_intent' => $paymentIntent['id']]);

        // TODO: Update billing history record and notify team
    }

    /**
     * Sync subscription from Stripe
     */
    public function syncSubscriptionFromStripe(array $stripeSubscription): void
    {
        $team = Team::where('stripe_customer_id', $stripeSubscription['customer'])->first();

        if (!$team) {
            static::logWarning('Team not found for Stripe customer', ['customer_id' => $stripeSubscription['customer']]);

            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();

        if ($subscription) {
            $subscription->update([
                'status'               => $stripeSubscription['status'],
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                'current_period_end'   => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]);
        }
    }

    /**
     * Handle subscription cancelled
     */
    public function handleSubscriptionCancelled(array $stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();

        if ($subscription) {
            $subscription->update([
                'status'      => 'canceled',
                'canceled_at' => now(),
            ]);
        }
    }

    /**
     * Record invoice payment
     */
    public function recordInvoicePayment(array $invoice, string $status): void
    {
        $team = Team::where('stripe_customer_id', $invoice['customer'])->first();

        if (!$team) {
            return;
        }

        $amount      = $invoice['amount_paid']                             / 100;
        $totalAmount = ($invoice['amount_due'] ?? $invoice['amount_paid']) / 100;

        // For failed payments, use the amount due instead of amount paid
        if ($status !== 'succeeded' && $amount <= 0) {
            $amount = $totalAmount;
        }

        $billingHistory = new BillingHistory([
            'team_id'           => $team->id,
            'type'              => 'invoice', // Use 'invoice' instead of 'subscription_payment'
            'description'       => $invoice['description'] ?? 'Subscription payment',
            'amount'            => $amount,
            'total_amount'      => $totalAmount,
            'currency'          => strtoupper($invoice['currency']),
            'status'            => $status === 'succeeded' ? 'paid' : 'open', // Use 'open' for failed instead of 'failed'
            'stripe_invoice_id' => $invoice['id'],
            'invoice_url'       => $invoice['invoice_pdf'] ?? null,
            'billing_date'      => Carbon::createFromTimestamp($invoice['created']),
            'metadata'          => [
                'invoice_number'  => $invoice['number'],
                'subscription_id' => $invoice['subscription'],
            ],
        ]);

        $billingHistory->save();
    }

    /**
     * Sync payment method from Stripe
     */
    public function syncPaymentMethodFromStripe(array $stripePaymentMethod): void
    {
        $team = Team::where('stripe_customer_id', $stripePaymentMethod['customer'])->first();

        if (!$team) {
            return;
        }

        $paymentMethod = PaymentMethod::where('stripe_payment_method_id', $stripePaymentMethod['id'])->first();

        if (!$paymentMethod) {
            $paymentMethod = new PaymentMethod([
                'team_id'                  => $team->id,
                'stripe_payment_method_id' => $stripePaymentMethod['id'],
                'type'                     => $stripePaymentMethod['type'],
                'card_brand'               => $stripePaymentMethod['card']['brand']     ?? null,
                'card_last_four'           => $stripePaymentMethod['card']['last4']     ?? null,
                'card_exp_month'           => $stripePaymentMethod['card']['exp_month'] ?? null,
                'card_exp_year'            => $stripePaymentMethod['card']['exp_year']  ?? null,
            ]);

            $paymentMethod->save();
        }
    }

    /**
     * Remove payment method by Stripe ID
     */
    public function removePaymentMethodByStripeId(string $stripePaymentMethodId): void
    {
        $paymentMethod = PaymentMethod::where('stripe_payment_method_id', $stripePaymentMethodId)->first();

        if ($paymentMethod) {
            $paymentMethod->delete();
        }
    }
}
