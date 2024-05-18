<?php

namespace Tests\Feature\Workflow;

use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use App\Models\User;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    public function test_start_producesArtifact(): void
    {
        // Given
        $user = User::first();
        $this->actingAs($user);
        $agent       = Agent::factory()->recycle($user)->create([
            'api' => OpenAiApi::$serviceName,
        ]);
        $workflow    = Workflow::factory()->recycle($user->team)->create();
        $workflowJob = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

        $this->mock(OpenAiApi::class)->shouldReceive('complete')->andReturn(OpenAiCompletionResponse::make([
            'choices' => [
                [
                    'message'       => ['content' => 'This is the artifact'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 6],
        ]));

        // When
        WorkflowService::start($workflowRun);

        // Then
        $this->assertNotNull($workflowRun->artifacts()->exists(), 'The artifact was not produced');
    }

    public function test_start_setsStartingStatusesAndTimestamps(): void
    {
        // Given
        $user = User::first();
        $this->actingAs($user);
        $agent       = Agent::factory()->recycle($user)->create([
            'api' => OpenAiApi::$serviceName,
        ]);
        $workflow    = Workflow::factory()->recycle($user->team)->create();
        $workflowJob = WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create();
        $workflowRun = WorkflowRun::factory()->recycle($workflow)->create();

        $this->mock(OpenAiApi::class)->shouldReceive('complete')->andReturn(OpenAiCompletionResponse::make([
            'choices' => [
                [
                    'message'       => ['content' => 'This is the artifact'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 6],
        ]));

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
}
