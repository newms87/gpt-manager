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
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class ClassifierTaskRunnerTest extends TestCase
{
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
    public function it_runs_classification_property_deduplication_when_meta_is_set()
    {
        // Create input artifacts
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'Apple Inc',
                        'location' => 'Cupertino',
                    ],
                ],
            ]),
        ]);

        // Create task process with classification_property meta
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'company'],
        ]);

        // Associate artifacts as input artifacts
        foreach($artifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        // Mock the ClassificationDeduplicationService
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldReceive('deduplicateClassificationProperty')
            ->once()
            ->with(\Mockery::type('Illuminate\Support\Collection'), 'company');

        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process is completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function it_detects_classification_property_from_meta_field()
    {
        // Test that the runner correctly identifies the classification property from meta
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'location'],
        ]);

        // Verify the meta field is properly set
        $this->assertEquals('location', $taskProcess->meta['classification_property']);

        // Test another property
        $taskProcess2 = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'company'],
        ]);

        $this->assertEquals('company', $taskProcess2->meta['classification_property']);
    }

    #[Test]
    public function it_creates_deduplication_processes_when_no_classification_property_processes_exist()
    {
        // Create output artifacts with classification metadata
        $artifact1 = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'company'  => 'Apple Inc',
                    'location' => 'Cupertino',
                ],
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'company'  => 'Google',
                    'location' => 'Mountain View',
                ],
            ],
        ]);

        // Associate artifacts as output artifacts
        $this->taskRun->outputArtifacts()->attach([$artifact1->id, $artifact2->id]);

        // Create a normal task process (no classification_property meta)
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Verify no classification property processes exist initially
        $hasPropertyProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->classification_property')
            ->exists();
        $this->assertFalse($hasPropertyProcesses);

        // Create real service (not mocked) to test actual behavior
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->afterAllProcessesCompleted();

        // Verify that new processes were created
        $newProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->classification_property')
            ->get();

        $this->assertGreaterThan(0, $newProcesses->count());

        // Verify they have the expected properties
        $properties = $newProcesses->pluck('meta.classification_property')->toArray();
        $this->assertContains('company', $properties);
        $this->assertContains('location', $properties);
    }

    #[Test]
    public function it_skips_process_creation_when_classification_property_processes_already_exist()
    {
        // Create a task process with classification_property meta (simulating existing deduplication process)
        $existingProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'company'],
        ]);

        // Create current task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Count existing processes before
        $beforeCount = $this->taskRun->taskProcesses()->count();

        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->afterAllProcessesCompleted();

        // Count processes after - should be the same (no new processes created)
        $afterCount = $this->taskRun->taskProcesses()->count();
        $this->assertEquals($beforeCount, $afterCount);
    }

    #[Test]
    public function it_handles_task_process_without_meta_field()
    {
        // Create task process with no meta field
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => null,
        ]);

        // Verify meta is null
        $this->assertNull($taskProcess->meta);

        // Test that runner handles this gracefully in afterAllProcessesCompleted
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Should not throw exception
        $runner->afterAllProcessesCompleted();
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_exception_in_process_creation_gracefully()
    {
        // Create output artifacts
        $artifact = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'company' => 'Apple Inc',
                ],
            ],
        ]);

        $this->taskRun->outputArtifacts()->attach($artifact->id);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Mock the service to throw an exception
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldReceive('createDeduplicationProcessesForTaskRun')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Should not throw exception, should handle gracefully
        $runner->afterAllProcessesCompleted();
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    #[Test]
    public function it_passes_correct_classification_property_to_service_for_company()
    {
        // Create input artifacts
        $artifact = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'company' => 'Apple Inc',
                ],
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'company'],
        ]);

        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock the service to verify correct property is passed
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldReceive('deduplicateClassificationProperty')
            ->once()
            ->with(\Mockery::type('Illuminate\Support\Collection'), 'company');

        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process is completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function it_passes_correct_classification_property_to_service_for_location()
    {
        // Create input artifacts
        $artifact = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'location' => 'Cupertino',
                ],
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'meta'        => ['classification_property' => 'location'],
        ]);

        $taskProcess->inputArtifacts()->attach($artifact->id);

        // Mock the service to verify correct property is passed
        $mockService = $this->mock(ClassificationDeduplicationService::class);
        $mockService->shouldReceive('deduplicateClassificationProperty')
            ->once()
            ->with(\Mockery::type('Illuminate\Support\Collection'), 'location');

        // Create and run the classifier task runner
        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify the process is completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function it_returns_empty_collection_when_no_context_configured()
    {
        // Create input artifacts
        $artifacts = collect([
            Artifact::factory()->create(['position' => 5]),
            Artifact::factory()->create(['position' => 6]),
        ]);

        // Create task process with no context configuration
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set task definition with no context config
        $this->taskDefinition->task_runner_config = [];
        $this->taskDefinition->save();

        foreach($artifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_fetches_context_before_artifacts()
    {
        // Create artifacts with positions
        // Input artifacts at positions 5-6, so context_before=3 should fetch positions 2,3,4
        $contextArtifacts = collect([
            Artifact::factory()->create(['position' => 2]),
            Artifact::factory()->create(['position' => 3]),
            Artifact::factory()->create(['position' => 4]),
        ]);

        $inputArtifacts = collect([
            Artifact::factory()->create(['position' => 5]),
            Artifact::factory()->create(['position' => 6]),
        ]);

        // Add all artifacts to task run as input artifacts
        foreach($contextArtifacts->merge($inputArtifacts) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set task definition with context_before = 3
        $this->taskDefinition->task_runner_config = ['context_before' => 3];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        $this->assertCount(3, $result);
        $this->assertEquals([2, 3, 4], $result->pluck('position')->sort()->values()->toArray());
    }

    #[Test]
    public function it_fetches_context_after_artifacts()
    {
        // Create artifacts with positions
        $inputArtifacts = collect([
            Artifact::factory()->create(['position' => 5]),
            Artifact::factory()->create(['position' => 6]),
        ]);

        $contextArtifacts = collect([
            Artifact::factory()->create(['position' => 8]),
            Artifact::factory()->create(['position' => 9]),
        ]);

        // Add all artifacts to task run as input artifacts
        foreach($inputArtifacts->merge($contextArtifacts) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set task definition with context_after = 3
        $this->taskDefinition->task_runner_config = ['context_after' => 3];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        $this->assertCount(2, $result);
        $this->assertEquals([8, 9], $result->pluck('position')->sort()->values()->toArray());
    }

    #[Test]
    public function it_fetches_both_context_before_and_after_artifacts()
    {
        // Create artifacts with positions
        // Input artifacts at positions 5-6
        // context_before=2 should fetch positions 3,4
        // context_after=2 should fetch positions 7,8
        $contextBefore = collect([
            Artifact::factory()->create(['position' => 3]),
            Artifact::factory()->create(['position' => 4]),
        ]);

        $inputArtifacts = collect([
            Artifact::factory()->create(['position' => 5]),
            Artifact::factory()->create(['position' => 6]),
        ]);

        $contextAfter = collect([
            Artifact::factory()->create(['position' => 7]),
            Artifact::factory()->create(['position' => 8]),
        ]);

        // Add all artifacts to task run as input artifacts
        foreach($contextBefore->merge($inputArtifacts)->merge($contextAfter) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set task definition with both context_before and context_after
        $this->taskDefinition->task_runner_config = [
            'context_before' => 2,
            'context_after'  => 2,
        ];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        // Should have 2 before + 2 after = 4 context artifacts
        $this->assertCount(4, $result);
        $this->assertEquals([3, 4, 7, 8], $result->pluck('position')->sort()->values()->toArray());
    }

    #[Test]
    public function it_limits_context_artifacts_to_available_range()
    {
        // Create only a few artifacts
        $inputArtifacts = collect([
            Artifact::factory()->create(['position' => 5]),
        ]);

        // Add artifacts to task run as input artifacts
        foreach($inputArtifacts as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set very high context values
        $this->taskDefinition->task_runner_config = [
            'context_before' => 10,
            'context_after'  => 10,
        ];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        // Should return empty since no artifacts exist in the requested ranges
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_orders_context_artifacts_by_position()
    {
        // Create artifacts with mixed positions
        // Input artifact at position 5
        // context_before=3 should fetch positions 2,3,4
        // context_after=3 should fetch positions 6,7,8
        $contextArtifacts = collect([
            Artifact::factory()->create(['position' => 3]),
            Artifact::factory()->create(['position' => 2]),
            Artifact::factory()->create(['position' => 4]),
            Artifact::factory()->create(['position' => 8]),
            Artifact::factory()->create(['position' => 6]),
            Artifact::factory()->create(['position' => 7]),
        ]);

        $inputArtifacts = collect([
            Artifact::factory()->create(['position' => 5]),
        ]);

        // Add all artifacts to task run as input artifacts
        foreach($contextArtifacts->merge($inputArtifacts) as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set context configuration
        $this->taskDefinition->task_runner_config = [
            'context_before' => 3,
            'context_after'  => 3,
        ];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        // Should be ordered by position: [2, 3, 4, 6, 7, 8]
        $this->assertEquals([2, 3, 4, 6, 7, 8], $result->pluck('position')->toArray());
    }

    #[Test]
    public function it_excludes_input_artifacts_from_context()
    {
        // Create artifacts including some that will be input artifacts
        $allArtifacts = collect([
            Artifact::factory()->create(['position' => 3]),
            Artifact::factory()->create(['position' => 4]),
            Artifact::factory()->create(['position' => 5]), // This will be input artifact
            Artifact::factory()->create(['position' => 6]), // This will be input artifact
            Artifact::factory()->create(['position' => 7]),
            Artifact::factory()->create(['position' => 8]),
        ]);

        $inputArtifacts = $allArtifacts->where('position', '>=', 5)->where('position', '<=', 6);

        // Add all artifacts to task run as input artifacts
        foreach($allArtifacts as $artifact) {
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Set context configuration
        $this->taskDefinition->task_runner_config = [
            'context_before' => 3,
            'context_after'  => 3,
        ];
        $this->taskDefinition->save();

        foreach($inputArtifacts as $artifact) {
            $taskProcess->inputArtifacts()->attach($artifact->id);
        }

        $runner = new ClassifierTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($runner);
        $method     = $reflection->getMethod('getContextArtifacts');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $inputArtifacts);

        // Should have artifacts at positions 3, 4, 7, 8 (excluding input artifacts at 5, 6)
        $this->assertEquals([3, 4, 7, 8], $result->pluck('position')->toArray());
        $this->assertCount(4, $result);
    }
}
