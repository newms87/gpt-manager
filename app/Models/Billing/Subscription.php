<?php

namespace App\Models\Billing;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class Subscription extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'monthly_amount'       => 'decimal:2',
            'yearly_amount'        => 'decimal:2',
            'trial_ends_at'        => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end'   => 'datetime',
            'canceled_at'          => 'datetime',
            'ends_at'              => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'metadata'             => 'json',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function billingHistory(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }

    public function validate(): static
    {
        if (!$this->team_id) {
            throw new ValidationError('Subscription must be associated with a team', 400);
        }

        if (!$this->subscription_plan_id) {
            throw new ValidationError('Subscription must be associated with a plan', 400);
        }

        // Check for existing active subscription for the team
        $query = Subscription::where('team_id', $this->team_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', ['active', 'trialing']);

        if ($existingSubscription = $query->first()) {
            throw new ValidationError('Team already has an active subscription', 409);
        }

        return $this;
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isCanceled(): bool
    {
        return !is_null($this->canceled_at);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trialing' &&
            $this->trial_ends_at            &&
            $this->trial_ends_at->isFuture();
    }

    public function getCurrentAmount(): float
    {
        return $this->billing_cycle === 'yearly'
            ? (float)($this->yearly_amount ?? 0)
            : (float)($this->monthly_amount ?? 0);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
