<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowJob>
 */
class WorkflowJobDependencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_job_id'            => WorkflowJob::factory(),
            'depends_on_workflow_job_id' => WorkflowJob::factory(),
            'force_schema'               => false,
            'include_fields'             => [],
            'group_by'                   => [],
            'order_by'                   => [],
        ];
    }
}
