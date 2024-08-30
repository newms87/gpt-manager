<?php

namespace App\Models\Workflow;

use App\Models\Prompt\PromptSchema;
use App\WorkflowTools\RunAgentThreadWorkflowTool;
use App\WorkflowTools\WorkflowInputWorkflowTool;
use App\WorkflowTools\WorkflowTool;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Traits\AuditableTrait;
use Throwable;

class WorkflowJob extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'response_schema' => 'json',
        ];
    }

    public function workflow(): BelongsTo|Workflow
    {
        return $this->belongsTo(Workflow::class);
    }

    public function dependencies(): HasMany|WorkflowJobDependency
    {
        return $this->hasMany(WorkflowJobDependency::class);
    }

    public function dependents(): HasMany|WorkflowJobDependency
    {
        return $this->hasMany(WorkflowJobDependency::class, 'depends_on_workflow_job_id');
    }

    public function workflowJobRuns(): HasMany|WorkflowJobRun
    {
        return $this->hasMany(WorkflowJobRun::class);
    }

    public function workflowTasks(): HasMany|WorkflowTask
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function remainingTasks(): HasMany|WorkflowTask
    {
        return $this->hasMany(WorkflowTask::class)->whereIn('status', [WorkflowRun::STATUS_PENDING, WorkflowRun::STATUS_RUNNING]);
    }

    public function workflowAssignments(): HasMany|WorkflowAssignment
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    public function responseSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class, 'response_schema_id');
    }

    public function getWorkflowTool(): WorkflowTool
    {
        try {
            return app($this->workflow_tool ?: RunAgentThreadWorkflowTool::class);
        } catch(\Throwable $exception) {
            throw new Exception("Invalid workflow tool for $this->name: $this->workflow_tool");
        }
    }

    /**
     * Gets a list of all the responses that are expected from the workflow tool
     */
    public function getResponseExample(): array
    {
        try {
            return $this->getWorkflowTool()->getResponseExample($this);
        } catch(Throwable $exception) {
            Log::error("Error getting response example for $this: $exception", ['exception' => $exception]);

            return [];
        }
    }

    /**
     * The list of fields that are returned in the expected response
     */
    public function getResponseFields(): array
    {
        return ArrayHelper::getNestedFieldList($this->getResponseExample());
    }

    /**
     * Get an example list of tasks that will be created by the workflow tool
     */
    public function getTasksPreview(): array
    {
        try {
            $prerequisiteJobRuns = [];

            foreach($this->dependencies as $dependency) {
                $artifacts = [];

                if ($dependency->dependsOn->workflow_tool === WorkflowInputWorkflowTool::class) {
                    $example     = $dependency->dependsOn->getResponseExample();
                    $artifacts[] = Artifact::make([
                        'data' => $example,
                    ]);
                } else {
                    foreach($dependency->dependsOn->workflowAssignments as $assignment) {
                        if ($assignment->agent->responseSchema) {
                            $artifacts[] = Artifact::make(['data' => $assignment->agent->responseSchema?->response_example]);
                        }
                    }
                }

                $workflowRun                  = WorkflowJobRun::make();
                $workflowRun->workflow_job_id = $dependency->depends_on_workflow_job_id;
                $workflowRun->setRelation('artifacts', $artifacts);
                $prerequisiteJobRuns[$workflowRun->workflow_job_id] = $workflowRun;
            }

            return $this->getWorkflowTool()->resolveDependencyArtifacts($this, $prerequisiteJobRuns);
        } catch(Exception $e) {
            Log::warning("Error generating tasks preview for $this: $e");

            return [];
        }
    }

    public function replicate(array $except = null)
    {
        $newWorkflowJob = parent::replicate($except);
        $newWorkflowJob->save();

        foreach($this->workflowAssignments as $assignment) {
            $newAssignment                  = $assignment->replicate();
            $newAssignment->workflow_job_id = $newWorkflowJob->id;
            $newAssignment->save();
        }

        // NOTE: Dependencies are handles by the Workflow replication process as this involves associating the new job and the new dependent job

        return $newWorkflowJob;
    }

    public function delete()
    {
        $this->dependencies()->each(fn(WorkflowJobDependency $dependency) => $dependency->delete());
        $this->dependents()->each(fn(WorkflowJobDependency $dependent) => $dependent->delete());
        $this->workflowAssignments()->each(fn(WorkflowAssignment $assignment) => $assignment->delete());

        return parent::delete();
    }

    public function validate(): void
    {
        Validator::make($this->toArray(), [
            'name' => ['required', 'max:80', 'string', Rule::unique('workflow_jobs')->where('workflow_id', $this->workflow_id)->whereNull('deleted_at')],
        ])->validate();
    }

    public function __toString()
    {
        return "<Workflow Job ($this->id) $this->name>";
    }
}
