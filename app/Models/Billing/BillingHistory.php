<?php

namespace App\Models\Billing;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class BillingHistory extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'billing_history';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'tax_amount'   => 'decimal:2',
            'total_amount' => 'decimal:2',
            'line_items'   => 'json',
            'period_start' => 'datetime',
            'period_end'   => 'datetime',
            'due_date'     => 'datetime',
            'paid_at'      => 'datetime',
            'billing_date' => 'datetime',
            'metadata'     => 'json',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function validate(): static
    {
        if (!$this->team_id) {
            throw new ValidationError('Billing history must be associated with a team', 400);
        }

        if (!$this->type) {
            throw new ValidationError('Billing history type is required', 400);
        }

        if (!in_array($this->type, ['invoice', 'payment', 'refund', 'usage_charge'])) {
            throw new ValidationError('Invalid billing history type', 400);
        }

        if (!$this->status) {
            throw new ValidationError('Billing history status is required', 400);
        }

        $validStatuses = match ($this->type) {
            'invoice'      => ['draft', 'open', 'paid', 'void', 'overdue'],
            'payment'      => ['pending', 'succeeded', 'failed', 'canceled'],
            'refund'       => ['pending', 'succeeded', 'failed', 'canceled'],
            'usage_charge' => ['pending', 'processed', 'failed'],
        };

        if (!in_array($this->status, $validStatuses)) {
            throw new ValidationError("Invalid status '{$this->status}' for type '{$this->type}'", 400);
        }

        if ($this->amount <= 0) {
            throw new ValidationError('Amount must be greater than 0', 400);
        }

        if ($this->total_amount <= 0) {
            throw new ValidationError('Total amount must be greater than 0', 400);
        }

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->type === 'invoice' && $this->status === 'paid' && !is_null($this->paid_at);
    }

    public function isOverdue(): bool
    {
        return $this->type === 'invoice' &&
            $this->status  === 'overdue' &&
            $this->due_date              &&
            $this->due_date->isPast();
    }

    public function getFormattedAmount(): string
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->where('due_date', '<', now());
    }
}
