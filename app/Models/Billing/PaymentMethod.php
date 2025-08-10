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

class PaymentMethod extends Model implements AuditableContract
{
    use AuditableTrait, ActionModelTrait, HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'card_exp_month' => 'integer',
            'card_exp_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function validate(): static
    {
        if (!$this->team_id) {
            throw new ValidationError("Payment method must be associated with a team", 400);
        }

        if (!$this->stripe_payment_method_id) {
            throw new ValidationError("Stripe payment method ID is required", 400);
        }

        if (!$this->type) {
            throw new ValidationError("Payment method type is required", 400);
        }

        // Validate card fields if type is card
        if ($this->type === 'card') {
            if (!$this->card_brand || !$this->card_last_four || 
                !$this->card_exp_month || !$this->card_exp_year) {
                throw new ValidationError("Card details are required for card payment methods", 400);
            }

            if ($this->card_exp_month < 1 || $this->card_exp_month > 12) {
                throw new ValidationError("Card expiration month must be between 1 and 12", 400);
            }

            if ($this->card_exp_year < date('Y')) {
                throw new ValidationError("Card expiration year cannot be in the past", 400);
            }
        }

        return $this;
    }

    public function getDisplayName(): string
    {
        if ($this->type === 'card') {
            return ucfirst($this->card_brand) . ' ending in ' . $this->card_last_four;
        }

        return ucfirst($this->type);
    }

    public function isExpired(): bool
    {
        if ($this->type !== 'card' || !$this->card_exp_month || !$this->card_exp_year) {
            return false;
        }

        $expirationDate = \Carbon\Carbon::create($this->card_exp_year, $this->card_exp_month)->endOfMonth();
        return $expirationDate->isPast();
    }

    public function makeDefault(): void
    {
        // Remove default from other payment methods for this team
        static::where('team_id', $this->team_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}