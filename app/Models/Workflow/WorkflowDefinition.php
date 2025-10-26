<?php

namespace App\Models\Workflow;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Team\Team;
use App\Services\Task\Runners\WorkflowInputTaskRunner;
use App\Services\Workflow\WorkflowExportService;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class WorkflowDefinition extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, HasDebugLogging, HasFactory, HasRelationCountersTrait, ResourcePackageableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'max_workers',
    ];

    protected $casts = [
        'max_workers' => 'integer',
    ];

    protected $attributes = [
        'max_workers' => 20,
    ];

    public array $relationCounters = [
        WorkflowRun::class => ['workflowRuns' => 'workflow_runs_count'],
    ];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

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
        return $this->workflowNodes()
            ->whereDoesntHave('connectionsAsTarget')
            ->whereHas('taskDefinition', fn(Builder $builder) => $builder->where('task_runner_name', WorkflowInputTaskRunner::RUNNER_NAME));
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

    /**
     * Clean up any corrupted connections that reference non-existent nodes
     */
    public function cleanCorruptedConnections(): void
    {
        static::log("Clean corrupted connections for $this");

        $validNodeIds = $this->workflowNodes()->pluck('id');

        $corruptedConnections = WorkflowConnection::where('workflow_definition_id', $this->id)
            ->where(function ($query) use ($validNodeIds) {
                $query->whereNotIn('source_node_id', $validNodeIds)
                    ->orWhereNotIn('target_node_id', $validNodeIds);
            })
            ->get();

        if ($corruptedConnections->isNotEmpty()) {
            static::log('Found ' . $corruptedConnections->count() . ' corrupted connections to delete');
            $corruptedConnections->each->delete();
        }
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $this->cleanCorruptedConnections();
        $service->registerRelatedModels($this->workflowNodes);
        $service->registerRelatedModels($this->workflowConnections);

        return $service->register($this, [
            'name'        => $this->name,
            'description' => $this->description,
        ]);
    }

    public function __toString()
    {
        return "<WorkflowDefinition id='$this->id' name='$this->name'>";
    }
}
