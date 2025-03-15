<?php

namespace App\Models\Task;

use App\Models\Workflow\WorkflowInput;
use App\Services\Workflow\WorkflowInputToArtifactMapper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskInput extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait, HasRelationCountersTrait;

    protected $fillable = [
        'task_definition_id',
        'workflow_input_id',
    ];

    public array $relationCounters = [
        TaskRun::class => ['taskRuns' => 'task_run_count'],
    ];

    public function taskDefinition(): TaskDefinition|BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function workflowInput(): BelongsTo|WorkflowInput
    {
        return $this->belongsTo(WorkflowInput::class);
    }

    public function taskRuns(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function toArtifact(): Artifact
    {
        return (new WorkflowInputToArtifactMapper)->setWorkflowInput($this->workflowInput)->map();
    }
}
