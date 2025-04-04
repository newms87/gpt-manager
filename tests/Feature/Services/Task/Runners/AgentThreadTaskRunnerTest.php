<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Agent\AgentThreadRun;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaAssociation;
use App\Models\Task\TaskDefinitionDirective;
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
        $taskProcess = TaskProcess::factory()->withAgent()->create();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

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
        $taskProcess     = TaskProcess::factory()->withAgent()->withInputArtifacts(['text_content' => $artifactContent])->create();

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(2, $messages, 'AgentThread should be 2 messages: the artifact and the agent response');
        $this->assertEquals($artifactContent, $messages->first()->content, 'First message should be the artifact content');
    }

    public function test_run_withTaskDefinitionDirectives_directivesAreAddedToThread(): void
    {
        // Given
        $directiveContent = 'Do as i say, not as i do.';
        $taskProcess      = TaskProcess::factory()->withAgent()->create();
        $promptDirective  = PromptDirective::factory()->create(['directive_text' => $directiveContent]);
        $taskProcess->taskRun->taskDefinition->taskDefinitionDirectives()->create([
            'prompt_directive_id' => $promptDirective->id,
        ]);

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();

        // Then
        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->agentThread, 'TaskProcess thread should have been created');
        $messages = $taskProcess->agentThread->messages;
        $this->assertCount(2, $messages, 'AgentThread should have 2 messages: The directive task and the agent response');
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
        $this->assertCount(4, $messages, 'AgentThread should have 4 messages: Before directive, artifact content, after directive and the agent response');
        $this->assertEquals($beforeContent, $messages[0]->content, 'First message should be the before directive content');
        $this->assertEquals($artifactContent, $messages[1]->content, 'Second message should be the artifact content');
        $this->assertEquals($afterContent, $messages[2]->content, 'Third message should be the after directive content');
    }

    public function test_setupAgentThread_withOutputFragment_completeApiCallHasFilteredStructuredOutput(): void
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


        // Then
        $this->partialMock(TestAiApi::class)
            ->shouldReceive('complete')->withArgs(function ($model, $messages, $options) {
                $this->assertEquals(AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA, $options['response_format']['type'] ?? null);
                $this->assertNull($options['response_format']['json_schema']['schema']['properties']['phone'] ?? null, 'Phone should NOT be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['email'] ?? null, 'Email should be in the response schema');
                $this->assertNotNull($options['response_format']['json_schema']['schema']['properties']['dob'] ?? null, 'DOB should be in the response schema');

                return true;
            })->andReturn(new TestAiCompletionResponse());

        // When
        AgentThreadTaskRunner::make()->setTaskRun($taskProcess->taskRun)->setTaskProcess($taskProcess)->run();
    }
}
