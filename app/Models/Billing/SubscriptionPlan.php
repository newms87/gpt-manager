<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class SubscriptionPlan extends Model implements AuditableContract
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
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_active' => 'boolean',
            'features' => 'json',
            'usage_limits' => 'json',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function validate(): static
    {
        $query = SubscriptionPlan::where('slug', $this->slug)->where('id', '!=', $this->id);

        if ($existingPlan = $query->first()) {
            throw new ValidationError("A subscription plan with the slug '$this->slug' already exists", 409);
        }

        if (empty($this->name)) {
            throw new ValidationError("Subscription plan name is required", 400);
        }

        if (empty($this->slug)) {
            throw new ValidationError("Subscription plan slug is required", 400);
        }

        return $this;
    }

    public function getMonthlyPriceFormatted(): string
    {
        return '$' . number_format($this->monthly_price, 2);
    }

    public function getYearlyPriceFormatted(): string
    {
        return '$' . number_format($this->yearly_price, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('monthly_price');
    }
}