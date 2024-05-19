<?php

namespace Tests\Feature\Workflow;

use App\Api\OpenAi\OpenAiApi;
use App\Jobs\RunWorkflowTaskJob;
use App\Models\Shared\Artifact;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class WorkflowServiceTest extends AuthenticatedTestCase
{
    use AiMockData;

    public function test_start_producesArtifact(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();
        $agent       = $this->openAiAgent();
        $workflow    = Workflow::factory()->create();
        $workflowJob = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

        // When
        WorkflowService::start($workflowRun);

        // Then
        $this->assertNotNull($workflowRun->artifacts()->exists(), 'The artifact was not produced');
    }

    public function test_start_setsStartingStatusesAndTimestamps(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();
        $agent       = $this->openAiAgent();
        $workflow    = Workflow::factory()->create();
        $workflowJob = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

        // When
        WorkflowService::start($workflowRun);

        // Then
        $workflowRun->refresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $workflowRun->status, 'The workflow run status was not set to completed');
        $this->assertNotNull($workflowRun->completed_at, 'The workflow run completed_at timestamp was not set');
        $this->assertNull($workflowRun->failed_at, 'The workflow run failed_at timestamp was set');
        $this->assertNotNull($workflowRun->started_at, 'The workflow run started_at timestamp was not set');

        $workflowRunJob = $workflowRun->workflowJobRuns()->first();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $workflowRunJob->status, 'The workflow run job status was not set to completed');
        $this->assertNotNull($workflowRunJob->completed_at, 'The workflow run job completed_at timestamp was not set');
        $this->assertNull($workflowRunJob->failed_at, 'The workflow run job failed_at timestamp was set');
        $this->assertNotNull($workflowRunJob->started_at, 'The workflow run job started_at timestamp was not set');
    }

    public function test_start_dependentJobsNotDispatchedBeforeDependencyCompleted(): void
    {
        // Given
        RunWorkflowTaskJob::disable();
        $agent        = $this->openAiAgent();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowJobB = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create([
            'depends_on' => [$workflowJobA->id],
        ]);
        $workflowRun  = WorkflowRun::factory()->recycle($workflow)->create();

        $this->mock(OpenAiApi::class)->shouldNotReceive('complete');

        // When
        WorkflowService::start($workflowRun);

        // Then
        $workflowJobRunA = $workflowRun->workflowJobRuns()->where('workflow_job_id', $workflowJobA->id)->first();
        $this->assertNotNull($workflowJobRunA->started_at, 'Job A was not dispatched');
        $this->assertNull($workflowJobRunA->completed_at, 'Job A should not have been completed as the task has not run yet');
        $this->assertNull($workflowJobRunA->failed_at, 'Job A should not have failed as the task has not run yet');

        $workflowJobRunB = $workflowRun->workflowJobRuns()->where('workflow_job_id', $workflowJobB->id)->first();
        $this->assertNull($workflowJobRunB->started_at, 'Job B should not have been dispatched yet since Job A has not completed');
    }

    public function test_workflowJobRunFinished_dependentJobDispatchedAfterDependencyCompleted(): void
    {
        // Given
        RunWorkflowTaskJob::disable();
        $this->mocksOpenAiNotCalled();
        $agent           = $this->openAiAgent();
        $workflow        = Workflow::factory()->create();
        $workflowJobA    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowJobB    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create([
            'depends_on' => [$workflowJobA->id],
        ]);
        $workflowRun     = WorkflowRun::factory()->recycle($workflow)->started()->create();
        $workflowJobRunA = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'completed_at' => now(),
            'started_at'   => now(),
        ]);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB = $workflowRun->workflowJobRuns()->where('workflow_job_id', $workflowJobB->id)->first();
        $this->assertNotNull($workflowJobRunB->started_at, 'Job B should have been dispatched');
        $this->assertNull($workflowJobRunB->completed_at, 'Job B should not have been completed yet as the task has not run');
        $this->assertNull($workflowJobRunB->failed_at, 'Job B should not have failed as the task has not run');
    }

    public function test_workflowJobRunFinished_dependentJobNotDispatchedAfterDependencyFailed(): void
    {
        // Given
        RunWorkflowTaskJob::disable();
        $this->mocksOpenAiNotCalled();
        $agent           = $this->openAiAgent();
        $workflow        = Workflow::factory()->create();
        $workflowJobA    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowJobB    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create([
            'depends_on' => [$workflowJobA->id],
        ]);
        $workflowRun     = WorkflowRun::factory()->recycle($workflow)->create();
        $workflowJobRunA = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'failed_at'  => now(),
            'started_at' => now(),
        ]);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB->refresh();
        $this->assertNull($workflowJobRunB->started_at, 'Job B should not have been dispatched');
        $this->assertNull($workflowJobRunB->completed_at, 'Job B should not have been completed');
        $this->assertNull($workflowJobRunB->failed_at, 'Job B should not have failed');
    }

    public function test_workflowJobRunFinished_dependentJobDispatchedWithArtifactFromDependency(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();
        $agent           = $this->openAiAgent();
        $workflow        = Workflow::factory()->create();
        $workflowJobA    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create(['name' => 'Job A']);
        $workflowJobB    = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create([
            'name'       => 'Job B',
            'depends_on' => [$workflowJobA->id],
        ]);
        $workflowRun     = WorkflowRun::factory()->recycle($workflow)->started()->create();
        $workflowJobRunA = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'completed_at' => now(),
            'started_at'   => now(),
        ]);
        $artifactContent = 'Job A Artifact';
        $artifact        = Artifact::factory()->create(['content' => $artifactContent]);
        $workflowJobRunA->artifacts()->save($artifact);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB->refresh();
        $pendingTaskB = $workflowJobRunB->completedTasks()->first();
        $this->assertNotNull($pendingTaskB, 'Job B should have a completed task');
        $threadB = $pendingTaskB->thread()->first();
        $this->assertNotNull($threadB, 'Job B should have a thread created');
        $this->assertEquals($threadB->messages()->first()->content, $artifactContent, 'Job B should have the artifact content from Job A');
    }
}
