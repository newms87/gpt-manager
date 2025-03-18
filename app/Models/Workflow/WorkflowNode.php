<?php

namespace App\Models\Workflow;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Task\TaskDefinition;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowNode extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait, ResourcePackageableTrait;

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

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'workflow_definition_id' => $service->registerRelatedModel($this->workflowDefinition),
            'task_definition_id'     => $service->registerRelatedModel($this->taskDefinition),
            'name'                   => $this->name,
            'settings'               => $this->settings,
            'params'                 => $this->params,
        ]);
    }

    public function __toString()
    {
        return "<WorkflowNode id='$this->id' name='$this->name'>";
    }
}
