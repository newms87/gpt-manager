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
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class Workflow extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public array $relationCounters = [
        WorkflowJob::class => ['workflowJobs' => 'jobs_count'],
        WorkflowRun::class => ['workflowRuns' => 'runs_count'],
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

    public function replicate(array $except = null): Workflow
    {
        $except = $except ?? ['runs_count', 'jobs_count'];

        $newWorkflow       = parent::replicate($except);
        $newWorkflow->name = ModelHelper::getNextModelName($this);
        $newWorkflow->save();

        foreach($this->workflowJobs as $workflowJob) {
            $newWorkflowJob              = $workflowJob->replicate();
            $newWorkflowJob->workflow_id = $newWorkflow->id;
            $newWorkflowJob->save();
        }

        $newWorkflow->jobs_count = $newWorkflow->workflowJobs()->count();

        // last step is to fix the dependencies relationships as they're still pointing to the old workflow jobs
        foreach($this->workflowJobs as $workflowJob) {
            $newWorkflowJob = $newWorkflow->workflowJobs()->where('name', $workflowJob->name)->first();
            foreach($workflowJob->dependencies as $dependency) {
                $newDependency                             = $dependency->replicate();
                $newDependency->workflow_job_id            = $newWorkflowJob->id;
                $newDependency->depends_on_workflow_job_id = $newWorkflow->workflowJobs()->where('name', $dependency->dependsOn->name)->first()->id;
                $newDependency->save();
            }
        }

        return $newWorkflow;
    }

    public function delete(): bool|null
    {
        $this->workflowJobs()->each(function (WorkflowJob $workflowJob) {
            $workflowJob->delete();
        });

        return parent::delete();
    }

    public function __toString()
    {
        return "<Workflow ($this->id) $this->name>";
    }
}
