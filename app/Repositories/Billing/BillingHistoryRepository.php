<?php

namespace App\Repositories\Billing;

use App\Models\Billing\BillingHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class BillingHistoryRepository extends ActionRepository
{
    public static string $model = BillingHistory::class;

    public function query(): Builder
    {
        return parent::query()
            ->where('team_id', team()->id)
            ->with(['subscription', 'subscription.subscriptionPlan'])
            ->orderBy('created_at', 'desc');
    }

    public function applyAction(string $action, BillingHistory|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createBillingRecord($data),
            'update' => $this->updateBillingRecord($model, $data),
            'mark-paid' => $this->markAsPaid($model, $data),
            'mark-failed' => $this->markAsFailed($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function getInvoicesForTeam(array $filters = []): Builder
    {
        $query = $this->query()->ofType('invoice');

        if (isset($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        if (isset($filters['from_date']) && isset($filters['to_date'])) {
            $query->inPeriod($filters['from_date'], $filters['to_date']);
        }

        return $query;
    }

    public function getOverdueInvoicesForTeam(): array
    {
        return BillingHistory::forTeam(team()->id)
            ->ofType('invoice')
            ->overdue()
            ->get()
            ->toArray();
    }

    public function getTotalPaidAmountForTeam(\Carbon\Carbon $fromDate = null, \Carbon\Carbon $toDate = null): float
    {
        $query = BillingHistory::forTeam(team()->id)
            ->paid()
            ->selectRaw('SUM(total_amount) as total');

        if ($fromDate && $toDate) {
            $query->inPeriod($fromDate, $toDate);
        }

        return $query->value('total') ?? 0.0;
    }

    public function getTeamBillingHistory(int $teamId, int $limit = 20, int $offset = 0): \Illuminate\Database\Eloquent\Collection
    {
        return BillingHistory::where('team_id', $teamId)
            ->with(['subscription', 'subscription.subscriptionPlan'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    protected function createBillingRecord(array $data): BillingHistory
    {
        $this->validateTeamOwnership();
        
        $data['team_id'] = team()->id;
        
        $billingRecord = new BillingHistory($data);
        $billingRecord->validate();
        $billingRecord->save();
        
        return $billingRecord->fresh(['subscription', 'subscription.subscriptionPlan']);
    }

    protected function updateBillingRecord(BillingHistory $billingRecord, array $data): BillingHistory
    {
        $this->validateOwnership($billingRecord);
        
        $billingRecord->fill($data);
        $billingRecord->validate();
        $billingRecord->save();
        
        return $billingRecord->fresh(['subscription', 'subscription.subscriptionPlan']);
    }

    protected function markAsPaid(BillingHistory $billingRecord, array $data): BillingHistory
    {
        $this->validateOwnership($billingRecord);
        
        if ($billingRecord->type !== 'invoice') {
            throw new ValidationError("Only invoices can be marked as paid", 400);
        }

        if ($billingRecord->status === 'paid') {
            throw new ValidationError("Invoice is already marked as paid", 400);
        }

        $billingRecord->update([
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now(),
            'metadata' => array_merge($billingRecord->metadata ?? [], $data['metadata'] ?? []),
        ]);
        
        return $billingRecord->fresh(['subscription', 'subscription.subscriptionPlan']);
    }

    protected function markAsFailed(BillingHistory $billingRecord): BillingHistory
    {
        $this->validateOwnership($billingRecord);
        
        if ($billingRecord->type === 'invoice') {
            $billingRecord->update(['status' => 'void']);
        } else {
            $billingRecord->update(['status' => 'failed']);
        }
        
        return $billingRecord->fresh(['subscription', 'subscription.subscriptionPlan']);
    }

    protected function validateOwnership(BillingHistory $billingRecord): void
    {
        $currentTeam = team();
        if (!$currentTeam || $billingRecord->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this billing record', 403);
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