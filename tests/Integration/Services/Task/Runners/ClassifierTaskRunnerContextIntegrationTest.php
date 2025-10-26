<?php

namespace Tests\Integration\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\Task\Runners\ClassifierTaskRunner;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class ClassifierTaskRunnerContextIntegrationTest extends TestCase
{
    protected Team $team;

    protected User $user;

    protected TaskRun $taskRun;

    protected TaskDefinition $taskDefinition;

    protected Agent $agent;

    protected SchemaDefinition $schemaDefinition;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user);
        $this->user->currentTeam = $this->team;
        $this->actingAs($this->user);

        // Configure TestAI
        Config::set('ai.models.test-model', [
            'api'      => TestAiApi::class,
            'name'     => 'Test Model',
            'context'  => 4096,
            'input'    => 0,
            'output'   => 0,
            'features' => [
                'temperature' => true,
            ],
        ]);

        // Create schema definition
        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->team->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'company'          => [
                        'type'        => 'string',
                        'description' => 'The company name mentioned in the document',
                    ],
                    'location'         => [
                        'type'        => 'string',
                        'description' => 'The location mentioned in the document',
                    ],
                    'continued_before' => [
                        'type'        => 'boolean',
                        'description' => 'True if content flows from previous artifact',
                    ],
                    'continued_after'  => [
                        'type'        => 'boolean',
                        'description' => 'True if content flows to next artifact',
                    ],
                ],
                'required'   => ['company', 'location', 'continued_before', 'continued_after'],
            ],
        ]);

        // Create agent
        $this->agent = Agent::factory()->create([
            'team_id' => $this->team->id,
            'model'   => 'test-model',
        ]);

        // Create task definition with context configuration
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->team->id,
            'agent_id'             => $this->agent->id,
            'schema_definition_id' => $this->schemaDefinition->id,
            'response_format'      => 'json_schema',
            'task_runner_name'     => 'Classifier',
            'task_runner_config'   => [
                'context_before' => 2,
                'context_after'  => 1,
            ],
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'started_at'         => now()->subMinutes(10),
            'completed_at'       => now(),
        ]);
    }

    #[Test]
    public function it_runs_classification_with_context_artifacts()
    {
        // Create artifacts with specific positions and content
        $contextBefore = collect([
            Artifact::factory()->create([
                'position'     => 1,
                'text_content' => 'Apple Inc is mentioned in the beginning of the document.',
            ]),
            Artifact::factory()->create([
                'position'     => 2,
                'text_content' => 'The company is based in Cupertino, California...',
            ]),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 3,
                'text_content' => '...and continues to innovate in technology.',
            ]),
        ]);

        $contextAfter = collect([
            Artifact::factory()->create([
                'position'     => 4,
                'text_content' => 'The headquarters are located in Silicon Valley.',
            ]),
        ]);

        // Add all artifacts to task run
        foreach ($contextBefore->merge($primaryArtifacts)->merge($contextAfter) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        // Create task process with only primary artifacts as input
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        foreach ($primaryArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'company'          => 'Apple Inc',
            'location'         => 'Cupertino',
            'continued_before' => true,
            'continued_after'  => true,
        ]));

        // Run the classifier
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify that context was included in the thread
        $agentThread = $taskProcess->fresh()->agentThread;
        $this->assertNotNull($agentThread);

        $messages = $agentThread->messages->pluck('content')->join(' ');
        $this->assertStringContainsString('--- CONTEXT BEFORE ---', $messages);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messages);
        $this->assertStringContainsString('--- CONTEXT AFTER ---', $messages);

        // Verify context artifacts content is included
        $this->assertStringContainsString('Apple Inc is mentioned in the beginning', $messages);
        $this->assertStringContainsString('based in Cupertino, California', $messages);
        $this->assertStringContainsString('headquarters are located in Silicon Valley', $messages);

        // Verify classification instructions were added
        $this->assertStringContainsString('IMPORTANT CLASSIFICATION RULES', $messages);
        $this->assertStringContainsString('classifying ONLY the PRIMARY ARTIFACTS section', $messages);
        $this->assertStringContainsString('continued_before=true if content flows from context before', $messages);

        // Verify the primary artifact was classified with context awareness
        $primaryArtifact = $primaryArtifacts->first()->fresh();
        $this->assertNotNull($primaryArtifact->meta['classification']);
        $this->assertEquals('Apple Inc', $primaryArtifact->meta['classification']['company']);
        $this->assertEquals('Cupertino', $primaryArtifact->meta['classification']['location']);
        $this->assertTrue($primaryArtifact->meta['classification']['continued_before']);
        $this->assertTrue($primaryArtifact->meta['classification']['continued_after']);
    }

    #[Test]
    public function it_runs_classification_without_context_when_not_configured()
    {
        // Create task definition without context configuration
        $taskDefinitionNoContext = TaskDefinition::factory()->create([
            'team_id'              => $this->team->id,
            'agent_id'             => $this->agent->id,
            'schema_definition_id' => $this->schemaDefinition->id,
            'response_format'      => 'json_schema',
            'task_runner_name'     => 'Classifier',
            'task_runner_config'   => [], // No context configuration
        ]);

        $taskRunNoContext = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinitionNoContext->id,
        ]);

        // Create artifacts
        $primaryArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 3,
                'text_content' => 'Google Inc is based in Mountain View.',
            ]),
        ]);

        $taskRunNoContext->inputArtifacts()->attach($primaryArtifacts->first()->id);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRunNoContext->id,
        ]);

        $taskProcess->inputArtifacts()->attach($primaryArtifacts->first()->id);

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'company'          => 'Google Inc',
            'location'         => 'Mountain View',
            'continued_before' => false,
            'continued_after'  => false,
        ]));

        // Run the classifier
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($taskRunNoContext)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify that no context sections were added
        $agentThread = $taskProcess->fresh()->agentThread;
        $messages    = $agentThread->messages->pluck('content')->join(' ');
        $this->assertStringNotContainsString('--- CONTEXT BEFORE ---', $messages);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messages);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messages);
    }

    #[Test]
    public function it_handles_partial_context_configuration()
    {
        // Create task definition with only context_before
        $this->taskDefinition->task_runner_config = ['context_before' => 1];
        $this->taskDefinition->task_runner_name   = 'Classifier';
        $this->taskDefinition->save();

        // Create artifacts
        $contextBefore = collect([
            Artifact::factory()->create([
                'position'     => 2,
                'text_content' => 'Microsoft Corporation context before.',
            ]),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 3,
                'text_content' => 'Microsoft develops software products.',
            ]),
        ]);

        // Add all artifacts to task run
        foreach ($contextBefore->merge($primaryArtifacts) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        foreach ($primaryArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'company'          => 'Microsoft Corporation',
            'location'         => 'Redmond',
            'continued_before' => true,
            'continued_after'  => false,
        ]));

        // Run the classifier
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify that only context before was included
        $agentThread = $taskProcess->fresh()->agentThread;
        $messages    = $agentThread->messages->pluck('content')->join(' ');
        $this->assertStringContainsString('--- CONTEXT BEFORE ---', $messages);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messages);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messages);
    }

    #[Test]
    public function it_handles_context_artifacts_with_no_available_range()
    {
        // Create primary artifact at position 1 (no room for context before)
        $primaryArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 1,
                'text_content' => 'Amazon Web Services is a cloud platform.',
            ]),
        ]);

        $this->taskRun->inputArtifacts()->attach($primaryArtifacts->first()->id);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $taskProcess->inputArtifacts()->attach($primaryArtifacts->first()->id);

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'company'          => 'Amazon',
            'location'         => 'Seattle',
            'continued_before' => false,
            'continued_after'  => false,
        ]));

        // Run the classifier
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process completed successfully
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify that no context sections were added (no artifacts available)
        $agentThread = $taskProcess->fresh()->agentThread;
        $messages    = $agentThread->messages->pluck('content')->join(' ');
        $this->assertStringNotContainsString('--- CONTEXT BEFORE ---', $messages);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messages);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messages);
    }

    #[Test]
    public function it_maintains_artifact_position_ordering_in_context()
    {
        // Create artifacts with positions that will be included in context
        // Primary artifact at position 5
        // context_before=2 should fetch positions 3,4
        // context_after=1 should fetch position 6
        $contextArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 3,
                'text_content' => 'Third context artifact.',
            ]),
            Artifact::factory()->create([
                'position'     => 4,
                'text_content' => 'Fourth context artifact.',
            ]),
            Artifact::factory()->create([
                'position'     => 6,
                'text_content' => 'Sixth context artifact.',
            ]),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create([
                'position'     => 5,
                'text_content' => 'Primary artifact at position 5.',
            ]),
        ]);

        // Add all artifacts to task run
        foreach ($contextArtifacts->merge($primaryArtifacts) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $taskProcess->inputArtifacts()->attach($primaryArtifacts->first()->id);

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'company'          => 'Test Company',
            'location'         => 'Test Location',
            'continued_before' => false,
            'continued_after'  => false,
        ]));

        // Run the classifier
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify ordering in the thread messages
        $agentThread = $taskProcess->fresh()->agentThread;
        $messages    = $agentThread->messages->pluck('content')->join(' ');

        // Verify context artifacts appear in correct order
        $thirdPos   = strpos($messages, 'Third context artifact');
        $fourthPos  = strpos($messages, 'Fourth context artifact');
        $primaryPos = strpos($messages, 'Primary artifact at position 5');
        $sixthPos   = strpos($messages, 'Sixth context artifact');

        // Context before should appear before primary
        $this->assertNotFalse($thirdPos, 'Third context artifact not found');
        $this->assertNotFalse($fourthPos, 'Fourth context artifact not found');
        $this->assertNotFalse($primaryPos, 'Primary artifact not found');
        $this->assertNotFalse($sixthPos, 'Sixth context artifact not found');

        // Verify ordering: third < fourth < primary < sixth
        $this->assertLessThan($fourthPos, $thirdPos);
        $this->assertLessThan($primaryPos, $fourthPos);
        $this->assertLessThan($sixthPos, $primaryPos);
    }
}
