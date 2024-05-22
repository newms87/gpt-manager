<?php

namespace Tests\Feature\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Repositories\WorkflowJobRepository;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;

class WorkflowJobRepositoryTest extends AuthenticatedTestCase
{
    public function test_calculateDependencyLevels(): void
    {
        // Given
        $workflow     = Workflow::factory()->create();
        $workflowJobA = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobB = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobC = WorkflowJob::factory()->recycle($workflow)->create();

        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobA->id,
            'depends_on_workflow_job_id' => $workflowJobB->id,
        ]);
        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobB->id,
            'depends_on_workflow_job_id' => $workflowJobC->id,
        ]);

        // When
        app(WorkflowJobRepository::class)->calculateDependencyLevels($workflow);

        // Then
        $this->assertEquals(0, $workflowJobC->refresh()->dependency_level);
        $this->assertEquals(1, $workflowJobB->refresh()->dependency_level);
        $this->assertEquals(2, $workflowJobA->refresh()->dependency_level);
    }

    public function test_setDependencies_doesNotAllowSingleLevelCircularDependencies()
    {
        // Given
        $workflow     = Workflow::factory()->create();
        $workflowJobA = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobB = WorkflowJob::factory()->recycle($workflow)->create();

        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobA->id,
            'depends_on_workflow_job_id' => $workflowJobB->id,
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessageMatches('/circular dependency/');

        // When
        app(WorkflowJobRepository::class)->assignDependenciesToWorkflowJob($workflowJobB, [['depends_on_id' => $workflowJobA->id]]);

        // Then
    }

    public function test_assign_doesNotAllowDeeplyNestedCircularDependencies()
    {
        // Given
        $workflow     = Workflow::factory()->create();
        $workflowJobA = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobB = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobC = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowJobD = WorkflowJob::factory()->recycle($workflow)->create();

        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobA->id,
            'depends_on_workflow_job_id' => $workflowJobB->id,
        ]);
        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobB->id,
            'depends_on_workflow_job_id' => $workflowJobC->id,
        ]);
        WorkflowJobDependency::factory()->create([
            'workflow_job_id'            => $workflowJobC->id,
            'depends_on_workflow_job_id' => $workflowJobD->id,
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessageMatches('/circular dependency/');

        // When
        app(WorkflowJobRepository::class)->assignDependenciesToWorkflowJob($workflowJobD, [['depends_on_id' => $workflowJobA->id]]);

        // Then
    }
}
