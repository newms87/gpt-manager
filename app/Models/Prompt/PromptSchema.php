<?php

namespace App\Models\Prompt;

use App\Models\Agent\Agent;
use App\Models\Team\Team;
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
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class PromptSchema extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    const string
        FORMAT_JSON = 'json',
        FORMAT_YAML = 'yaml',
        FORMAT_TYPESCRIPT = 'typescript';

    const string
        TYPE_AGENT_RESPONSE = 'Agent Response';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'agents_count',
        'workflow_jobs_count',
    ];

    public array $relationCounters = [
        Agent::class       => ['agents' => 'agents_count'],
        WorkflowJob::class => ['workflowJobs' => 'workflow_jobs_count'],
    ];

    public function casts(): array
    {
        return [
            'schema' => 'json',
        ];
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class);
    }

    public function workflowJobs(): BelongsToMany|WorkflowJob
    {
        return $this->belongsToMany(WorkflowJob::class)->withTimestamps();
    }

    public function delete(): bool
    {
        $agentsCount = $this->agents()->count();
        if ($agentsCount) {
            throw new Exception("Cannot delete Prompt Schema $this->name: there are $agentsCount agents with this schema assigned.");
        }

        $workflowJobsCount = $this->workflowJobs()->count();

        if ($workflowJobsCount) {
            throw new Exception("Cannot delete Prompt Schema $this->name: there are $workflowJobsCount workflow jobs assigned");
        }

        return parent::delete();
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('prompt_schemas')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'type' => 'required|string',
        ])->validate();

        return $this;
    }


    public static function booted(): void
    {
        static::creating(function (PromptSchema $agent) {
            $agent->team_id = $agent->team_id ?? team()->id;
        });
    }

    public function __toString(): string
    {
        return "<PromptSchema $this->name>";
    }
}
