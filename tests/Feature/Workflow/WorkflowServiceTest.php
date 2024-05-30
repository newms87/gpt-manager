<?php

namespace Tests\Feature\Workflow;

use App\Jobs\RunWorkflowTaskJob;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
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
        $this->mocksOpenAiCompletionResponse()->once();
        $workflow = Workflow::factory()->create();
        $this->openAiWorkflowJob($workflow, ['use_input' => true]);
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

        // When
        WorkflowService::start($workflowRun);

        // Then
        $this->assertNotNull($workflowRun->artifacts()->exists(), 'The artifact was not produced');
    }

    public function test_start_setsStartingStatusesAndTimestamps(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse()->once();
        $workflow = Workflow::factory()->create();
        $this->openAiWorkflowJob($workflow, ['use_input' => true]);
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
        $this->mocksOpenAiNotCalled();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow);
        $workflowJobB = $this->openAiWorkflowJob($workflow);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id]);
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

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

    public function test_start_pendingWorkflowJobCompletedIfNotTasksRequired(): void
    {
        // Given
        RunWorkflowTaskJob::disable();
        $this->mocksOpenAiNotCalled();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = WorkflowJob::factory()->recycle($workflow)->create();
        $workflowRun  = WorkflowRun::factory()->recycle($workflow)->create();

        // When
        WorkflowService::start($workflowRun);

        // Then
        $workflowJobRunA = $workflowRun->workflowJobRuns()->where('workflow_job_id', $workflowJobA->id)->first();
        $this->assertNotNull($workflowJobRunA->started_at, 'Job A was not dispatched');
        $this->assertNotNull($workflowJobRunA->completed_at, 'Job A should have been completed immediately as there were no assignments / tasks');
        $this->assertNull($workflowJobRunA->failed_at, 'Job A should not have failed as the task has not run yet');

        $workflowRun->refresh();
        $this->assertNotNull($workflowRun->completed_at, 'The workflow run should be completed since all the jobs are complete');
    }

    public function test_workflowJobRunFinished_dependentJobDispatchedAfterDependencyCompleted(): void
    {
        // Given
        RunWorkflowTaskJob::disable();
        $this->mocksOpenAiNotCalled();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow);
        $workflowJobB = $this->openAiWorkflowJob($workflow);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id]);
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
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow);
        $workflowJobB = $this->openAiWorkflowJob($workflow);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id]);
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
        $this->mocksOpenAiCompletionResponse()->once();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow, ['name' => 'Job A']);
        $workflowJobB = $this->openAiWorkflowJob($workflow, ['name' => 'Job B', 'use_input' => true]);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id]);
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
        $this->assertEquals($threadB->messages[0]->content, $workflowRun->workflowInput->content, 'Job B first message should have the workflow input content');
        $this->assertEquals($threadB->messages[1]->content, $artifactContent, 'Job B second message should have the artifact content from Job A');
    }

    public function test_workflowJobRunFinished_multipleTasksDispatchedForAssignmentWithGroupByOfArrayOfPrimitives(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse()->twice();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow, ['name' => 'Job A']);
        $workflowJobB = $this->openAiWorkflowJob($workflow, ['name' => 'Job B']);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id, 'group_by' => 'service_dates']);
        $workflowRun     = WorkflowRun::factory()->recycle($workflow)->started()->create();
        $workflowJobRunA = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'completed_at' => now(),
            'started_at'   => now(),
        ]);
        $serviceDates    = ['2022-01-01', '2022-02-01'];
        $artifactContent = json_encode([
            'service_dates' => $serviceDates,
        ]);
        $artifact        = Artifact::factory()->create(['content' => $artifactContent]);
        $workflowJobRunA->artifacts()->save($artifact);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB->refresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $workflowJobRunB->status, 'Job B should have been completed');

        $completedTasks = $workflowJobRunB->completedTasks()->get();
        $this->assertCount(2, $completedTasks, 'Job B should have 2 tasks, 1 for each service date');

        // Verify 1st Task messages are correct
        $firstTask = $completedTasks->first();
        $this->assertEquals($serviceDates[0], $firstTask->group, 'The first task should have the first service date as the task group');
        $firstTaskMessage = $firstTask->thread()->first()->messages()->first();
        $this->assertEquals($serviceDates[0], $firstTaskMessage->content, 'Job B should have the first service date as the second thread message');

        // Verify 2nd Task messages are correct
        $secondTask = $completedTasks->last();
        $this->assertEquals($serviceDates[1], $secondTask->group, 'The second task should have the second service date as the task group');
        $secondTaskMessage = $secondTask->thread()->first()->messages()->first();
        $this->assertEquals($serviceDates[1], $secondTaskMessage->content, 'Job B should have the second service date as the second message');
    }

    public function test_workflowJobRunFinished_multipleTasksDispatchedForAssignmentWithGroupByOfArrayOfArray(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse()->twice();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow, ['name' => 'Job A']);
        $workflowJobB = $this->openAiWorkflowJob($workflow, ['name' => 'Job B']);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id, 'group_by' => 'service_dates']);
        $workflowRun     = WorkflowRun::factory()->recycle($workflow)->started()->create();
        $workflowJobRunA = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'completed_at' => now(),
            'started_at'   => now(),
        ]);
        $serviceDates    = [
            ['date' => '2022-01-01', 'service' => 'Service A'],
            ['date' => '2022-02-01', 'service' => 'Service B'],
        ];
        $artifactContent = json_encode([
            'service_dates' => $serviceDates,
        ]);
        $artifact        = Artifact::factory()->create(['content' => $artifactContent]);
        $workflowJobRunA->artifacts()->save($artifact);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB->refresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $workflowJobRunB->status, 'Job B should have been completed');

        $completedTasks = $workflowJobRunB->completedTasks()->get();
        $this->assertCount(2, $completedTasks, 'Job B should have 2 tasks, 1 for each service date');

        // Verify 1st Task messages are correct
        $firstTask = $completedTasks->first();
        $this->assertEquals(0, $firstTask->group, 'The first task should have the first service date as the task group');
        $firstTaskMessage = $firstTask->thread()->first()->messages()->first();
        $this->assertEquals($serviceDates[0], json_decode($firstTaskMessage->content, true), 'Job B should have the first service date as the second thread message');

        // Verify 2nd Task messages are correct
        $secondTask = $completedTasks->last();
        $this->assertEquals(1, $secondTask->group, 'The second task should have the second service date as the task group');
        $secondTaskMessage = $secondTask->thread()->first()->messages()->first();
        $this->assertEquals($serviceDates[1], json_decode($secondTaskMessage->content, true), 'Job B should have the second service date as the second message');
    }

    public function test_workflowJobRunFinished_jobAndTaskFailedForAssignmentWithGroupByMissingDataPoint(): void
    {
        $this->markTestSkipped('This test is not yet implemented');
    }

    public function test_workflowJobRunFinished_multipleTasksDispatchedForAssignmentWithGroupByOfKeyInArrayOfArray(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse()->twice();
        $workflow     = Workflow::factory()->create();
        $workflowJobA = $this->openAiWorkflowJob($workflow, ['name' => 'Job A']);
        $workflowJobB = $this->openAiWorkflowJob($workflow, ['name' => 'Job B', 'use_input' => true]);
        $workflowJobB->dependencies()->create(['depends_on_workflow_job_id' => $workflowJobA->id, 'group_by' => 'service_dates.date']);
        $workflowInputContent = 'Multiple Task Workflow Input Content';
        $workflowInput        = WorkflowInput::factory()->create(['name' => $workflowInputContent, 'content' => $workflowInputContent]);
        $workflowRun          = WorkflowRun::factory()->recycle($workflow)->recycle($workflowInput)->started()->create();
        $workflowJobRunA      = WorkflowJobRun::factory()->recycle($workflowJobA)->recycle($workflowRun)->create([
            'completed_at' => now(),
            'started_at'   => now(),
        ]);
        $serviceDates         = [
            ['date' => '2022-01-01', 'service' => 'Service A'],
            ['date' => '2022-02-01', 'service' => 'Service B'],
        ];
        $artifactContent      = json_encode([
            'service_dates' => $serviceDates,
        ]);
        $artifact             = Artifact::factory()->create(['content' => $artifactContent]);
        $workflowJobRunA->artifacts()->save($artifact);
        $workflowJobRunB = WorkflowJobRun::factory()->recycle($workflowJobB)->recycle($workflowRun)->create();

        // When
        WorkflowService::workflowJobRunFinished($workflowJobRunA);

        // Then
        $workflowJobRunB->refresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $workflowJobRunB->status, 'Job B should have been completed');

        $completedTasks = $workflowJobRunB->completedTasks()->get();
        $this->assertCount(2, $completedTasks, 'Job B should have 2 tasks, 1 for each service date');

        // Verify 1st Task messages are correct
        $firstTask = $completedTasks->first();
        $this->assertEquals($serviceDates[0]['date'], $firstTask->group, 'The first task should have the first service date as the task group');
        $firstTaskMessages = $firstTask->thread()->first()->messages()->get();
        $this->assertEquals($workflowInputContent, $firstTaskMessages[0]->content, 'Job B should have the workflow input content as the first thread message');
        $firstTaskServiceDateData = json_decode($firstTaskMessages[1]->content, true);
        $this->assertNotNull($firstTaskServiceDateData, 'Job B should have a thread w/ a message that is JSON encoded with the service date data');
        $this->assertEquals($serviceDates[0]['date'], $firstTaskServiceDateData['date'], 'Job B should have the first service date as the thread input');
        $this->assertEquals($serviceDates[0]['service'], $firstTaskServiceDateData['service'], 'Job B should have the first service as thread input');

        // Verify 2nd Task messages are correct
        $secondTask = $completedTasks->last();
        $this->assertEquals($serviceDates[1]['date'], $secondTask->group, 'The second task should have the second service date as the task group');
        $secondTaskMessages = $secondTask->thread()->first()->messages()->get();
        $this->assertEquals($workflowInputContent, $secondTaskMessages[0]->content, 'Job B should have the workflow input content as the first thread message');
        $secondTaskServiceDateData = json_decode($secondTaskMessages[1]->content, true);
        $this->assertNotNull($secondTaskServiceDateData, 'Job B should have a thread w/ a message that is JSON encoded with the service date data');
        $this->assertEquals($serviceDates[1]['date'], $secondTaskServiceDateData['date'], 'Job B should have the second service date as the thread input');
        $this->assertEquals($serviceDates[1]['service'], $secondTaskServiceDateData['service'], 'Job B should have the second service as thread input');
    }
}
