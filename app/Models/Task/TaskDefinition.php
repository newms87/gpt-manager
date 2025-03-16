<?php

namespace App\Models\Task;

use App\Models\Workflow\WorkflowNode;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class TaskDefinition extends Model implements AuditableContract
{
    use ActionModelTrait, HasFactory, AuditableTrait, HasRelationCountersTrait, KeywordSearchTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'task_runner_class',
        'task_runner_config',
        'artifact_split_mode',
        'timeout_after_seconds',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'task_runner_class',
    ];

    public array $relationCounters = [
        TaskRun::class             => ['taskRuns' => 'task_run_count'],
        TaskDefinitionAgent::class => ['definitionAgents' => 'task_agent_count'],
    ];

    public function definitionAgents(): HasMany|TaskDefinitionAgent
    {
        return $this->hasMany(TaskDefinitionAgent::class);
    }

    public function taskInputs(): HasMany|TaskInput
    {
        return $this->hasMany(TaskInput::class);
    }

    public function taskRuns(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function workflowNodes(): HasMany|WorkflowNode
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('task_definitions')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function delete(): ?bool
    {
        $this->workflowNodes()->each(fn(WorkflowNode $wn) => $wn->delete());

        return parent::delete();
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'name'                  => $this->name,
            'description'           => $this->description,
            'task_runner_class'     => $this->task_runner_class,
            'task_runner_config'    => $this->task_runner_config,
            'artifact_split_mode'   => $this->artifact_split_mode,
            'timeout_after_seconds' => $this->timeout_after_seconds,
            'definitionAgents'      => $this->definitionAgents->map(fn(TaskDefinitionAgent $taskDefinitionAgent) => $taskDefinitionAgent->exportToJson($service))->values(),
        ]);
    }

    public function __toString()
    {
        return "<TaskDefinition id='$this->id' name='$this->name' runner='$this->task_runner_class'>";
    }
}
