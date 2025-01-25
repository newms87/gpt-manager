<?php

namespace Feature\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptSchema;
use App\Models\Task\TaskProcess;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\Feature\Api\TestAi\TestAiApi;

class AgentThreadTaskRunnerTest extends AuthenticatedTestCase
{
    public function test_run_withEmptyThread_agentResponds(): void
    {
        // Given
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
        $artifactContent = 'hello world';
        $taskProcess     = TaskProcess::factory()->withInputArtifacts(['content' => $artifactContent])->forTaskDefinitionAgent([
            'include_text' => true,
        ])->create();

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

    public function test_setupAgentThread_withDefinitionAgentOutputSubSelection_completeApiCallHasFilteredStructuredOutput(): void
    {
        // Given
        $inputSchema  = PromptSchema::factory()->create([
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'phone' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ]);
        $agent        = Agent::factory()->withJsonSchemaResponse($inputSchema)->create();
        $outputSchema = PromptSchema::factory()->create([
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'phone' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'dob'   => ['type' => 'string'],
                ],
            ],
        ]);
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'email' => ['type' => 'string'],
                'dob'   => ['type' => 'string'],
            ],
        ];

        $taskProcess = TaskProcess::factory()->forTaskDefinitionAgent([
            'agent_id'             => $agent,
            'output_schema_id'     => $outputSchema->id,
            'output_sub_selection' => $subSelection,
        ])->create();

        // Then
        $this->partialMock(TestAiApi::class)
            ->shouldReceive('complete')->withArgs(function ($model, $messages, $options) use ($subSelection) {
                $this->assertEquals(Agent::RESPONSE_FORMAT_JSON_SCHEMA, $options['response_format']['type'] ?? null);
                $this->assertNull($options['response_format']['json_schema']['schema']['properties']['phone'] ?? null, 'Phone should NOT be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['email'] ?? null, 'Email should be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['dob'] ?? null, 'DOB should be in the response schema');

                return true;
            })->andReturn(new TestAiCompletionResponse());

        // When
        AgentThreadTaskRunner::make($taskProcess)->run();
    }
}
