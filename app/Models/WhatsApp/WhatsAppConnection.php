<?php

namespace App\Models\WhatsApp;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class WhatsAppConnection extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, HasRelationCountersTrait, SoftDeletes;

    protected $table = 'whatsapp_connections';

    protected $fillable = [
        'name',
        'phone_number',
        'api_provider',
        'account_sid',
        'auth_token',
        'access_token',
        'webhook_url',
        'api_config',
        'is_active',
        'status',
        'last_sync_at',
        'verified_at',
    ];

    protected $casts = [
        'api_config'   => 'json',
        'is_active'    => 'boolean',
        'last_sync_at' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    protected $hidden = [
        'auth_token',
        'access_token',
    ];

    public array $relationCounters = [
        WhatsAppMessage::class => ['messages' => 'message_count'],
    ];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function messages(): HasMany|WhatsAppMessage
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function validate(): static
    {
        if (!$this->name) {
            throw new \Exception('Name is required');
        }

        if (!$this->phone_number) {
            throw new \Exception('Phone number is required');
        }

        return $this;
    }

    public function getDisplayPhoneNumber(): string
    {
        return preg_replace('/(\d{1})(\d{3})(\d{3})(\d{4})/', '+$1 ($2) $3-$4', $this->phone_number);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && $this->verified_at !== null;
    }

    public function isDisconnected(): bool
    {
        return $this->status === 'disconnected' || !$this->is_active;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasCredentials(): bool
    {
        return match ($this->api_provider) {
            'twilio' => !empty($this->account_sid) && !empty($this->auth_token),
            'whatsapp_business' => !empty($this->access_token),
            default => false,
        };
    }

    public static function booted(): void
    {
        static::creating(function (WhatsAppConnection $connection) {
            $connection->team_id = $connection->team_id ?? team()?->id;
        });
    }
}
