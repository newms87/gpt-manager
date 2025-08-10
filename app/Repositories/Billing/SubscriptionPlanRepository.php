<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Repositories\ActionRepository;

class SubscriptionPlanRepository extends ActionRepository
{
    public static string $model = SubscriptionPlan::class;

    public function query(): Builder
    {
        return parent::query()->ordered();
    }

    public function applyAction(string $action, SubscriptionPlan|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createPlan($data),
            'update' => $this->updatePlan($model, $data),
            'activate' => $this->activatePlan($model),
            'deactivate' => $this->deactivatePlan($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function getActivePlans(): array
    {
        return SubscriptionPlan::active()->ordered()->get()->toArray();
    }

    public function getAvailablePlans(bool $includeInactive = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = SubscriptionPlan::query();
        
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        
        return $query->orderBy('sort_order')->get();
    }

    protected function createPlan(array $data): SubscriptionPlan
    {
        $plan = new SubscriptionPlan($data);
        $plan->validate();
        $plan->save();
        
        return $plan->fresh();
    }

    protected function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->fill($data);
        $plan->validate();
        $plan->save();
        
        return $plan->fresh();
    }

    protected function activatePlan(SubscriptionPlan $plan): SubscriptionPlan
    {
        $plan->update(['is_active' => true]);
        return $plan->fresh();
    }

    protected function deactivatePlan(SubscriptionPlan $plan): SubscriptionPlan
    {
        $plan->update(['is_active' => false]);
        return $plan->fresh();
    }
}