<?php

namespace App\Models\Agent;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Task\TaskDefinition;
use App\Models\Team\Team;
use App\Repositories\AgentRepository;
use App\Services\Workflow\WorkflowExportService;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;
use Tests\Feature\Api\TestAi\TestAiApi;

class Agent extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes, ResourcePackageableTrait, KeywordSearchTrait, ActionModelTrait;

    protected $fillable = [
        'name',
        'description',
        'api',
        'model',
        'temperature',
        'retry_count',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'api',
        'model',
    ];

    public array $relationCounters = [
        AgentThread::class => ['threads' => 'threads_count'],
    ];

    public function casts(): array
    {
        return [
            'temperature' => 'float',
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

    public function taskDefinitions(): HasMany|TaskDefinition
    {
        return $this->hasMany(TaskDefinition::class);
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


    public function resetModel(): static
    {
        $this->api   = config('ai.default_api');
        $this->model = config('ai.default_model');

        return $this;
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
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::creating(function (Agent $agent) {
            $agent->team_id = $agent->team_id ?? team()->id;
        });

        static::saving(function (Agent $agent) {
            if (!$agent->api || $agent->isDirty('model')) {
                $agent->api = AgentRepository::getApiForModel($agent->model) ?: TestAiApi::$serviceName;
            }
        });
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $service->registerRelatedModels($this->directives);

        return $service->register($this, [
            'name'        => $this->name,
            'description' => $this->description,
            'api'         => $this->api,
            'model'       => $this->model,
            'temperature' => $this->temperature,
            'retry_count' => $this->retry_count,
        ]);
    }

    public function delete(): bool
    {
        $this->taskDefinitions()->each(fn(TaskDefinition $taskDefinition) => $taskDefinition->agent()->disassociate()->save());

        return parent::delete();
    }

    public function __toString(): string
    {
        return "<Agent ($this->id) " . StringHelper::limitText(20, $this->name) . ": $this->api $this->model>";
    }
}
