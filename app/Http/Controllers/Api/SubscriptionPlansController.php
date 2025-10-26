<?php

namespace App\Http\Controllers\Api;

use App\Models\Billing\SubscriptionPlan;
use App\Repositories\Billing\SubscriptionPlanRepository;
use App\Resources\Billing\SubscriptionPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class SubscriptionPlansController extends ActionController
{
    public static ?string $repo = SubscriptionPlanRepository::class;

    public static ?string $resource = SubscriptionPlanResource::class;

    /**
     * List all active subscription plans
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'include_inactive' => 'nullable|boolean',
        ]);

        $plans = app(SubscriptionPlanRepository::class)->getAvailablePlans(
            $request->boolean('include_inactive', false)
        );

        return response()->json([
            'plans' => SubscriptionPlanResource::collection($plans),
        ]);
    }

    /**
     * Get a specific subscription plan
     */
    public function show(SubscriptionPlan $plan): JsonResponse
    {
        return response()->json([
            'plan' => SubscriptionPlanResource::make($plan),
        ]);
    }

    /**
     * Compare subscription plans
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:subscription_plans,id',
        ]);

        $planIds = $request->input('plan_ids');

        if ($planIds) {
            $plans = SubscriptionPlan::whereIn('id', $planIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } else {
            $plans = app(SubscriptionPlanRepository::class)->getAvailablePlans();
        }

        return response()->json([
            'plans'      => SubscriptionPlanResource::collection($plans),
            'comparison' => $this->buildComparisonMatrix($plans),
        ]);
    }

    /**
     * Build a comparison matrix for plans
     */
    protected function buildComparisonMatrix($plans): array
    {
        $features = [];

        foreach ($plans as $plan) {
            if (is_array($plan->features)) {
                $features = array_merge($features, array_keys($plan->features));
            }
        }

        $features = array_unique($features);
        $matrix   = [];

        foreach ($features as $feature) {
            $row = ['feature' => $feature];

            foreach ($plans as $plan) {
                $row[$plan->slug] = $plan->features[$feature] ?? false;
            }

            $matrix[] = $row;
        }

        return $matrix;
    }
}
