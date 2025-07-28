<?php

namespace Tests\Unit\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\AgentThread\TaskDefinitionToAgentThreadMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class TaskDefinitionToAgentThreadMapperTest extends TestCase
{
    use RefreshDatabase;

    protected Team           $team;
    protected User           $user;
    protected TaskRun        $taskRun;
    protected TaskDefinition $taskDefinition;
    protected Agent          $agent;

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
            'api'     => TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
            'input'   => 0,
            'output'  => 0,
        ]);

        // Create agent
        $this->agent = Agent::factory()->create([
            'team_id' => $this->team->id,
            'model'   => 'test-model',
        ]);

        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'         => $this->team->id,
            'agent_id'        => $this->agent->id,
            'response_format' => 'json_schema',
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'started_at'         => now()->subMinutes(10),
            'completed_at'       => now(),
        ]);
    }

    #[Test]
    public function it_can_set_context_artifacts()
    {
        $mapper = new TaskDefinitionToAgentThreadMapper();
        
        $contextArtifacts = collect([
            Artifact::factory()->create(['position' => 1]),
            Artifact::factory()->create(['position' => 2]),
        ]);

        $result = $mapper->setContextArtifacts($contextArtifacts);

        $this->assertInstanceOf(TaskDefinitionToAgentThreadMapper::class, $result);
        
        // Use reflection to verify the context artifacts were set
        $reflection = new \ReflectionClass($mapper);
        $property = $reflection->getProperty('contextArtifacts');
        $property->setAccessible(true);

        $this->assertEquals($contextArtifacts, $property->getValue($mapper));
    }

    #[Test]
    public function it_can_set_empty_context_artifacts()
    {
        $mapper = new TaskDefinitionToAgentThreadMapper();
        
        $result = $mapper->setContextArtifacts([]);

        $this->assertInstanceOf(TaskDefinitionToAgentThreadMapper::class, $result);
        
        // Use reflection to verify empty context artifacts were set
        $reflection = new \ReflectionClass($mapper);
        $property = $reflection->getProperty('contextArtifacts');
        $property->setAccessible(true);

        $this->assertEquals([], $property->getValue($mapper));
    }

    #[Test]
    public function it_can_set_context_artifacts_as_collection()
    {
        $mapper = new TaskDefinitionToAgentThreadMapper();
        
        $contextArtifacts = new Collection([
            Artifact::factory()->create(['position' => 3]),
            Artifact::factory()->create(['position' => 4]),
        ]);

        $result = $mapper->setContextArtifacts($contextArtifacts);

        $this->assertInstanceOf(TaskDefinitionToAgentThreadMapper::class, $result);
        
        // Use reflection to verify the context artifacts were set
        $reflection = new \ReflectionClass($mapper);
        $property = $reflection->getProperty('contextArtifacts');
        $property->setAccessible(true);

        $this->assertEquals($contextArtifacts, $property->getValue($mapper));
    }

    #[Test]
    public function it_creates_agent_thread_with_context_artifacts()
    {
        // Create artifacts with different positions
        $contextBefore = collect([
            Artifact::factory()->create(['position' => 1, 'text_content' => 'Context before content']),
            Artifact::factory()->create(['position' => 2, 'text_content' => 'More context before']),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact 1']),
            Artifact::factory()->create(['position' => 6, 'text_content' => 'Primary artifact 2']),
        ]);

        $contextAfter = collect([
            Artifact::factory()->create(['position' => 8, 'text_content' => 'Context after content']),
        ]);

        $allContextArtifacts = $contextBefore->merge($contextAfter);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts($allContextArtifacts)
            ->map();

        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // Verify that messages contain context sections
        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('--- CONTEXT BEFORE ---', $messageTexts);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messageTexts);
        $this->assertStringContainsString('--- CONTEXT AFTER ---', $messageTexts);
    }

    #[Test]
    public function it_creates_agent_thread_with_only_context_before()
    {
        $contextBefore = collect([
            Artifact::factory()->create(['position' => 1, 'text_content' => 'Context before content']),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact']),
        ]);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts($contextBefore)
            ->map();

        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('--- CONTEXT BEFORE ---', $messageTexts);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messageTexts);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messageTexts);
    }

    #[Test]
    public function it_creates_agent_thread_with_only_context_after()
    {
        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact']),
        ]);

        $contextAfter = collect([
            Artifact::factory()->create(['position' => 8, 'text_content' => 'Context after content']),
        ]);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts($contextAfter)
            ->map();

        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->join(' ');

        $this->assertStringNotContainsString('--- CONTEXT BEFORE ---', $messageTexts);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messageTexts);
        $this->assertStringContainsString('--- CONTEXT AFTER ---', $messageTexts);
    }

    #[Test]
    public function it_creates_agent_thread_without_context_artifacts()
    {
        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact']),
        ]);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts([])
            ->map();

        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->join(' ');

        $this->assertStringNotContainsString('--- CONTEXT BEFORE ---', $messageTexts);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messageTexts);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messageTexts);
    }

    #[Test]
    public function it_orders_context_artifacts_correctly_in_thread()
    {
        // Create artifacts with specific positions
        $contextBefore = collect([
            Artifact::factory()->create(['position' => 2, 'text_content' => 'Before position 2']),
            Artifact::factory()->create(['position' => 1, 'text_content' => 'Before position 1']),
        ]);

        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact']),
        ]);

        $contextAfter = collect([
            Artifact::factory()->create(['position' => 9, 'text_content' => 'After position 9']),
            Artifact::factory()->create(['position' => 8, 'text_content' => 'After position 8']),
        ]);

        $allContextArtifacts = $contextBefore->merge($contextAfter);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts($allContextArtifacts)
            ->map();

        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->toArray();

        // Find the indices of context sections
        $contextBeforeIndex = null;
        $primaryIndex = null;
        $contextAfterIndex = null;

        foreach ($messageTexts as $index => $text) {
            if (str_contains($text, '--- CONTEXT BEFORE ---')) {
                $contextBeforeIndex = $index;
            } elseif (str_contains($text, '--- PRIMARY ARTIFACTS ---')) {
                $primaryIndex = $index;
            } elseif (str_contains($text, '--- CONTEXT AFTER ---')) {
                $contextAfterIndex = $index;
            }
        }

        // Verify sections appear in correct order
        $this->assertNotNull($contextBeforeIndex);
        $this->assertNotNull($primaryIndex);
        $this->assertNotNull($contextAfterIndex);
        $this->assertLessThan($primaryIndex, $contextBeforeIndex);
        $this->assertLessThan($contextAfterIndex, $primaryIndex);

        // Verify context artifacts are ordered by position within their sections
        $allText = join(' ', $messageTexts);
        $beforePos1 = strpos($allText, 'Before position 1');
        $beforePos2 = strpos($allText, 'Before position 2');
        $afterPos8 = strpos($allText, 'After position 8');
        $afterPos9 = strpos($allText, 'After position 9');

        // Position 1 should come before position 2
        $this->assertLessThan($beforePos2, $beforePos1);
        // Position 8 should come before position 9
        $this->assertLessThan($afterPos9, $afterPos8);
    }

    #[Test]
    public function it_handles_empty_context_artifacts_gracefully()
    {
        $primaryArtifacts = collect([
            Artifact::factory()->create(['position' => 5, 'text_content' => 'Primary artifact']),
        ]);

        $mapper = new TaskDefinitionToAgentThreadMapper();
        $agentThread = $mapper
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskDefinition)
            ->setArtifacts($primaryArtifacts)
            ->setContextArtifacts(collect()) // Empty collection
            ->map();

        $this->assertNotNull($agentThread);
        
        $messages = $agentThread->messages;
        $messageTexts = $messages->pluck('content')->join(' ');

        // Should only contain primary artifacts section
        $this->assertStringNotContainsString('--- CONTEXT BEFORE ---', $messageTexts);
        $this->assertStringContainsString('--- PRIMARY ARTIFACTS ---', $messageTexts);
        $this->assertStringNotContainsString('--- CONTEXT AFTER ---', $messageTexts);
    }
}