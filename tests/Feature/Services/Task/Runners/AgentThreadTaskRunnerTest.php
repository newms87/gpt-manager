<?php

namespace Feature\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinitionAgent;
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
        AgentThreadTaskRunner::make($taskProcess->taskRun, $taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(1, $messages, 'AgentThread should have a single message');
    }

    public function test_run_withArtifactOnThread_agentResponds(): void
    {
        // Given
        $artifactContent = 'hello world';
        $taskProcess     = TaskProcess::factory()->withInputArtifacts(['text_content' => $artifactContent])->forTaskDefinitionAgent([
            'include_text' => true,
        ])->create();

        // When
        AgentThreadTaskRunner::make($taskProcess->taskRun, $taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(2, $messages, 'AgentThread should be 2 messages: the artifact and the agent response');
        $this->assertEquals($artifactContent, $messages->first()->content, 'First message should be the artifact content');
    }

    public function test_setupAgentThread_withDefinitionAgentInputFragment_threadHasFilteredInputData(): void
    {
        // Given
        $artifactAttributes = [
            'text_content' => 'nothing',
            'json_content' => [
                'name'  => 'John Doe',
                'email' => 'john@doe.com',
            ],
        ];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'email' => ['type' => 'string'],
            ],
        ];

        $taskProcess = TaskProcess::factory()->withInputArtifacts($artifactAttributes)->forTaskDefinitionAgent([
            'include_data'             => true,
            'input_schema_fragment_id' => SchemaFragment::factory()->create(['fragment_selector' => $fragmentSelector]),
        ])->create();

        // When
        AgentThreadTaskRunner::make($taskProcess->taskRun, $taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $messages = $taskProcess->agentThread?->messages ?? [];
        $this->assertCount(2, $messages, 'AgentThread should be 2 messages: the artifact and the agent response');
        $this->assertEquals(['json_content' => ['email' => $artifactAttributes['json_content']['email']]], $messages->first()->getJsonContent(), 'First message should be the artifact content');
    }

    public function test_setupAgentThread_withDefinitionAgentOutputFragment_completeApiCallHasFilteredStructuredOutput(): void
    {
        // Given
        $inputSchema            = SchemaDefinition::factory()->create([
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'phone' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ],
        ]);
        $agent                  = Agent::factory()->withJsonSchemaResponse($inputSchema)->create();
        $outputSchema           = [
            'type'       => 'object',
            'properties' => [
                'phone' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'dob'   => ['type' => 'string'],
            ],
        ];
        $outputFragmentSelector = [
            'type'     => 'object',
            'children' => [
                'email' => ['type' => 'string'],
                'dob'   => ['type' => 'string'],
            ],
        ];
        $taskDefinitionAgent    = TaskDefinitionAgent::factory()->withOutputSchema($outputSchema, $outputFragmentSelector)->create(['agent_id' => $agent]);
        $taskProcess            = TaskProcess::factory()->create(['task_definition_agent_id' => $taskDefinitionAgent]);
        
        // Then
        $this->partialMock(TestAiApi::class)
            ->shouldReceive('complete')->withArgs(function ($model, $messages, $options) {
                $this->assertEquals(Agent::RESPONSE_FORMAT_JSON_SCHEMA, $options['response_format']['type'] ?? null);
                $this->assertNull($options['response_format']['json_schema']['schema']['properties']['phone'] ?? null, 'Phone should NOT be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['email'] ?? null, 'Email should be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['dob'] ?? null, 'DOB should be in the response schema');

                return true;
            })->andReturn(new TestAiCompletionResponse());

        // When
        AgentThreadTaskRunner::make($taskProcess->taskRun, $taskProcess)->run();
    }
}
