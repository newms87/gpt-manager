<?php

namespace App\Models\Workflow;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class Workflow extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function workflowJobs(): HasMany|WorkflowJob
    {
        return $this->hasMany(WorkflowJob::class);
    }

    public function sortedAgentWorkflowJobs(): HasMany|WorkflowJob
    {
        return $this->workflowJobs()->orderBy('dependency_level')->orderBy('name');
    }

    public function workflowRuns(): HasMany|WorkflowRun
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('workflows')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function __toString()
    {
        return "<Workflow ($this->id) $this->name>";
    }
}
