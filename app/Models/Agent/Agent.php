<?php

namespace App\Models\Agent;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class Agent extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes, KeywordSearchTrait;

    const string
        RESPONSE_FORMAT_TEXT = 'text',
        RESPONSE_FORMAT_JSON_SCHEMA = 'json_schema',
        RESPONSE_FORMAT_JSON_OBJECT = 'json_object';

    protected $fillable = [
        'name',
        'description',
        'api',
        'model',
        'temperature',
        'response_format',
        'response_schema_id',
        'response_schema_fragment_id',
        'save_response_to_db',
        'enable_message_sources',
        'tools',
        'retry_count',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'api',
        'model',
        'response_format',
    ];

    public array $relationCounters = [
        AgentThread::class        => ['threads' => 'threads_count'],
        WorkflowAssignment::class => ['assignments' => 'assignments_count'],
    ];

    public function casts(): array
    {
        return [
            'tools'                  => 'json',
            'save_response_to_db'    => 'boolean',
            'enable_message_sources' => 'boolean',
            'temperature'            => 'float',
        ];
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function knowledge(): BelongsTo|Knowledge
    {
        return $this->belongsTo(Knowledge::class);
    }

    public function responseSchema(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class, 'response_schema_id');
    }

    public function responseSchemaFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class, 'response_schema_fragment_id');
    }

    public function directives(): HasMany|AgentPromptDirective
    {
        return $this->hasMany(AgentPromptDirective::class)->orderBy('position');
    }

    public function topDirectives(): HasMany|AgentPromptDirective
    {
        return $this->directives()->where('section', AgentPromptDirective::SECTION_TOP);
    }

    public function bottomDirectives(): HasMany|AgentPromptDirective
    {
        return $this->directives()->where('section', AgentPromptDirective::SECTION_BOTTOM);
    }

    public function threads(): HasMany|AgentThread
    {
        return $this->hasMany(AgentThread::class);
    }

    public function assignments(): WorkflowAssignment|HasMany
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    public function workflowJobs(): BelongsToMany|WorkflowJob
    {
        return $this->belongsToMany(WorkflowJob::class)->withTimestamps();
    }

    public function formatTools(): array
    {
        $availableTools = collect(config('ai.tools'))->keyBy('name');
        $tools          = [];
        foreach(($this->tools ?: []) as $name) {
            $availableTool = $availableTools->get($name);
            if (!$availableTool) {
                continue;
            }
            $tools[] = [
                'type'     => 'function',
                'function' => $availableTool,
            ];
        }

        return $tools;
    }

    public function getModelConfig($key = null, $default = null): ?array
    {
        $config = config('ai.models')[$this->api][$this->model] ?? [];

        if ($key) {
            return $config[$key] ?? $default;
        }

        return $config;
    }

    public function getModelApi(): AgentApiContract
    {
        $apiClass = config('ai.apis')[$this->api] ?? null;
        if (!$apiClass) {
            // Special case for testing, as this TestAiApi only exists in testing / dev environments
            if ($this->api === 'TestAI') {
                return app('Tests\Feature\Api\TestAi\TestAiApi');
            }
            throw new Exception('API class not found for ' . $this->api);
        }

        return app($apiClass);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name'        => [
                'required',
                'max:80',
                'string',
                Rule::unique('agents')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'api'         => 'required|string',
            'model'       => 'required|string',
            'temperature' => 'required|numeric',
            'tools'       => 'nullable|array',
        ])->validate();

        return $this;
    }

    public function delete(): bool
    {
        $assignmentCount = $this->assignments()->count();

        if ($assignmentCount) {
            throw new Exception("Cannot delete Agent $this->name: there are $assignmentCount active assignments");
        }

        return parent::delete();
    }

    public static function booted(): void
    {
        static::creating(function (Agent $agent) {
            $agent->team_id = $agent->team_id ?? team()->id;
        });
    }

    public function __toString(): string
    {
        return "<Agent ($this->id) " . StringHelper::limitText(20, $this->name) . ": $this->api $this->model>";
    }
}
