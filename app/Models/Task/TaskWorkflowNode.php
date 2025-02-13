<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class TaskWorkflowNode extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'name',
        'settings',
        'params',
    ];

    public function casts(): array
    {
        return [
            'settings' => 'json',
            'params'   => 'json',
        ];
    }

    public function taskWorkflow(): BelongsTo|TaskWorkflow
    {
        return $this->belongsTo(TaskWorkflow::class);
    }

    public function connectionsAsSource(): HasMany|TaskWorkflowConnection
    {
        return $this->hasMany(TaskWorkflowConnection::class, 'source_node_id');
    }

    public function connectionsAsTarget(): HasMany|TaskWorkflowConnection
    {
        return $this->hasMany(TaskWorkflowConnection::class, 'target_node_id');
    }

    public function taskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('task_workflows')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function __toString()
    {
        return "<TaskWorkflow id='$this->id' name='$this->name'>";
    }
}
