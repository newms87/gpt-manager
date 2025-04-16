<?php

namespace App\Models\Task;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThreadRun;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\Task\Runners\BaseTaskRunner;
use App\Services\Task\TaskRunnerService;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class TaskDefinition extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, HasFactory, AuditableTrait, ResourcePackageableTrait, HasRelationCountersTrait, KeywordSearchTrait, SoftDeletes;

    const string
        OUTPUT_ARTIFACT_MODE_STANDARD = '',
        OUTPUT_ARTIFACT_MODE_PER_PROCESS = 'Per Process',
        OUTPUT_ARTIFACT_MODE_GROUP_ALL = 'Group All';

    protected $fillable = [
        'name',
        'description',
        'task_runner_name',
        'task_runner_config',
        'response_format',
        'input_artifact_mode',
        'input_artifact_levels',
        'output_artifact_mode',
        'output_artifact_levels',
        'timeout_after_seconds',
        'schema_definition_id',
        'agent_id',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'task_runner_name',
    ];

    public array $relationCounters = [
        TaskRun::class => ['taskRuns' => 'task_run_count'],
    ];

    public function casts()
    {
        return [
            'task_runner_config'     => 'json',
            'input_artifact_levels'  => 'json',
            'output_artifact_levels' => 'json',
        ];
    }

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function schemaAssociations(): MorphMany|SchemaAssociation
    {
        return $this->morphMany(SchemaAssociation::class, 'object');
    }

    public function taskDefinitionDirectives(): HasMany|TaskDefinitionDirective
    {
        return $this->hasMany(TaskDefinitionDirective::class)->orderBy('position');
    }

    public function beforeThreadDirectives(): HasMany|TaskDefinitionDirective
    {
        return $this->hasMany(TaskDefinitionDirective::class)->where('section', TaskDefinitionDirective::SECTION_TOP);
    }

    public function afterThreadDirectives(): HasMany|TaskDefinitionDirective
    {
        return $this->hasMany(TaskDefinitionDirective::class)->where('section', TaskDefinitionDirective::SECTION_BOTTOM);
    }

    public function taskInputs(): HasMany|TaskInput
    {
        return $this->hasMany(TaskInput::class);
    }

    public function taskRuns(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function taskArtifactFiltersAsSource(): HasMany|TaskArtifactFilter
    {
        return $this->hasMany(TaskArtifactFilter::class, 'source_task_definition_id');
    }

    public function taskArtifactFiltersAsTarget(): HasMany|TaskArtifactFilter
    {
        return $this->hasMany(TaskArtifactFilter::class, 'target_task_definition_id');
    }

    public function workflowNodes(): HasMany|WorkflowNode
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function isJsonResponse(): bool
    {
        return $this->response_format === AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA && $this->schema_definition_id;
    }

    public function isTextResponse(): bool
    {
        return !$this->isJsonResponse();
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
        $this->taskArtifactFiltersAsSource()->each(fn(TaskArtifactFilter $taf) => $taf->delete());
        $this->taskArtifactFiltersAsTarget()->each(fn(TaskArtifactFilter $taf) => $taf->delete());

        return parent::delete();
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $service->registerRelatedModels($this->taskArtifactFiltersAsTarget);
        $service->registerRelatedModels($this->schemaAssociations);
        $service->registerRelatedModels($this->taskDefinitionDirectives);

        return $service->register($this, [
            'name'                   => $this->name,
            'description'            => $this->description,
            'task_runner_name'       => $this->task_runner_name,
            'task_runner_config'     => $this->task_runner_config,
            'schema_definition_id'   => $service->registerRelatedModel($this->schemaDefinition),
            'agent_id'               => $service->registerRelatedModel($this->agent),
            'response_format'        => $this->response_format,
            'input_artifact_mode'    => $this->input_artifact_mode,
            'input_artifact_levels'  => $this->input_artifact_levels,
            'output_artifact_mode'   => $this->output_artifact_mode,
            'output_artifact_levels' => $this->output_artifact_levels,
            'timeout_after_seconds'  => $this->timeout_after_seconds,
        ]);
    }

    public function getRunner(): BaseTaskRunner
    {
        $runners     = TaskRunnerService::getTaskRunners();
        $runnerClass = $runners[$this->task_runner_name] ?? BaseTaskRunner::class;

        return app($runnerClass);
    }

    public function isTrigger(): bool
    {
        return $this->getRunner()->isTrigger();
    }

    public static function booted()
    {
        static::saving(function (TaskDefinition $taskDefinition) {
            if ($taskDefinition->isDirty('task_runner_name')) {
                // If the task runner class has been changed w/o changing the config, reset the config
                if (!$taskDefinition->isDirty('task_runner_config')) {
                    $taskDefinition->task_runner_config = null;
                }

                // If the task runner class has been changed, reset the schema definition (if it is unchanged)
                if (!$taskDefinition->isDirty('schema_definition_id')) {
                    $taskDefinition->schema_definition_id = null;
                }
            }
        });

        static::saved(function (TaskDefinition $taskDefinition) {
            if ($taskDefinition->wasChanged('task_runner_name')) {
                foreach($taskDefinition->taskDefinitionDirectives as $taskDefinitionDirective) {
                    $taskDefinitionDirective->delete();
                }
            }

            if ($taskDefinition->wasChanged('schema_definition_id')) {
                foreach($taskDefinition->schemaAssociations as $schemaAssociation) {
                    if ($schemaAssociation->schema_definition_id != $taskDefinition->schema_definition_id) {
                        $schemaAssociation->delete();
                    }
                }
            }
        });
    }

    public function __toString()
    {
        return "<TaskDefinition id='$this->id' name='$this->name' runner='$this->task_runner_name'>";
    }
}
