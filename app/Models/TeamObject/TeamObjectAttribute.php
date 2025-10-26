<?php

namespace App\Models\TeamObject;

use App\Events\TeamObjectUpdatedEvent;
use App\Models\Agent\AgentThreadRun;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TeamObjectAttribute extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, SoftDeletes;

    protected $table   = 'team_object_attributes';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'json_value' => 'json',
        ];
    }

    public static function booted(): void
    {
        static::saved(function (TeamObjectAttribute $attribute) {
            if ($attribute->teamObject) {
                TeamObjectUpdatedEvent::broadcast($attribute->teamObject);
            }
        });
    }

    public function getValue(): string|array|null
    {
        return $this->text_value ?? $this->json_value;
    }

    public function teamObject(): BelongsTo|TeamObject
    {
        return $this->belongsTo(TeamObject::class, 'team_object_id');
    }

    public function sources(): HasMany|TeamObjectAttributeSource
    {
        return $this->hasMany(TeamObjectAttributeSource::class, 'team_object_attribute_id');
    }

    public function agentThreadRun(): BelongsTo|AgentThreadRun
    {
        return $this->belongsTo(AgentThreadRun::class, 'agent_thread_run_id');
    }

    public function __toString(): string
    {
        $value = $this->text_value ?? json_encode($this->json_value);

        return "<TeamObjectAttribute ($this->team_object_id) name='$this->name' value='$value' />";
    }
}
