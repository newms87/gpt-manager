<?php

namespace App\Models\Workflow;

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
        return $this->hasMany(WorkflowTask::class)->whereIn('status', [WorkflowTask::STATUS_PENDING, WorkflowTask::STATUS_RUNNING]);
    }

    public function workflowAssignments(): HasMany|WorkflowAssignment
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    public function getWorkflowTool(): WorkflowTool
    {
        try {
            return app($this->workflow_tool ?: RunAgentThreadWorkflowTool::class);
        } catch(\Throwable $exception) {
            throw new Exception("Invalid workflow tool for $this->name: $this->workflow_tool");
        }
    }

    public function getResponsePreview(): array|string|null
    {
        try {
            return $this->getWorkflowTool()->getResponsePreview($this);
        } catch(Throwable $exception) {
            Log::error("Error getting response preview for $this: $exception", ['exception' => $exception]);

            return [];
        }
    }

    /**
     * The list of fields that are returned in the expected response
     */
    public function getResponseFields(): array
    {
        $responses = $this->getResponsePreview();
        $fields    = [];
        foreach($responses as $response) {
            $fields = array_merge($fields, ArrayHelper::getNestedFieldList($response));
        }

        return array_unique($fields);
    }

    public function getTasksPreview(): array
    {
        try {
            $prerequisiteJobRuns = [];

            foreach($this->dependencies as $dependency) {
                $artifacts = [];


                if ($dependency->dependsOn->workflow_tool === WorkflowInputWorkflowTool::class) {
                    $artifacts[] = Artifact::make([
                        'data' => [
                            'content' => 'Example content',
                            'files'   => [
                                [
                                    'name' => 'example.pdf',
                                    'url'  => 'https://example.com/example.pdf',
                                ],
                                [
                                    'name' => 'example2.pdf',
                                    'url'  => 'https://example.com/example2.pdf',
                                ],
                            ],
                        ],
                    ]);
                } else {
                    foreach($dependency->dependsOn->workflowAssignments as $assignment) {
                        $artifacts[] = Artifact::make(['data' => $assignment->agent->response_sample]);
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
