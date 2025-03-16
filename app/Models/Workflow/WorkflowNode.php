<?php

namespace App\Models\Workflow;

use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowNode extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait;

    protected $fillable = [
        'task_definition_id',
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

    public function workflowDefinition(): BelongsTo|WorkflowDefinition
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function connectionsAsSource(): HasMany|WorkflowConnection
    {
        return $this->hasMany(WorkflowConnection::class, 'source_node_id');
    }

    public function connectionsAsTarget(): HasMany|WorkflowConnection
    {
        return $this->hasMany(WorkflowConnection::class, 'target_node_id');
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
            ],
        ])->validate();

        return $this;
    }

    public function exportToJson(): array
    {
        return [
            'name'           => $this->name,
            'settings'       => $this->settings,
            'params'         => $this->params,
            'taskDefinition' => $this->taskDefinition->exportToJson(),
        ];
    }

    public function __toString()
    {
        return "<WorkflowNode id='$this->id' name='$this->name'>";
    }
}
