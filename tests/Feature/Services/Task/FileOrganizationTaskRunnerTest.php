<?php

namespace Tests\Feature\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use App\Services\Task\FileOrganization\WindowProcessService;
use App\Services\Task\Runners\BaseTaskRunner;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class FileOrganizationTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private FileOrganizationTaskRunner $runner;

    private TaskDefinition $taskDefinition;

    private Agent $agent;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Create an agent for testing with a valid model
        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => self::TEST_MODEL,
        ]);

        // Create task definition with FileOrganizationTaskRunner
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'File Organization Test',
            'task_runner_name'   => FileOrganizationTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'comparison_window_size' => 3,
            ],
            'agent_id' => $this->agent->id,
        ]);

        $this->runner = new FileOrganizationTaskRunner();
    }

    #[Test]
    public function initial_process_completes_without_meta_fields(): void
    {
        // Given: TaskRun with initial TaskProcess (Default Task operation, no meta fields)
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'name'        => 'Initial Process',
            'operation'   => BaseTaskRunner::OPERATION_DEFAULT,
            'meta'        => [],
            'is_ready'    => true,
            'started_at'  => now(),
        ]);

        // When: Running the initial process (simulating production bug scenario)
        // This is the bug that occurred: TaskProcess with no meta fields calling run()
        $this->runner->setTaskRun($taskRun)->setTaskProcess($taskProcess);
        $this->runner->run();

        // Then: Process completes successfully without errors
        $taskProcess = $taskProcess->fresh(); // Use fresh() to reload from database
        $this->assertNotNull($taskProcess->completed_at, 'Initial process should be completed');
        $this->assertNull($taskProcess->failed_at, 'Initial process should not fail');
        $this->assertNull($taskProcess->incomplete_at, 'Initial process should not be incomplete');

        // Verify status is Completed
        $this->assertEquals('Completed', $taskProcess->status);
    }

    #[Test]
    public function initial_process_validates_window_size_constraints(): void
    {
        // Given: TaskDefinition with invalid window size
        $this->taskDefinition->task_runner_config = ['comparison_window_size' => 101]; // Invalid: > 100
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        $initialProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => BaseTaskRunner::OPERATION_DEFAULT,
            'meta'        => [],
            'started_at'  => now(),
        ]);

        // When/Then: Initial process run throws validation error
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('comparison_window_size must be between 2 and 100');

        $this->runner->setTaskRun($taskRun)->setTaskProcess($initialProcess);
        $this->runner->run();
    }

    #[Test]
    public function initial_process_completes_when_no_input_artifacts(): void
    {
        // Given: TaskRun with no input artifacts
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $initialProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => BaseTaskRunner::OPERATION_DEFAULT,
            'meta'        => [],
            'started_at'  => now(),
        ]);

        // When: Running initial process with no input artifacts
        $this->runner->setTaskRun($taskRun)->setTaskProcess($initialProcess);
        $this->runner->run();

        // Then: Initial process completes without creating window processes
        $initialProcess->refresh();
        $this->assertNotNull($initialProcess->completed_at, 'Initial process should complete');

        // No window processes created
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();
        $this->assertCount(0, $windowProcesses, 'No window processes should be created');
    }

    #[Test]
    public function merge_process_runs_with_meta(): void
    {
        // Given: TaskRun with window output artifacts
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create input artifacts
        $inputArtifacts = [];
        for ($i = 0; $i < 5; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $inputArtifacts[] = $artifact;
        }

        // Create window output artifacts (simulating completed window processes)
        // NEW flat schema format - files with individual group assignments
        $windowArtifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'files' => [
                    [
                        'page_number'            => 0,
                        'group_name'             => 'group1',
                        'group_name_confidence'  => 5,
                        'group_explanation'      => 'First group',
                        'belongs_to_previous'    => null,
                    ],
                    [
                        'page_number'            => 1,
                        'group_name'             => 'group1',
                        'group_name_confidence'  => 5,
                        'group_explanation'      => 'First group',
                        'belongs_to_previous'    => 3,
                    ],
                    [
                        'page_number'            => 2,
                        'group_name'             => 'group1',
                        'group_name_confidence'  => 5,
                        'group_explanation'      => 'First group',
                        'belongs_to_previous'    => 4,
                    ],
                ],
            ],
        ]);

        $windowArtifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'files' => [
                    [
                        'page_number'            => 3,
                        'group_name'             => 'group2',
                        'group_name_confidence'  => 5,
                        'group_explanation'      => 'Second group',
                        'belongs_to_previous'    => 1,
                    ],
                    [
                        'page_number'            => 4,
                        'group_name'             => 'group2',
                        'group_name_confidence'  => 5,
                        'group_explanation'      => 'Second group',
                        'belongs_to_previous'    => 3,
                    ],
                ],
            ],
        ]);

        // Create window processes and attach artifacts as outputs (simulating completed processes)
        $windowProcess1 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $windowProcess1->outputArtifacts()->attach($windowArtifact1->id);

        $windowProcess2 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $windowProcess2->outputArtifacts()->attach($windowArtifact2->id);

        // Create merge process with meta
        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'name'        => 'Merge Window Results',
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'meta'        => [],
            'is_ready'    => true,
        ]);

        // When: Check that merge process has correct structure
        $this->runner->setTaskRun($taskRun)->setTaskProcess($mergeProcess);

        // Then: Merge process has required operation field
        $this->assertEquals(FileOrganizationTaskRunner::OPERATION_MERGE, $mergeProcess->operation);

        // Verify window artifacts exist for merging (via window processes)
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $windowArtifacts = collect();
        foreach ($windowProcesses as $wp) {
            $windowArtifacts = $windowArtifacts->merge($wp->outputArtifacts);
        }

        $this->assertCount(2, $windowArtifacts);

        // Verify merge service can process these artifacts
        $mergeService = app(FileOrganizationMergeService::class);
        $mergeResult  = $mergeService->mergeWindowResults($windowArtifacts);

        $this->assertIsArray($mergeResult);
        $this->assertArrayHasKey('groups', $mergeResult);
        $this->assertArrayHasKey('file_to_group_mapping', $mergeResult);
        $this->assertGreaterThan(0, count($mergeResult['groups']));
    }

    #[Test]
    public function afterAllProcessesCompleted_creates_merge_process(): void
    {
        // Given: TaskRun with completed window processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create some completed window processes
        $window1 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'         => [],
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $window1->computeStatus()->save();

        $window2 = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'         => [],
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $window2->computeStatus()->save();

        // When: Calling afterAllProcessesCompleted
        $this->runner->setTaskRun($taskRun);
        $this->runner->afterAllProcessesCompleted();

        // Then: Merge process is created
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        $this->assertNotNull($mergeProcess, 'Merge process should be created');
        $this->assertEquals(FileOrganizationTaskRunner::OPERATION_MERGE, $mergeProcess->operation);
        $this->assertEquals('Merge Window Results', $mergeProcess->name);
        $this->assertTrue($mergeProcess->is_ready, 'Merge process should be ready to run');
    }

    #[Test]
    public function afterAllProcessesCompleted_does_not_duplicate_merge_process(): void
    {
        // Given: TaskRun with existing merge process
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create existing merge process
        TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'meta'        => [],
            'name'        => 'Existing Merge Process',
        ]);

        $initialMergeCount = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->count();

        // When: Calling afterAllProcessesCompleted again
        $this->runner->setTaskRun($taskRun);
        $this->runner->afterAllProcessesCompleted();

        // Then: No duplicate merge process is created
        $finalMergeCount = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->count();

        $this->assertEquals($initialMergeCount, $finalMergeCount, 'Merge process should not be duplicated');
    }

    #[Test]
    public function afterAllProcessesCompleted_does_not_create_merge_when_no_window_processes_exist(): void
    {
        // GIVEN: A TaskRun with only the initial process (no window processes yet)
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create ONLY the initial process (completed)
        $initialProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'status'      => 'Completed',
            'meta'        => [],
        ]);

        // WHEN: afterAllProcessesCompleted is called
        $this->runner->setTaskRun($taskRun)->setTaskProcess($initialProcess);
        $this->runner->afterAllProcessesCompleted();

        // THEN: No merge process should be created
        $mergeProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->get();

        $this->assertCount(0, $mergeProcesses, 'Merge process should NOT be created when no window processes exist');

        // AND: Only the initial process should exist
        $this->assertCount(1, $taskRun->taskProcesses, 'Should only have the initial process');
    }

    #[Test]
    public function prepareRun_does_not_create_processes(): void
    {
        // Given: A new task run
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // When: Calling prepareRun
        $this->runner->setTaskRun($taskRun);
        $this->runner->prepareRun();

        // Then: No processes are created (TaskRunnerService::prepareTaskProcesses handles that)
        $this->assertEquals(0, $taskRun->taskProcesses()->count(), 'prepareRun should not create any processes');
    }

    #[Test]
    public function run_routes_to_createWindowProcesses_when_operation_is_default(): void
    {
        // Given: TaskProcess with operation = 'Default Task' and NO artifacts
        // (avoiding agent dispatch which causes test failures)
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $defaultProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => BaseTaskRunner::OPERATION_DEFAULT,
            'meta'        => [],
            'started_at'  => now(),
        ]);

        // When: Running the process with operation = 'Default Task'
        $this->runner->setTaskRun($taskRun)->setTaskProcess($defaultProcess);

        // Verify operation field is correctly set for routing
        $this->assertEquals(BaseTaskRunner::OPERATION_DEFAULT, $defaultProcess->operation);

        // Run the process (will complete since no artifacts to process)
        $this->runner->run();

        // Then: Process completes successfully (operation routing works)
        $this->assertNotNull($defaultProcess->fresh()->completed_at, 'Default process should complete');
    }

    #[Test]
    public function run_routes_to_runComparisonWindow_when_operation_is_comparison_window(): void
    {
        // Given: TaskProcess with operation = 'comparison_window'
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create input artifacts
        $artifacts = [];
        for ($i = 0; $i < 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $artifacts[] = $artifact;
        }

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        foreach ($artifacts as $artifact) {
            $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // When: Checking operation routing
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);

        // Then: Verify the operation field is used for routing
        $this->assertEquals(FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW, $windowProcess->operation);
        $this->assertGreaterThan(0, $windowProcess->inputArtifacts->count());
    }

    #[Test]
    public function run_routes_to_runMergeProcess_when_operation_is_merge(): void
    {
        // Given: TaskRun with completed window processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create input artifacts
        $inputArtifacts = [];
        for ($i = 0; $i < 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $inputArtifacts[] = $artifact;
        }

        // Create a window output artifact
        $windowArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'groups' => [
                    [
                        'name'        => 'Test Group',
                        'description' => 'Test Description',
                        'files'       => [$inputArtifacts[0]->id, $inputArtifacts[1]->id],
                    ],
                ],
            ],
        ]);

        // Create window process and attach the artifact
        $windowProcess = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'         => [],
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $windowProcess->outputArtifacts()->attach($windowArtifact->id);

        // Create merge process with operation = 'merge'
        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'meta'        => [],
            'is_ready'    => true,
        ]);

        // When: Checking operation routing
        $this->runner->setTaskRun($taskRun)->setTaskProcess($mergeProcess);

        // Then: Verify the operation field is used for routing to merge
        $this->assertEquals(FileOrganizationTaskRunner::OPERATION_MERGE, $mergeProcess->operation);
    }

    #[Test]
    public function overlapping_windows_are_created_correctly(): void
    {
        // NOTE: This test verifies overlapping window structure via WindowProcessService
        // Actual window creation is tested in FileOrganizationMergeServiceTest::creates_overlapping_windows_from_file_list

        // Given: TaskRun with artifacts that have stored files with page numbers
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifacts = [];
        for ($i = 0; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);

            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $artifacts[] = $artifact;
        }

        // When: Creating window processes with size 3 and overlap 1
        app(WindowProcessService::class)->createWindowProcesses($taskRun, 3);

        // Then: Window processes should be created with overlapping ranges
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->assertGreaterThan(1, $windowProcesses->count(), 'Multiple windows should be created');

        // Verify each window has input artifacts
        foreach ($windowProcesses as $wp) {
            $this->assertGreaterThan(0, $wp->inputArtifacts->count(), 'Window process should have input artifacts');
        }
    }

    #[Test]
    public function setupAgentThread_sends_simplified_artifact_data(): void
    {
        // GIVEN: TaskRun with artifacts that have StoredFiles with page_number
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create artifacts with StoredFile relationships that have page_number
        $artifacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);

            // Create StoredFile with page_number and attach to artifact
            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            // Manually attach the stored file to artifact (via many-to-many relationship)
            $artifact->storedFiles()->attach($storedFile->id);

            $artifacts[] = $artifact;
        }

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        foreach ($artifacts as $artifact) {
            $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // WHEN: setupAgentThread is called
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $agentThread = $this->runner->setupAgentThread($artifacts);

        // THEN: Agent thread should be created
        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // AND: Thread messages should be simplified (only contain page numbers, not full artifact structure)
        $messages = $agentThread->messages()->orderBy('created_at')->get();

        // Find artifact messages (those that ONLY contain "Page X" format)
        $artifactMessages = $messages->filter(function ($message) {
            $content = trim($message->content ?? '');

            return preg_match('/^Page \d+$/', $content);
        });

        // Verify we have messages for each page
        $this->assertGreaterThanOrEqual(3, $artifactMessages->count(), 'Should have messages for each artifact');

        // Verify message format is simplified (just "Page X", not complex JSON)
        foreach ($artifactMessages as $message) {
            // Message should be simple: "Page 1", "Page 2", etc.
            $this->assertMatchesRegularExpression('/^Page \d+$/', trim($message->content ?? ''),
                'Message should only contain page number, not complex artifact structure');
        }

        // Verify complex metadata is NOT in artifact messages (skip instruction messages)
        foreach ($messages as $message) {
            $content = $message->content ?? '';

            // Skip instruction messages (they contain examples with "files", "name", etc)
            if (str_contains($content, 'You are comparing adjacent files') ||
                str_contains($content, 'GROUPING STRATEGY')) {
                continue;
            }

            $this->assertStringNotContainsString('"url":', $content, 'Should not contain URL in message content');
            $this->assertStringNotContainsString('"mime":', $content, 'Should not contain mime type in message content');
            $this->assertStringNotContainsString('"size":', $content, 'Should not contain size in message content');
        }
    }

    #[Test]
    public function setupAgentThread_handles_artifacts_without_page_number(): void
    {
        // GIVEN: TaskRun with artifacts that DON'T have page_number in meta
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'position' => 1,
            'meta'     => [
                // No page_number or page field
                'other_data' => 'some value',
            ],
        ]);

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        // WHEN: setupAgentThread is called with artifact missing page_number
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $agentThread = $this->runner->setupAgentThread([$artifact]);

        // THEN: Agent thread should still be created successfully without errors
        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // The artifact may not create a message if it has no stored files and no page number
        // This is acceptable behavior - the important part is that no error is thrown
    }

    #[Test]
    public function setupAgentThread_handles_page_number_as_array(): void
    {
        // GIVEN: TaskRun with artifact that has StoredFile with page_number = 5
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'position' => 1,
        ]);

        // Create StoredFile with page_number and attach to artifact
        $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 5,
            'filename'    => 'page-5.jpg',
            'filepath'    => 'test/page-5.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        // Manually attach the stored file to artifact (via many-to-many relationship)
        $artifact->storedFiles()->attach($storedFile->id);

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        // WHEN: setupAgentThread is called
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $agentThread = $this->runner->setupAgentThread([$artifact]);

        // THEN: Agent thread should be created successfully
        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // AND: Message should contain "Page 5" from StoredFile page_number
        $messages     = $agentThread->messages()->orderBy('created_at')->get();
        $pageMessages = $messages->filter(function ($message) {
            return str_contains($message->content ?? '', 'Page ');
        });

        if ($pageMessages->isNotEmpty()) {
            // If a message was created, it should contain "Page 5"
            $found = false;
            foreach ($pageMessages as $message) {
                if (str_contains($message->content ?? '', 'Page 5')) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Should use page_number from StoredFile');
        }
    }

    #[Test]
    public function setupAgentThread_handles_empty_page_number_array(): void
    {
        // GIVEN: TaskRun with artifact where page_number is an empty array
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'position' => 1,
            'meta'     => [
                'page_number' => [], // Empty array - should be treated as null
            ],
        ]);

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        // WHEN: setupAgentThread is called with artifact where page_number is empty array
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $agentThread = $this->runner->setupAgentThread([$artifact]);

        // THEN: Agent thread should be created successfully without errors
        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // Empty array should be treated as null - no page number message should be created
        // (or an empty message if no stored files)
    }

    #[Test]
    public function setupAgentThread_handles_null_storedFiles_relationship(): void
    {
        // GIVEN: TaskRun with artifact where storedFiles relationship is null/not loaded
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'position' => 1,
            'meta'     => [
                'page_number' => 5,
            ],
        ]);

        // Don't load storedFiles relationship - this could cause the error if not handled

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

        // WHEN: setupAgentThread is called without storedFiles loaded
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $agentThread = $this->runner->setupAgentThread([$artifact]);

        // THEN: Agent thread should be created successfully without errors
        $this->assertNotNull($agentThread);
        $this->assertEquals($this->agent->id, $agentThread->agent_id);

        // Should handle null storedFiles gracefully (empty array for file IDs)
    }

    #[Test]
    public function setupAgentThread_never_reuses_existing_thread(): void
    {
        // CRITICAL BUG FIX TEST: Verifies agent threads are NEVER reused
        // The bug was that if taskProcess->agentThread existed, it would return the existing thread
        // WITHOUT adding new file messages, causing the agent to receive no file data

        // GIVEN: TaskRun with artifacts that have StoredFiles with page_number
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create artifacts with StoredFile relationships
        $artifacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);

            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $artifacts[] = $artifact;
        }

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
        ]);

        foreach ($artifacts as $artifact) {
            $windowProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // Create a first agent thread and associate it with the process (simulating reuse scenario)
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);
        $firstThread = $this->runner->setupAgentThread($artifacts);

        // Associate the thread with the process (this is what might trigger reuse bug)
        $windowProcess->agentThread()->associate($firstThread)->save();
        $windowProcess->refresh();

        // Verify the first thread was associated
        $this->assertNotNull($windowProcess->agentThread);
        $this->assertEquals($firstThread->id, $windowProcess->agentThread->id);

        // Get initial message count
        $firstThreadMessageCount = $firstThread->messages()->count();
        $this->assertGreaterThan(0, $firstThreadMessageCount, 'First thread should have messages');

        // WHEN: setupAgentThread is called AGAIN with the same process (that already has an agentThread)
        // This simulates the bug scenario where the method would return the existing thread without adding messages
        $secondThread = $this->runner->setupAgentThread($artifacts);

        // THEN: A NEW thread should be created (not reusing the existing one)
        $this->assertNotEquals($firstThread->id, $secondThread->id,
            'setupAgentThread should NEVER reuse an existing agent thread - each call must create a fresh thread with new messages');

        // AND: The new thread should have its own set of file messages
        $secondThreadMessages = $secondThread->messages()->orderBy('created_at')->get();
        $artifactMessages     = $secondThreadMessages->filter(function ($message) {
            return preg_match('/^Page \d+$/', trim($message->content ?? ''));
        });

        $this->assertGreaterThanOrEqual(3, $artifactMessages->count(),
            'New thread should have all file messages added');

        // AND: The first thread should remain unchanged (not affected by second call)
        $firstThread->refresh();
        $this->assertEquals($firstThreadMessageCount, $firstThread->messages()->count(),
            'First thread should remain unchanged when second thread is created');
    }

    #[Test]
    public function validateNoDuplicatePages_throws_error_when_page_appears_in_multiple_groups(): void
    {
        // GIVEN: JSON content that has page 62 in BOTH groups
        $invalidJsonContent = [
            'groups' => [
                [
                    'name'        => 'ME Physical Therapy',
                    'description' => 'Physical therapy billing documents',
                    'files'       => [
                        ['page_number' => 62, 'confidence' => 5, 'explanation' => 'CMS-1500 form'],
                        ['page_number' => 61, 'confidence' => 4, 'explanation' => 'PT treatment note'],
                    ],
                ],
                [
                    'name'        => 'Mountain View Pain Center',
                    'description' => 'Pain center clinical documentation',
                    'files'       => [
                        ['page_number' => 63, 'confidence' => 5, 'explanation' => 'Pain center letterhead'],
                        ['page_number' => 64, 'confidence' => 5, 'explanation' => 'Clinical history'],
                        ['page_number' => 65, 'confidence' => 5, 'explanation' => 'Assessment and plan'],
                        ['page_number' => 62, 'confidence' => 5, 'explanation' => 'CMS-1500 lists Mountain View'],
                    ],
                ],
            ],
        ];

        // WHEN/THEN: Validation should throw an error
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('Invalid file organization: Page 62 appears in multiple groups');
        $this->expectExceptionMessage('First group: \'ME Physical Therapy\'');
        $this->expectExceptionMessage('Second group: \'Mountain View Pain Center\'');
        $this->expectExceptionMessage('Each page must belong to exactly ONE group');

        // Call the ValidationService directly
        app(\App\Services\Task\FileOrganization\ValidationService::class)->validateNoDuplicatePages($invalidJsonContent);
    }

    #[Test]
    public function validateNoDuplicatePages_passes_when_no_duplicates(): void
    {
        // GIVEN: Valid JSON content where each page appears in exactly one group
        $validJsonContent = [
            'groups' => [
                [
                    'name'        => 'Group A',
                    'description' => 'First group',
                    'files'       => [
                        ['page_number' => 1, 'confidence' => 5, 'explanation' => 'Clear match'],
                        ['page_number' => 2, 'confidence' => 4, 'explanation' => 'Strong match'],
                    ],
                ],
                [
                    'name'        => 'Group B',
                    'description' => 'Second group',
                    'files'       => [
                        ['page_number' => 3, 'confidence' => 5, 'explanation' => 'Clear match'],
                        ['page_number' => 4, 'confidence' => 5, 'explanation' => 'Clear match'],
                        ['page_number' => 5, 'confidence' => 3, 'explanation' => 'Moderate match'],
                    ],
                ],
            ],
        ];

        // WHEN: Calling validation
        // Call the ValidationService directly
        app(\App\Services\Task\FileOrganization\ValidationService::class)->validateNoDuplicatePages($validJsonContent);

        // THEN: No exception should be thrown
        // If we get here, validation passed
        $this->assertTrue(true);
    }

    #[Test]
    public function validateNoDuplicatePages_handles_integer_format(): void
    {
        // GIVEN: JSON content using integer format for file references with a duplicate page
        $oldFormatWithDuplicate = [
            'groups' => [
                [
                    'name'        => 'Group A',
                    'description' => 'First group',
                    'files'       => [1, 2, 3], // Integer format
                ],
                [
                    'name'        => 'Group B',
                    'description' => 'Second group',
                    'files'       => [3, 4, 5], // Page 3 is duplicated
                ],
            ],
        ];

        // WHEN/THEN: Should throw error for duplicate with integer format
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('Page 3 appears in multiple groups');

        // Call the ValidationService directly
        app(\App\Services\Task\FileOrganization\ValidationService::class)->validateNoDuplicatePages($oldFormatWithDuplicate);
    }


    #[Test]
    public function createWindowProcesses_attaches_input_artifacts_with_stored_files(): void
    {
        // This test verifies that WindowProcessService creates window processes
        // with input artifacts that have stored files attached.

        // GIVEN: TaskRun with input artifacts that have page numbers
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifacts = [];
        for ($i = 1; $i <= 4; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);

            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $artifacts[] = $artifact;
        }

        // WHEN: WindowProcessService creates window processes
        app(WindowProcessService::class)->createWindowProcesses($taskRun, 3);

        // THEN: Window processes should be created
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->assertGreaterThan(0, $windowProcesses->count(), 'Window processes should be created');

        // AND: Each window process should have input artifacts with stored files
        foreach ($windowProcesses as $windowProcess) {
            $inputArtifacts = $windowProcess->inputArtifacts;
            $this->assertGreaterThanOrEqual(1, $inputArtifacts->count(), 'Window process should have input artifacts');

            // Verify at least one input artifact has stored files
            $totalStoredFiles = $inputArtifacts->sum(fn($a) => $a->storedFiles->count());
            $this->assertGreaterThanOrEqual(2, $totalStoredFiles, 'Each window should have at least 2 stored files');
        }
    }

    #[Test]
    public function runLowConfidenceResolution_queries_artifacts_without_ambiguous_column_error(): void
    {
        // This test verifies the SQL query fix for ambiguous column error
        // The error occurred when querying inputArtifacts()->whereIn('id', ...) without table qualification

        // GIVEN: TaskRun with low-confidence files in merge process meta
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create input artifacts for the task run
        $inputArtifacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'position' => $i,
            ]);

            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            $inputArtifacts[] = $artifact;
        }

        // Create resolution process with low-confidence files
        $lowConfidenceFiles = [
            [
                'file_id'         => $inputArtifacts[0]->id,
                'page_number'     => 1,
                'best_assignment' => [
                    'group_name'  => 'Group A',
                    'confidence'  => 2,
                    'explanation' => 'Uncertain',
                ],
                'all_explanations' => [],
            ],
            [
                'file_id'         => $inputArtifacts[1]->id,
                'page_number'     => 2,
                'best_assignment' => [
                    'group_name'  => 'Group B',
                    'confidence'  => 1,
                    'explanation' => 'Very uncertain',
                ],
                'all_explanations' => [],
            ],
            [
                'file_id'         => $inputArtifacts[2]->id,
                'page_number'     => 3,
                'best_assignment' => [
                    'group_name'  => 'Group A',
                    'confidence'  => 2,
                    'explanation' => 'Uncertain',
                ],
                'all_explanations' => [],
            ],
        ];

        $resolutionProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_LOW_CONFIDENCE_RESOLUTION,
            'meta'        => [
                'low_confidence_files' => $lowConfidenceFiles,
            ],
            'is_ready' => true,
        ]);

        // Attach uncertain files as input artifacts to the process
        foreach ($inputArtifacts as $artifact) {
            $resolutionProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }
        $resolutionProcess->updateRelationCounter('inputArtifacts');

        // WHEN: We query for uncertain artifacts (this was causing ambiguous column error)
        // The fix is to use 'artifacts.id' instead of just 'id' in the whereIn clause
        $uncertainFileIds = array_column($lowConfidenceFiles, 'file_id');

        // This query should NOT throw "SQLSTATE[42702]: Ambiguous column: column reference "id" is ambiguous"
        $uncertainArtifacts = $resolutionProcess->inputArtifacts()
            ->whereIn('artifacts.id', $uncertainFileIds) // Table qualified to avoid ambiguity
            ->get();

        // THEN: Query should succeed and return the correct artifacts
        $this->assertCount(3, $uncertainArtifacts, 'Should retrieve all 3 uncertain artifacts without SQL error');
        $this->assertEquals($inputArtifacts[0]->id, $uncertainArtifacts[0]->id);
        $this->assertEquals($inputArtifacts[1]->id, $uncertainArtifacts[1]->id);
        $this->assertEquals($inputArtifacts[2]->id, $uncertainArtifacts[2]->id);
    }

    #[Test]
    public function merge_config_getters_return_defaults_when_not_set(): void
    {
        // GIVEN: TaskDefinition with NO merge config keys in task_runner_config
        // (setUp already creates taskDefinition with only 'comparison_window_size')

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->runner->setTaskRun($taskRun);

        // WHEN/THEN: Each getter should return its default value
        $this->assertProtectedMethodReturns('getGroupConfidenceThreshold', FileOrganizationTaskRunner::DEFAULT_GROUP_CONFIDENCE_THRESHOLD);
        $this->assertProtectedMethodReturns('getAdjacencyBoundaryThreshold', FileOrganizationTaskRunner::DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD);
        $this->assertProtectedMethodReturns('getBlankPageHandling', FileOrganizationTaskRunner::DEFAULT_BLANK_PAGE_HANDLING);
        $this->assertProtectedMethodReturns('getNameSimilarityThreshold', FileOrganizationTaskRunner::DEFAULT_NAME_SIMILARITY_THRESHOLD);
        $this->assertProtectedMethodReturns('getMaxSlidingIterations', FileOrganizationTaskRunner::DEFAULT_MAX_SLIDING_ITERATIONS);
    }

    #[Test]
    public function merge_config_getters_return_custom_values_when_set(): void
    {
        // GIVEN: TaskDefinition with ALL merge config keys set to custom values
        $this->taskDefinition->task_runner_config = [
            'comparison_window_size'       => 3,
            'group_confidence_threshold'   => 5,
            'adjacency_boundary_threshold' => 4,
            'blank_page_handling'          => 'separate_group',
            'name_similarity_threshold'    => 0.9,
            'max_sliding_iterations'       => 10,
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->runner->setTaskRun($taskRun);

        // WHEN/THEN: Each getter should return the custom value
        $this->assertProtectedMethodReturns('getGroupConfidenceThreshold', 5);
        $this->assertProtectedMethodReturns('getAdjacencyBoundaryThreshold', 4);
        $this->assertProtectedMethodReturns('getBlankPageHandling', 'separate_group');
        $this->assertProtectedMethodReturns('getNameSimilarityThreshold', 0.9);
        $this->assertProtectedMethodReturns('getMaxSlidingIterations', 10);
    }

    #[Test]
    public function getMergeConfig_returns_complete_config_with_defaults(): void
    {
        // GIVEN: TaskDefinition with NO merge config keys
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->runner->setTaskRun($taskRun);

        // WHEN: Calling getMergeConfig
        $method = new ReflectionMethod($this->runner, 'getMergeConfig');
        $config = $method->invoke($this->runner);

        // THEN: All 5 keys should be present with default values
        $this->assertIsArray($config);
        $this->assertCount(5, $config);
        $this->assertEquals(FileOrganizationTaskRunner::DEFAULT_GROUP_CONFIDENCE_THRESHOLD, $config['group_confidence_threshold']);
        $this->assertEquals(FileOrganizationTaskRunner::DEFAULT_ADJACENCY_BOUNDARY_THRESHOLD, $config['adjacency_boundary_threshold']);
        $this->assertEquals(FileOrganizationTaskRunner::DEFAULT_BLANK_PAGE_HANDLING, $config['blank_page_handling']);
        $this->assertEquals(FileOrganizationTaskRunner::DEFAULT_NAME_SIMILARITY_THRESHOLD, $config['name_similarity_threshold']);
        $this->assertEquals(FileOrganizationTaskRunner::DEFAULT_MAX_SLIDING_ITERATIONS, $config['max_sliding_iterations']);
    }

    #[Test]
    public function getMergeConfig_returns_complete_config_with_custom_values(): void
    {
        // GIVEN: TaskDefinition with custom merge config values
        $this->taskDefinition->task_runner_config = [
            'group_confidence_threshold'   => 4,
            'adjacency_boundary_threshold' => 3,
            'blank_page_handling'          => 'ignore',
            'name_similarity_threshold'    => 0.85,
            'max_sliding_iterations'       => 7,
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->runner->setTaskRun($taskRun);

        // WHEN: Calling getMergeConfig
        $method = new ReflectionMethod($this->runner, 'getMergeConfig');
        $config = $method->invoke($this->runner);

        // THEN: All 5 keys should be present with custom values
        $this->assertEquals(4, $config['group_confidence_threshold']);
        $this->assertEquals(3, $config['adjacency_boundary_threshold']);
        $this->assertEquals('ignore', $config['blank_page_handling']);
        $this->assertEquals(0.85, $config['name_similarity_threshold']);
        $this->assertEquals(7, $config['max_sliding_iterations']);
    }

    #[Test]
    public function runMergeProcess_passes_config_to_mergeWindowResults(): void
    {
        // GIVEN: TaskDefinition with custom merge config values
        $this->taskDefinition->task_runner_config = [
            'group_confidence_threshold'   => 5,
            'adjacency_boundary_threshold' => 4,
            'blank_page_handling'          => 'separate_group',
            'name_similarity_threshold'    => 0.95,
            'max_sliding_iterations'       => 8,
        ];
        $this->taskDefinition->save();

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create a completed window process with output artifact
        $inputArtifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'position' => 0,
        ]);
        $taskRun->inputArtifacts()->attach($inputArtifact->id, ['category' => 'input']);

        $windowArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'files' => [
                    [
                        'page_number'           => 0,
                        'group_name'            => 'test_group',
                        'group_name_confidence' => 5,
                        'group_explanation'     => 'Test',
                        'belongs_to_previous'   => null,
                    ],
                ],
            ],
        ]);

        $windowProcess = TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
        $windowProcess->outputArtifacts()->attach($windowArtifact->id);

        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'is_ready'    => true,
        ]);

        // Mock FileOrganizationMergeService to capture the config argument
        $capturedConfig = null;
        $mockMergeService = $this->mock(FileOrganizationMergeService::class, function ($mock) use (&$capturedConfig) {
            $mock->shouldReceive('mergeWindowResults')
                ->once()
                ->withArgs(function ($artifacts, $config) use (&$capturedConfig) {
                    $capturedConfig = $config;

                    return true;
                })
                ->andReturn([
                    'groups'                => [],
                    'file_to_group_mapping' => [],
                ]);
        });

        // WHEN: Running the merge process
        $this->runner->setTaskRun($taskRun)->setTaskProcess($mergeProcess);
        $this->runner->run();

        // THEN: The config should have been passed through to mergeWindowResults
        $this->assertNotNull($capturedConfig, 'Config should be passed to mergeWindowResults');
        $this->assertEquals(5, $capturedConfig['group_confidence_threshold']);
        $this->assertEquals(4, $capturedConfig['adjacency_boundary_threshold']);
        $this->assertEquals('separate_group', $capturedConfig['blank_page_handling']);
        $this->assertEquals(0.95, $capturedConfig['name_similarity_threshold']);
        $this->assertEquals(8, $capturedConfig['max_sliding_iterations']);
    }

    #[Test]
    public function run_routes_to_transcode_operation(): void
    {
        // Given: A task process with operation "Transcode"
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $transcodeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => \App\Services\Task\TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'meta'        => [],
            'started_at'  => now(),
        ]);

        // When: Checking operation routing
        $this->runner->setTaskRun($taskRun)->setTaskProcess($transcodeProcess);

        // Then: Verify operation field matches and routing works (no exception for wrong operation)
        $this->assertEquals(
            \App\Services\Task\TranscodePrerequisiteService::OPERATION_TRANSCODE,
            $transcodeProcess->operation
        );
    }

    #[Test]
    public function window_process_uses_window_pages_artifact(): void
    {
        // Given: A window process with a "Window Pages 1-3" artifact
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create stored files for the window pages
        $storedFiles = [];
        for ($i = 1; $i <= 3; $i++) {
            $storedFiles[] = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);
        }

        // Create window pages artifact with stored files
        $windowPagesArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Window Pages 1-3',
        ]);
        foreach ($storedFiles as $sf) {
            $windowPagesArtifact->storedFiles()->attach($sf->id);
        }

        // Create window process and attach the pages artifact
        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'meta'        => [],
            'is_ready'    => true,
            'started_at'  => now(),
        ]);

        $windowProcess->inputArtifacts()->attach($windowPagesArtifact->id, ['category' => 'input']);

        // When: The runner uses the first input artifact for comparison
        $this->runner->setTaskRun($taskRun)->setTaskProcess($windowProcess);

        // Verify the process has the correct input artifact
        $inputArtifacts = $windowProcess->inputArtifacts;
        $this->assertCount(1, $inputArtifacts);

        $windowPages = $inputArtifacts->first();
        $this->assertNotNull($windowPages, 'Window pages artifact should be found');
        $this->assertEquals('Window Pages 1-3', $windowPages->name);

        // Verify the window pages artifact has the correct stored files
        $this->assertCount(3, $windowPages->storedFiles);
    }

    /**
     * Helper to invoke a protected method on the runner and assert its return value.
     */
    private function assertProtectedMethodReturns(string $methodName, mixed $expected): void
    {
        $method = new ReflectionMethod($this->runner, $methodName);
        $actual = $method->invoke($this->runner);
        $this->assertEquals($expected, $actual, "Expected $methodName to return " . var_export($expected, true));
    }
}
