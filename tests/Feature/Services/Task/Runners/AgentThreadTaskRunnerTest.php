<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Agent\AgentThreadRun;
use App\Models\Agent\McpServer;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Task\TaskProcess;
use App\Services\Task\Runners\AgentThreadTaskRunner;
use Illuminate\Support\Facades\Config;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\Feature\Api\TestAi\TestAiApi;

class AgentThreadTaskRunnerTest extends AuthenticatedTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Configure test-model for testing
        Config::set('ai.models.test-model', [
            'api'     => TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
        ]);
    }

    public function test_run_withEmptyThread_agentResponds(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(1, $messages, 'AgentThread should have agent response only');
    }

    public function test_run_withArtifactOnThread_agentResponds(): void
    {
        // Given
        $artifactContent = 'hello world';
        $taskProcess     = TaskProcess::factory()->withAgent()->withInputArtifacts(['text_content' => $artifactContent])->create();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(3, $messages, 'AgentThread should be 3 messages: artifacts header, artifact content, and agent response');
        $allContent = $messages->pluck('content')->join(' ');
        $this->assertStringContainsString($artifactContent, $allContent, 'Messages should contain the artifact content');
    }

    public function test_run_withTaskDefinitionDirectives_directivesAreAddedToThread(): void
    {
        // Given
        $directiveContent = 'Do as i say, not as i do.';
        $taskProcess      = TaskProcess::factory()->withAgent()->create();
        $promptDirective  = PromptDirective::factory()->create(['directive_text' => $directiveContent]);
        $taskProcess->taskRun->taskDefinition->taskDefinitionDirectives()->create([
            'prompt_directive_id' => $promptDirective->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
        ]);

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(2, $messages, 'AgentThread should have 2 messages: directive and agent response');
        $this->assertEquals($directiveContent, $messages->first()->content, 'First message should be the directive content');
    }

    public function test_run_withTaskDefinitionDirectives_beforeAndAfterDirectiveSectionsAreRespected(): void
    {
        // Given
        $beforeContent         = 'Do as i say, not as i do.';
        $artifactContent       = "Hey There";
        $afterContent          = 'why would you do that?';
        $beforePromptDirective = PromptDirective::factory()->create(['directive_text' => $beforeContent]);
        $afterPromptDirective  = PromptDirective::factory()->create(['directive_text' => $afterContent]);
        $taskProcess           = TaskProcess::factory()->withAgent()->withInputArtifacts(['text_content' => $artifactContent])->create();
        $taskProcess->taskRun->taskDefinition->taskDefinitionDirectives()->create([
            'prompt_directive_id' => $beforePromptDirective->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
        ]);
        $taskProcess->taskRun->taskDefinition->taskDefinitionDirectives()->create([
            'prompt_directive_id' => $afterPromptDirective->id,
            'section'             => TaskDefinitionDirective::SECTION_BOTTOM,
        ]);

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(5, $messages, 'AgentThread should have 5 messages: before directive, artifacts header, artifact content, after directive, and agent response');
        $allContent = $messages->pluck('content')->join(' ');
        $this->assertStringContainsString($beforeContent, $allContent, 'Messages should contain the before directive content');
        $this->assertStringContainsString($artifactContent, $allContent, 'Messages should contain the artifact content');
        $this->assertStringContainsString($afterContent, $allContent, 'Messages should contain the after directive content');
    }

    public function test_setupAgentThread_withOutputFragment_responsesApiCallHasFilteredStructuredOutput(): void
    {
        // Given
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
        $taskProcess            = TaskProcess::factory()->withAgent()->create();
        $schemaAssociation      = SchemaAssociation::factory()->withObject($taskProcess, 'output')->withSchema($outputSchema, $outputFragmentSelector)->create();
        $taskProcess->taskRun->taskDefinition->schemaDefinition()->associate($schemaAssociation->schemaDefinition)->save();

        $capturedOptions = null;

        // Then
        $this->partialMock(TestAiApi::class)
            ->shouldReceive('responses')->withArgs(function ($model, $messages, $options) use (&$capturedOptions) {
                $capturedOptions = $options->toArray();

                return true;
            })->once()->andReturn(new TestAiCompletionResponse());

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Assert the captured options
        $this->assertNotNull($capturedOptions, 'API options should have been captured');
        $this->assertEquals(AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA, $capturedOptions['text']['format']['type'] ?? null);
        $this->assertNull($capturedOptions['text']['format']['schema']['properties']['phone'] ?? null, 'Phone should NOT be in the response schema');
        $this->assertNotNull($capturedOptions['text']['format']['schema']['properties']['email'] ?? null, 'Email should be in the response schema');
        $this->assertNotNull($capturedOptions['text']['format']['schema']['properties']['dob'] ?? null, 'DOB should be in the response schema');
    }

    public function test_run_withMcpServer_mcpServerIsPassedToApi(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();

        $mcpServer = McpServer::factory()->create([
            'team_id'       => $taskProcess->taskRun->taskDefinition->team_id,
            'name'          => 'Test MCP Server',
            'server_url'    => 'https://test-mcp-server.com',
            'allowed_tools' => ['tool1', 'tool2'],
            'headers'       => ['Authorization' => 'Bearer token123'],
        ]);
        $taskProcess->taskRun->taskDefinition->update([
            'task_runner_config' => ['mcp_server_id' => $mcpServer->id],
        ]);
        $taskProcess->taskRun->taskDefinition->refresh();

        // Verify the task definition can find the MCP server
        $foundMcpServer = $taskProcess->taskRun->taskDefinition->getMcpServer();
        $this->assertNotNull($foundMcpServer, 'MCP server should be found via TaskDefinition');
        $this->assertEquals($mcpServer->id, $foundMcpServer->id, 'MCP server IDs should match');

        $capturedOptions = null;

        // Then
        $this->partialMock(TestAiApi::class)
            ->shouldReceive('responses')->withArgs(function ($model, $messages, $options) use (&$capturedOptions) {
                $capturedOptions = $options->toArray();

                return true;
            })->once()->andReturn(new TestAiCompletionResponse());

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Assert the captured options
        $this->assertNotNull($capturedOptions, 'API options should have been captured');
        $tools = $capturedOptions['tools'] ?? [];
        $this->assertNotEmpty($tools, 'Tools should be included in options');
        $this->assertEquals('mcp', $tools[0]['type']);
        $this->assertEquals($mcpServer->server_url, $tools[0]['server_url']);
        $this->assertEquals($mcpServer->name, $tools[0]['server_label']);
        $this->assertEquals($mcpServer->allowed_tools, $tools[0]['allowed_tools']);
        $this->assertEquals($mcpServer->headers, $tools[0]['headers']);

        // Verify the AgentThreadRun has the MCP server relationship set
        $taskProcess->refresh();
        $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
        $this->assertNotNull($agentThreadRun, 'AgentThreadRun should exist');
        $this->assertEquals($mcpServer->id, $agentThreadRun->mcp_server_id, 'AgentThreadRun should have MCP server ID set');
        $this->assertNotNull($agentThreadRun->mcpServer, 'AgentThreadRun should have MCP server relationship');
        $this->assertEquals($mcpServer->id, $agentThreadRun->mcpServer->id, 'AgentThreadRun MCP server should match');
    }

    public function test_run_withNoTimeoutConfig_usesNullApiTimeout(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
        $this->assertNotNull($agentThreadRun, 'AgentThreadRun should exist');
        $this->assertNull($agentThreadRun->timeout, 'Default API timeout should be null (uses default HTTP timeout)');
    }

    public function test_run_withValidTimeoutConfig_setsApiTimeoutCorrectly(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();
        $taskProcess->taskRun->taskDefinition->update([
            'task_runner_config' => ['timeout' => 120],
        ]);
        $taskProcess->taskRun->taskDefinition->refresh();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
        $this->assertNotNull($agentThreadRun, 'AgentThreadRun should exist');
        $this->assertEquals(120, $agentThreadRun->timeout, 'AgentThreadRun timeout should match configured value');
    }

    public function test_run_withTimeoutBelowMinimum_clampsApiTimeoutToMinimum(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();
        $taskProcess->taskRun->taskDefinition->update([
            'task_runner_config' => ['timeout' => 0],
        ]);
        $taskProcess->taskRun->taskDefinition->refresh();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
        $this->assertNotNull($agentThreadRun, 'AgentThreadRun should exist');
        $this->assertEquals(1, $agentThreadRun->timeout, 'AgentThreadRun API timeout should be clamped to minimum (1 second)');
    }

    public function test_run_withTimeoutAboveMaximum_clampsApiTimeoutToMaximum(): void
    {
        // Given
        $taskProcess = TaskProcess::factory()->withAgent()->create();
        $taskProcess->taskRun->taskDefinition->update([
            'task_runner_config' => ['timeout' => 1000],
        ]);
        $taskProcess->taskRun->taskDefinition->refresh();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
        $this->assertNotNull($agentThreadRun, 'AgentThreadRun should exist');
        $this->assertEquals(600, $agentThreadRun->timeout, 'AgentThreadRun API timeout should be clamped to maximum (600 seconds)');
    }

    public function test_run_withValidTimeoutRange_passesApiTimeoutCorrectly(): void
    {
        $testTimeouts = [1, 30, 60, 300, 600];

        foreach($testTimeouts as $timeout) {
            // Given
            $taskProcess = TaskProcess::factory()->withAgent()->create();
            $taskProcess->taskRun->taskDefinition->update([
                'task_runner_config' => ['timeout' => $timeout],
            ]);
            $taskProcess->taskRun->taskDefinition->refresh();

            // When
            AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

            // Then
            $taskProcess->refresh();
            $agentThreadRun = $taskProcess->agentThread->runs()->latest()->first();
            $this->assertNotNull($agentThreadRun, "AgentThreadRun should exist for API timeout {$timeout}");
            $this->assertEquals($timeout, $agentThreadRun->timeout, "AgentThreadRun API timeout should match configured value: {$timeout}");
        }
    }

}
