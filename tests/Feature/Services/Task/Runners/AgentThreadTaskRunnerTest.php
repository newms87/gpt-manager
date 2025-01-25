<?php

namespace Feature\Services\Task\Runners;

use App\Models\Task\TaskProcess;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class AgentThreadTaskRunnerTest extends AuthenticatedTestCase
{
    use AiMockData;

    public function test_run_withEmptyThread_agentResponds(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();
        $taskProcess = TaskProcess::factory()->forTaskDefinitionAgent()->create();

        // When
        AgentThreadTaskRunner::make($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->thread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->thread->messages;
        $this->assertCount(1, $messages, 'Thread should have a single message');
    }

    public function test_run_withArtifactOnThread_agentResponds(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();

        $artifactContent = 'hello world';
        $taskProcess     = TaskProcess::factory()->withInputArtifacts(['content' => $artifactContent])->forTaskDefinitionAgent()->create();

        // When
        AgentThreadTaskRunner::make($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->thread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->thread->messages;
        $this->assertCount(2, $messages, 'Thread should be 2 messages: the artifact and the agent response');
        $this->assertEquals($artifactContent, $messages->first()->content, 'First message should be the artifact content');
    }

    public function test_setupAgentThread_withDefinitionAgentInputSubSelection_threadHasFilteredInputData(): void
    {
        // Given
        $this->mocksOpenAiCompletionResponse();

        $artifactAttributes = [
            'content' => 'nothing',
            'data'    => [
                'name'  => 'John Doe',
                'email' => 'john@doe.com',
            ],
        ];

        $subSelection = [
            'type'     => 'object',
            'children' => [
                'email' => ['type' => 'string'],
            ],
        ];

        $taskProcess = TaskProcess::factory()->withInputArtifacts($artifactAttributes)->forTaskDefinitionAgent([
            'include_data'        => true,
            'input_sub_selection' => $subSelection,
        ])->create();

        // When
        AgentThreadTaskRunner::make($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $messages = $taskProcess->thread?->messages ?? [];
        $this->assertCount(2, $messages, 'Thread should be 2 messages: the artifact and the agent response');
        $this->assertEquals(['data' => ['email' => $artifactAttributes['data']['email']]], $messages->first()->getJsonContent(), 'First message should be the artifact content');
    }
}
