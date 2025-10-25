<?php

namespace App\Models\Agent;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Task\TaskDefinition;
use App\Models\Team\Team;
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

class Agent extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasRelationCountersTrait, KeywordSearchTrait, ResourcePackageableTrait, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'model',
        'retry_count',
        'api_options',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'model',
    ];

    public array $relationCounters = [
        AgentThread::class => ['threads' => 'threads_count'],
    ];

    public function casts(): array
    {
        return [
            'api_options' => 'json',
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

    public function threads(): HasMany|AgentThread
    {
        return $this->hasMany(AgentThread::class);
    }

    public function getModelApi(): AgentApiContract
    {
        $modelConfig = config('ai.models.' . $this->model);
        $apiClass    = $modelConfig['api'] ?? null;
        if (!$apiClass) {
            throw new Exception('API class not found for ' . $this->model);
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
            'model'       => 'required|string',
            'api_options' => 'nullable|array',
        ])->validate();

        return $this;
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $service->registerRelatedModels($this->directives);

        return $service->register($this, [
            'name'        => $this->name,
            'description' => $this->description,
            'model'       => $this->model,
            'retry_count' => $this->retry_count,
            'api_options' => $this->api_options,
        ]);
    }

    public function delete(): bool
    {
        $this->taskDefinitions()->each(fn(TaskDefinition $taskDefinition) => $taskDefinition->agent()->disassociate()->save());

        return parent::delete();
    }

    public function __toString(): string
    {
        return "<Agent ($this->id) " . StringHelper::limitText(20, $this->name) . ": $this->model>";
    }
}
