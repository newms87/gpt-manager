<?php

namespace App\Models\Agent;

use App\Api\AgentApiContracts\AgentApiContract;
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

class Agent extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'api',
        'model',
        'temperature',
        'prompt',
        'response_format',
        'response_notes',
        'response_schema',
        'tools',
    ];

    public function casts(): array
    {
        return [
            'tools'           => 'json',
            'response_schema' => 'json',
            'response_sample' => 'json',
            'temperature'     => 'float',
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

    public function threads(): HasMany|Thread
    {
        return $this->hasMany(Thread::class);
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

    public function getModelApi(): AgentApiContract
    {
        $apiClass = config('ai.apis')[$this->api] ?? null;
        if (!$apiClass) {
            throw new Exception('API class not found for ' . $this->api);
        }

        return app($apiClass);
    }

    public function getFormattedSampleResponse(): string|array|null
    {
        return match ($this->response_format) {
            'text' => $this->response_sample ? $this->response_sample['content'] : '',
            default => $this->response_sample,
        };
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
