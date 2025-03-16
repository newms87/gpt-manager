<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class WorkflowDefinition extends Model implements AuditableContract
{
    use ActionModelTrait, HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public array $relationCounters = [
        WorkflowRun::class => ['workflowRuns' => 'workflow_runs_count'],
    ];

    public function workflowRuns(): HasMany|WorkflowRun
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function workflowNodes(): HasMany|WorkflowNode
    {
        return $this->hasMany(WorkflowNode::class);
    }

    /**
     * Get the starting nodes of the workflow (nodes that don't have any incoming connections)
     */
    public function startingWorkflowNodes(): HasMany|WorkflowNode
    {
        // This is a starting node if it doesn't have any connections where it is the target
        return $this->workflowNodes()->whereDoesntHave('connectionsAsTarget');
    }

    public function workflowConnections(): HasMany|WorkflowConnection
    {
        return $this->hasMany(WorkflowConnection::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('workflow_definitions')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function exportToJson(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'nodes'       => $this->workflowNodes->map(fn(WorkflowNode $workflowNode) => $workflowNode->exportToJson())->values(),
            'connections' => $this->workflowConnections->map(fn(WorkflowConnection $workflowConnection) => $workflowConnection->exportToJson())->values(),
        ];
    }

    public function __toString()
    {
        return "<WorkflowDefinition id='$this->id' name='$this->name'>";
    }
}
