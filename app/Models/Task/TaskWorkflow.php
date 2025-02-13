<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskWorkflow extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public array $relationCounters = [
        TaskWorkflowRun::class => ['taskWorkflowRuns' => 'workflow_runs_count'],
    ];

    public function taskWorkflowRuns(): HasMany|TaskWorkflowRun
    {
        return $this->hasMany(TaskWorkflowRun::class);
    }

    public function taskWorkflowNodes(): HasMany|TaskWorkflowNode
    {
        return $this->hasMany(TaskWorkflowNode::class);
    }

    /**
     * Get the starting nodes of the workflow (nodes that don't have any incoming connections)
     */
    public function startingWorkflowNodes(): HasMany|TaskWorkflowNode
    {
        // This is a starting node if it doesn't have any connections where it is the target
        return $this->taskWorkflowNodes()->whereDoesntHave('connectionsAsTarget');
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
