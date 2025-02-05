<?php

namespace App\Models\Team;

use App\Models\Agent\Agent;
use App\Models\User;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class Team extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function users(): BelongsToMany|User
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class);
    }

    public function workflowInputs(): HasMany|WorkflowInput
    {
        return $this->hasMany(WorkflowInput::class);
    }

    public function __toString()
    {
        return "<Team ($this->id) $this->name>";
    }
}
