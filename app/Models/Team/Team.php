<?php

namespace App\Models\Team;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\User;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class Team extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait, SoftDeletes, HasUuids;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function users(): BelongsToMany|User
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class);
    }

    public function schemaDefinitions(): HasMany|SchemaDefinition
    {
        return $this->hasMany(SchemaDefinition::class);
    }
    
    public function taskDefinitions(): HasMany|TaskDefinition
    {
        return $this->hasMany(TaskDefinition::class);
    }

    public function workflowDefinitions(): HasMany|WorkflowDefinition
    {
        return $this->hasMany(WorkflowDefinition::class);
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
