<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class SubscriptionRepository extends ActionRepository
{
    public static string $model = Subscription::class;

    public function query(): Builder
    {
        return parent::query()
            ->where('team_id', team()->id)
            ->with(['subscriptionPlan', 'team']);
    }

    public function applyAction(string $action, Subscription|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createSubscription($data),
            'update' => $this->updateSubscription($model, $data),
            'change-plan' => $this->changePlan($model, $data),
            'cancel' => $this->cancelSubscription($model, $data),
            'reactivate' => $this->reactivateSubscription($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function getActiveSubscriptionForTeam(): ?Subscription
    {
        return Subscription::forTeam(team()->id)->active()->first();
    }

    public function getActiveSubscription(int $teamId): ?Subscription
    {
        return Subscription::forTeam($teamId)->active()->first();
    }

    protected function createSubscription(array $data): Subscription
    {
        $this->validateTeamOwnership();
        
        $data['team_id'] = team()->id;
        
        $subscription = new Subscription($data);
        $subscription->validate();
        $subscription->save();
        
        return $subscription->fresh(['subscriptionPlan', 'team']);
    }

    protected function updateSubscription(Subscription $subscription, array $data): Subscription
    {
        $this->validateOwnership($subscription);
        
        $subscription->fill($data);
        $subscription->validate();
        $subscription->save();
        
        return $subscription->fresh(['subscriptionPlan', 'team']);
    }

    protected function changePlan(Subscription $subscription, array $data): Subscription
    {
        $this->validateOwnership($subscription);
        
        if (!isset($data['subscription_plan_id'])) {
            throw new ValidationError("New subscription plan ID is required", 400);
        }

        $newPlan = SubscriptionPlan::find($data['subscription_plan_id']);
        if (!$newPlan || !$newPlan->is_active) {
            throw new ValidationError("Invalid or inactive subscription plan", 400);
        }

        $subscription->fill([
            'subscription_plan_id' => $newPlan->id,
            'monthly_amount' => $newPlan->monthly_price,
            'yearly_amount' => $newPlan->yearly_price,
        ]);

        $subscription->validate();
        $subscription->save();
        
        return $subscription->fresh(['subscriptionPlan', 'team']);
    }

    protected function cancelSubscription(Subscription $subscription, array $data): Subscription
    {
        $this->validateOwnership($subscription);
        
        if ($subscription->isCanceled()) {
            throw new ValidationError("Subscription is already canceled", 400);
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => $data['ends_at'] ?? $subscription->current_period_end ?? now(),
        ]);
        
        return $subscription->fresh(['subscriptionPlan', 'team']);
    }

    protected function reactivateSubscription(Subscription $subscription): Subscription
    {
        $this->validateOwnership($subscription);
        
        if (!$subscription->isCanceled()) {
            throw new ValidationError("Subscription is not canceled", 400);
        }

        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            throw new ValidationError("Cannot reactivate expired subscription", 400);
        }

        $subscription->update([
            'status' => 'active',
            'canceled_at' => null,
            'ends_at' => null,
        ]);
        
        return $subscription->fresh(['subscriptionPlan', 'team']);
    }

    protected function validateOwnership(Subscription $subscription): void
    {
        $currentTeam = team();
        if (!$currentTeam || $subscription->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this subscription', 403);
        }
    }

    protected function validateTeamOwnership(): void
    {
        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 403);
        }
    }
}