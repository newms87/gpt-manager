<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowRepository extends ActionRepository
{
    public static string $model = Workflow::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(runs_count) as runs_count"),
            DB::raw("SUM(jobs_count) as jobs_count"),
        ]);
    }

    public function fieldOptions(?array $filter = []): array
    {
        return [
            'agents' => team()->agents->map(fn(Agent $agent) => ['value' => $agent->id, 'label' => $agent->name]),
        ];
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflow($data),
            'create-job' => app(WorkflowJobRepository::class)->create($model, $data),
            'run-workflow' => $this->runWorkflow($model, $data),
            'import-json' => $this->importFromJson(StoredFile::find($data['import_file_id'] ?? null)?->getContents() ?? ''),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createWorkflow(array $data): Model
    {
        $workflow          = Workflow::make($data);
        $workflow->team_id = team()->id;

        $workflow->validate()->save();

        return $workflow;
    }

    public function runWorkflow(Workflow $workflow, $data): WorkflowRun
    {
        $workflowInputId = $data['workflow_input_id'] ?? null;
        $workflowInput   = WorkflowInput::find($workflowInputId);

        if (!$workflowInput) {
            throw new ValidationError('Workflow Input was not found');
        }

        $workflowRun = $workflow->workflowRuns()->create([
            'workflow_input_id' => $workflowInputId,
        ]);

        WorkflowService::start($workflowRun);

        return $workflowRun;
    }

    public function exportAsJson(Workflow $workflow): string
    {
        $workflow->load('workflowJobs.dependencies', 'workflowJobs.workflowAssignments.agent');

        $data = [
            'name'        => $workflow->name,
            'description' => $workflow->description,
            'jobs'        => $workflow->workflowJobs()->get()->map(function ($job) {
                return [
                    'name'             => $job->name,
                    'description'      => $job->description,
                    'timeout_after'    => $job->timeout_after,
                    'max_attempts'     => $job->max_attempts,
                    'workflow_tool'    => $job->workflow_tool,
                    'dependency_level' => $job->dependency_level,
                    'response_schema'  => $job->response_schema,
                    'dependencies'     => $job->dependencies()->get()->map(function (WorkflowJobDependency $dependency) {
                        return [
                            'depends_on_workflow_job_name' => $dependency->dependsOn->name,
                            'force_schema'                 => $dependency->force_schema,
                            'include_fields'               => $dependency->include_fields,
                            'group_by'                     => $dependency->group_by,
                            'order_by'                     => $dependency->order_by,
                        ];
                    }),
                    'assignments'      => $job->workflowAssignments->map(function (WorkflowAssignment $assignment) {
                        $agent = $assignment->agent;

                        return [
                            'is_required'  => $assignment->is_required,
                            'max_attempts' => $assignment->max_attempts,
                            'agent'        => [
                                'name'                   => $agent->name,
                                'description'            => $agent->description,
                                'api'                    => $agent->api,
                                'model'                  => $agent->model,
                                'temperature'            => $agent->temperature,
                                'tools'                  => $agent->tools,
                                'response_format'        => $agent->response_format,
                                'response_sample'        => $agent->response_sample,
                                'enable_message_sources' => $agent->enable_message_sources,
                                'retry_count'            => $agent->retry_count,
                            ],
                        ];
                    }),
                ];
            }),
        ];

        return json_encode($data);
    }

    public function importFromJson(string $workflowJson): bool
    {
        $data = json_decode($workflowJson, true);

        $name = $data['name'] ?? null;
        if (!$name) {
            throw new ValidationError('Workflow name is required');
        }

        $existingWorkflow = Workflow::where('name', $name)->where('team_id', team()->id)->first();
        if ($existingWorkflow) {
            $name = ModelHelper::getNextModelName($existingWorkflow);
        }
        $workflow = Workflow::create([
            'name'        => $name,
            'description' => $data['description'],
            'team_id'     => team()->id,
        ]);

        foreach($data['jobs'] as $jobData) {
            $workflowJob = $workflow->workflowJobs()->create(collect($jobData)->except('assignments', 'dependencies')->toArray());

            foreach($jobData['assignments'] as $assignmentData) {
                $agent = Agent::updateOrCreate(
                    [
                        'team_id' => team()->id,
                        'name'    => $assignmentData['agent']['name'],
                    ],
                    [
                        ...collect($assignmentData['agent'])->except('name')->toArray(),
                    ]);

                $workflowJob->workflowAssignments()->create([
                    'agent_id'     => $agent->id,
                    'is_required'  => $assignmentData['is_required'],
                    'max_attempts' => $assignmentData['max_attempts'],
                ]);
            }
        }

        // Create dependencies after all jobs and assignments have been created so all required jobs exist
        foreach($data['jobs'] as $jobData) {
            $workflowJob = $workflow->workflowJobs()->where('name', $jobData['name'])->first();
            foreach($jobData['dependencies'] as $dependencyData) {
                $dependsOnJob = $workflow->workflowJobs()->where('name', $dependencyData['depends_on_workflow_job_name'])->first();
                $workflowJob->dependencies()->create([
                    'depends_on_workflow_job_id' => $dependsOnJob->id,
                    ...collect($dependencyData)->except('depends_on_workflow_job_name')->toArray(),
                ]);
            }
        }

        return true;
    }
}
