<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\Task\ClassificationDeduplicationService;
use App\Services\Task\Runners\ClassifierTaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class ClassifierTaskRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;
    protected User $user;
    protected TaskRun $taskRun;
    protected TaskDefinition $taskDefinition;
    protected Agent $agent;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user);
        $this->user->currentTeam = $this->team;
        $this->actingAs($this->user);
        
        // Configure TestAI
        Config::set('ai.apis.TestAI', TestAiApi::class);
        Config::set('ai.models.TestAI', [
            'test-model' => [
                'name' => 'Test Model',
                'context' => 4096,
                'input' => 0,
                'output' => 0,
            ],
        ]);
        
        // Create agent
        $this->agent = Agent::factory()->create([
            'team_id' => $this->team->id,
            'api' => TestAiApi::$serviceName,
            'model' => 'test-model',
        ]);
        
        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
            'response_format' => 'json_schema',
        ]);
        
        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }

    #[Test]
    public function it_calls_classification_deduplication_after_all_processes_completed()
    {
        // Create output artifacts with classification metadata
        $artifact1 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [
                'classification' => [
                    'category' => 'HEALTHCARE',
                    'subcategory' => 'Primary Care',
                ],
            ],
        ]);
        
        $artifact2 = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [
                'classification' => [
                    'category' => 'healthcare',
                    'subcategory' => 'primary care',
                ],
            ],
        ]);
        
        // Associate artifacts as output artifacts
        $this->taskRun->outputArtifacts()->attach([$artifact1->id, $artifact2->id]);
        
        // Verify artifacts are found before mocking
        $foundArtifacts = $this->taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();
        $this->assertCount(2, $foundArtifacts, "Should find 2 artifacts with classification metadata");
        
        // Mock the ClassificationDeduplicationService
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldReceive('deduplicateClassificationLabels')
            ->once()
            ->with(\Mockery::on(function ($artifacts) use ($artifact1, $artifact2) {
                return $artifacts->count() === 2 &&
                       $artifacts->contains($artifact1) &&
                       $artifacts->contains($artifact2);
            }));
        
        // Create a task process for the runner
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);
        
        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->afterAllProcessesCompleted();
    }

    #[Test]
    public function it_finds_artifacts_with_classification_metadata()
    {
        // Create artifacts with different metadata structures
        $artifactWithClassification = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [
                'classification' => [
                    'category' => 'HEALTHCARE',
                ],
            ],
        ]);
        
        $artifactWithoutClassification = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [
                'other_data' => 'value',
            ],
        ]);
        
        $artifactWithEmptyMeta = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [],
        ]);
        
        // Associate all artifacts as output artifacts
        $this->taskRun->outputArtifacts()->attach([
            $artifactWithClassification->id,
            $artifactWithoutClassification->id,
            $artifactWithEmptyMeta->id,
        ]);
        
        // Test the correct JSON query approach used in the runner
        $artifacts = $this->taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();
            
        // Should only find the artifact with classification metadata
        $this->assertCount(1, $artifacts);
        $this->assertTrue($artifacts->contains($artifactWithClassification));
        $this->assertFalse($artifacts->contains($artifactWithoutClassification));
        $this->assertFalse($artifacts->contains($artifactWithEmptyMeta));
    }

    #[Test]
    public function it_handles_empty_classification_results_gracefully()
    {
        // Create artifacts without classification metadata
        $artifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta' => [
                'other_data' => 'value',
            ],
        ]);
        
        $this->taskRun->outputArtifacts()->attach($artifact->id);
        
        // Mock the service - should not be called
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldNotReceive('deduplicateClassificationLabels');
        
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);
        
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->afterAllProcessesCompleted();
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    #[Test]
    public function it_runs_without_errors_when_no_artifacts_found()
    {
        // Test that the method runs without errors when no artifacts are found
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        
        // Mock the service - should not be called since no artifacts exist
        $this->mock(ClassificationDeduplicationService::class)
            ->shouldNotReceive('deduplicateClassificationLabels');
        
        // Should not throw any exceptions
        $runner->afterAllProcessesCompleted();
        $this->assertTrue(true);
    }
}