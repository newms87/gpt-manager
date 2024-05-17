<?php

namespace Tests\Feature\Workflow;

use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\OpenAiApi;
use App\Jobs\RunWorkflowTaskJob;
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
        RunWorkflowTaskJob::enable();
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
        $this->assertNotNull($workflowRun->artifact, 'The artifact was not produced');
    }
}
