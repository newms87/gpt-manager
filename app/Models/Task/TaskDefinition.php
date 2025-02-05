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

class TaskDefinition extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'task_runner_class',
        'input_grouping',
        'input_group_chunk_size',
        'timeout_after_seconds',
    ];

    public array $relationCounters = [
        TaskDefinitionAgent::class => ['definitionAgents' => 'task_agent_count'],
    ];

    public function casts(): array
    {
        return [
            'input_grouping' => 'json',
        ];
    }

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

    public function __toString()
    {
        return "<TaskDefinition id='$this->id' name='$this->name' runner='$this->task_runner_class'>";
    }
}
