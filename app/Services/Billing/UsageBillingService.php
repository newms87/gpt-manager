<?php

namespace App\Services\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Billing\Subscription;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Repositories\Billing\BillingHistoryRepository;
use App\Repositories\Billing\SubscriptionRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsageBillingService
{
    public function __construct(
        protected StripePaymentServiceInterface $stripeService,
        protected BillingService $billingService,
        protected BillingHistoryRepository $billingHistoryRepository,
        protected SubscriptionRepository $subscriptionRepository
    ) {
    }

    /**
     * Process daily usage billing for all teams
     */
    public function processDailyBilling(): void
    {
        Log::info('Starting daily usage billing process');

        $teams = $this->getTeamsForBilling();

        foreach ($teams as $team) {
            try {
                $this->processTeamBilling($team);
            } catch (\Exception $e) {
                Log::error('Failed to process billing for team', [
                    'team_id' => $team->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed daily usage billing process');
    }

    /**
     * Process billing for a specific team
     */
    public function processTeamBilling(Team $team): void
    {
        if (!$this->shouldChargeTeam($team)) {
            return;
        }

        $usage = $this->calculateDailyUsage($team);

        if ($usage['total_cost'] <= 0) {
            Log::info('No usage charges for team', ['team_id' => $team->id]);

            return;
        }

        DB::transaction(function () use ($team, $usage) {
            $charge = $this->createUsageCharge($team, $usage);

            if ($charge['status'] === 'succeeded') {
                $this->recordSuccessfulCharge($team, $usage, $charge);
            } else {
                $this->recordFailedCharge($team, $usage, $charge);
            }
        });
    }

    /**
     * Get teams that should be billed today
     */
    protected function getTeamsForBilling(): Collection
    {
        return Team::whereNotNull('stripe_customer_id')
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'active')
                    ->where('cancel_at_period_end', false);
            })
            ->whereHas('paymentMethods', function ($query) {
                $query->where('is_default', true);
            })
            ->get();
    }

    /**
     * Check if team should be charged
     */
    protected function shouldChargeTeam(Team $team): bool
    {
        $subscription = $this->subscriptionRepository->getActiveSubscription($team->id);

        if (!$subscription) {
            return false;
        }

        // Load the subscription plan relationship
        $subscription->load('subscriptionPlan');
        $plan = $subscription->subscriptionPlan;

        if (!$plan || !isset($plan->usage_limits['usage_based_billing'])) {
            return false;
        }

        return $plan->usage_limits['usage_based_billing'] === true;
    }

    /**
     * Calculate daily usage for a team
     */
    public function calculateDailyUsage(Team $team): array
    {
        $startDate = Carbon::now()->subDay()->startOfDay();
        $endDate   = Carbon::now()->subDay()->endOfDay();

        $usage = UsageEvent::where('team_id', $team->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as event_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(input_cost) as total_input_cost,
                SUM(output_cost) as total_output_cost,
                SUM(request_count) as total_requests,
                SUM(data_volume) as total_data_volume
            ')
            ->first();

        $totalCost = ($usage->total_input_cost ?? 0) + ($usage->total_output_cost ?? 0);

        return [
            'date'                => $startDate->toDateString(),
            'event_count'         => $usage->event_count         ?? 0,
            'total_input_tokens'  => $usage->total_input_tokens  ?? 0,
            'total_output_tokens' => $usage->total_output_tokens ?? 0,
            'total_tokens'        => ($usage->total_input_tokens ?? 0) + ($usage->total_output_tokens ?? 0),
            'total_input_cost'    => $usage->total_input_cost  ?? 0,
            'total_output_cost'   => $usage->total_output_cost ?? 0,
            'total_cost'          => $totalCost,
            'total_requests'      => $usage->total_requests    ?? 0,
            'total_data_volume'   => $usage->total_data_volume ?? 0,
        ];
    }

    /**
     * Get current usage stats for a team
     */
    public function getCurrentUsageStats(Team $team): array
    {
        $currentMonth = Carbon::now()->startOfMonth();

        $monthlyUsage = UsageEvent::where('team_id', $team->id)
            ->where('created_at', '>=', $currentMonth)
            ->selectRaw('
                COUNT(*) as event_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(input_cost) as total_input_cost,
                SUM(output_cost) as total_output_cost,
                SUM(request_count) as total_requests
            ')
            ->first();

        $todayUsage = UsageEvent::where('team_id', $team->id)
            ->whereDate('created_at', Carbon::today())
            ->selectRaw('
                COUNT(*) as event_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(input_cost) as total_input_cost,
                SUM(output_cost) as total_output_cost
            ')
            ->first();

        return [
            'current_month' => [
                'period_start'   => $currentMonth->toDateString(),
                'period_end'     => Carbon::now()->endOfMonth()->toDateString(),
                'event_count'    => $monthlyUsage->event_count ?? 0,
                'total_tokens'   => ($monthlyUsage->total_input_tokens ?? 0) + ($monthlyUsage->total_output_tokens ?? 0),
                'total_cost'     => ($monthlyUsage->total_input_cost ?? 0)   + ($monthlyUsage->total_output_cost ?? 0),
                'total_requests' => $monthlyUsage->total_requests ?? 0,
            ],
            'today' => [
                'date'         => Carbon::today()->toDateString(),
                'event_count'  => $todayUsage->event_count ?? 0,
                'total_tokens' => ($todayUsage->total_input_tokens ?? 0) + ($todayUsage->total_output_tokens ?? 0),
                'total_cost'   => ($todayUsage->total_input_cost ?? 0)   + ($todayUsage->total_output_cost ?? 0),
            ],
        ];
    }

    /**
     * Create usage charge via Stripe
     */
    protected function createUsageCharge(Team $team, array $usage): array
    {
        $amountInCents = (int)round($usage['total_cost'] * 100);

        if ($amountInCents < 50) {
            // Stripe minimum charge is $0.50
            return [
                'status' => 'skipped',
                'reason' => 'Below minimum charge threshold',
            ];
        }

        return $this->stripeService->createCharge(
            $team->stripe_customer_id,
            $amountInCents,
            'USD',
            "Usage charges for {$usage['date']}"
        );
    }

    /**
     * Record successful charge
     */
    protected function recordSuccessfulCharge(Team $team, array $usage, array $charge): void
    {
        $billingHistory = new BillingHistory([
            'team_id'          => $team->id,
            'type'             => 'usage_charge',
            'description'      => "Daily usage charges for {$usage['date']}",
            'amount'           => $usage['total_cost'],
            'total_amount'     => $usage['total_cost'], // Required field in migration
            'currency'         => 'USD',
            'status'           => 'processed',
            'stripe_charge_id' => $charge['id'] ?? null,
            'billing_date'     => Carbon::parse($usage['date']),
            'metadata'         => [
                'usage_stats'    => $usage,
                'charge_details' => $charge,
            ],
        ]);

        $billingHistory->save();

        Log::info('Successfully charged team for usage', [
            'team_id' => $team->id,
            'amount'  => $usage['total_cost'],
            'date'    => $usage['date'],
        ]);
    }

    /**
     * Record failed charge
     */
    protected function recordFailedCharge(Team $team, array $usage, array $charge): void
    {
        $billingHistory = new BillingHistory([
            'team_id'          => $team->id,
            'type'             => 'usage_charge',
            'description'      => "Failed charge for usage on {$usage['date']}",
            'amount'           => $usage['total_cost'],
            'total_amount'     => $usage['total_cost'], // Required field in migration
            'currency'         => 'USD',
            'status'           => 'failed',
            'stripe_charge_id' => $charge['id'] ?? null,
            'billing_date'     => Carbon::parse($usage['date']),
            'metadata'         => [
                'usage_stats'    => $usage,
                'charge_details' => $charge,
                'error'          => $charge['error'] ?? 'Unknown error',
            ],
        ]);

        $billingHistory->save();

        Log::error('Failed to charge team for usage', [
            'team_id' => $team->id,
            'amount'  => $usage['total_cost'],
            'date'    => $usage['date'],
            'error'   => $charge['error'] ?? 'Unknown',
        ]);

        // TODO: Send notification to team about failed payment
    }

    /**
     * Generate usage summary report for a team
     */
    public function generateUsageSummary(Team $team, Carbon $startDate, Carbon $endDate): array
    {
        $usage = UsageEvent::where('team_id', $team->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                event_type,
                api_name,
                COUNT(*) as event_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(input_cost) as total_input_cost,
                SUM(output_cost) as total_output_cost,
                SUM(request_count) as total_requests
            ')
            ->groupBy('date', 'event_type', 'api_name')
            ->orderBy('date')
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'summary' => $usage->groupBy('date')->map(function ($dayUsage) {
                return [
                    'total_events' => $dayUsage->sum('event_count'),
                    'total_tokens' => $dayUsage->sum('total_input_tokens') + $dayUsage->sum('total_output_tokens'),
                    'total_cost'   => $dayUsage->sum('total_input_cost')   + $dayUsage->sum('total_output_cost'),
                    'by_type'      => $dayUsage->groupBy('event_type')->map(function ($typeUsage) {
                        return [
                            'count' => $typeUsage->sum('event_count'),
                            'cost'  => $typeUsage->sum('total_input_cost') + $typeUsage->sum('total_output_cost'),
                        ];
                    }),
                ];
            }),
        ];
    }
}
