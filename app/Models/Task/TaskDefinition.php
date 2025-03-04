<?php

namespace App\Models\Task;

use App\Models\Schema\SchemaAssociation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'grouping_mode',
        'split_by_file',
        'input_group_chunk_size',
        'timeout_after_seconds',
    ];

    protected array $keywordFields = [
        'name',
        'description',
    ];

    public array $relationCounters = [
        TaskRun::class             => ['taskRuns' => 'task_run_count'],
        TaskDefinitionAgent::class => ['definitionAgents' => 'task_agent_count'],
    ];

    public function casts(): array
    {
        return [
            'split_by_file' => 'boolean',
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

    public function schemaAssociations(): MorphMany|SchemaAssociation
    {
        return $this->morphMany(SchemaAssociation::class, 'object');
    }

    public function groupingSchemaAssociations(): MorphMany|SchemaAssociation
    {
        return $this->schemaAssociations()->where('category', 'grouping');
    }

    public function getGroupingKeys(): array
    {
        $groupingKeys = [];
        foreach($this->groupingSchemaAssociations as $association) {
            if ($association->schemaFragment->fragment_selector) {
                $groupingKeys[] = $association->schemaFragment->fragment_selector;
            }
        }

        return $groupingKeys;
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
