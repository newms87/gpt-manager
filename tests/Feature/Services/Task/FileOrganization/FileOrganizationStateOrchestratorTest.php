<?php

namespace Tests\Feature\Services\Task\FileOrganization;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\FileOrganizationStateOrchestrator;
use App\Services\Task\FileOrganization\PageResolutionService;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use App\Services\Task\TranscodePrerequisiteService;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class FileOrganizationStateOrchestratorTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TaskDefinition $taskDefinition;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => self::TEST_MODEL,
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'State Orchestrator Test',
            'task_runner_name'   => FileOrganizationTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'comparison_window_size'    => 3,
                'comparison_window_overlap' => 1,
            ],
            'agent_id' => $agent->id,
        ]);

        // Mock TranscodePrerequisiteService to prevent API calls during tests
        $this->mock(TranscodePrerequisiteService::class, function ($mock) {
            $mock->shouldReceive('getArtifactsNeedingTranscode')
                ->andReturn(collect());
        });
    }

    #[Test]
    public function resolves_pages_on_first_advance(): void
    {
        // Given: A task run with image input artifacts but no "Resolved Pages" artifact
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Input Document',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'     => StoredFile::MIME_PNG,
                'filename' => "page-$i.png",
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
        }

        $taskRun->inputArtifacts()->attach($artifact->id);

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: Pages are resolved and "Resolved Pages" artifact exists
        $taskRun->refresh();
        $resolvedArtifact = $taskRun->inputArtifacts()
            ->where('name', 'Resolved Pages')
            ->first();

        $this->assertNotNull($resolvedArtifact, 'Resolved Pages artifact should exist after page resolution');
        $this->assertCount(3, $resolvedArtifact->storedFiles);
    }

    #[Test]
    public function creates_window_processes_after_page_resolution(): void
    {
        // Given: A task run with a "Resolved Pages" artifact containing stored files
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $resolvedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Resolved Pages',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "page-$i.png",
                'page_number' => $i,
            ]);
            $resolvedArtifact->storedFiles()->attach($storedFile->id);
        }

        $taskRun->inputArtifacts()->attach($resolvedArtifact->id);

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: Window processes are created (Comparison Window operations exist)
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->assertGreaterThan(0, $windowProcesses->count(), 'Window processes should be created');
    }

    #[Test]
    public function creates_merge_process_when_all_windows_complete(): void
    {
        // Given: A task run with all window processes completed
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create a "Resolved Pages" artifact so page resolution is skipped
        $resolvedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Resolved Pages',
        ]);
        $taskRun->inputArtifacts()->attach($resolvedArtifact->id);

        // Create completed window processes
        for ($i = 0; $i < 3; $i++) {
            TaskProcess::factory()->create([
                'task_run_id'  => $taskRun->id,
                'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
                'started_at'   => now()->subMinutes(5),
                'completed_at' => now(),
            ]);
        }

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: A "Merge" process is created
        $mergeProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
            ->first();

        $this->assertNotNull($mergeProcess, 'Merge process should be created when all windows complete');
        $this->assertEquals('Merge Window Results', $mergeProcess->name);
        $this->assertTrue($mergeProcess->is_ready);
    }

    #[Test]
    public function skips_page_resolution_when_already_done(): void
    {
        // Given: A task run that already has a "Resolved Pages" input artifact
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $resolvedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Resolved Pages',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "page-$i.png",
                'page_number' => $i,
            ]);
            $resolvedArtifact->storedFiles()->attach($storedFile->id);
        }

        $taskRun->inputArtifacts()->attach($resolvedArtifact->id);

        // Mock PageResolutionService to verify it is NOT called
        $this->mock(PageResolutionService::class, function ($mock) {
            $mock->shouldNotReceive('resolvePages');
        });

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: PageResolutionService is NOT called again (verified by mock)
        $this->assertTrue(true, 'PageResolutionService was not called when pages already resolved');
    }

    #[Test]
    public function skips_window_creation_when_windows_exist(): void
    {
        // Given: A task run with existing window processes (even if not complete)
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create a "Resolved Pages" artifact so page resolution is skipped
        $resolvedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Resolved Pages',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "page-$i.png",
                'page_number' => $i,
            ]);
            $resolvedArtifact->storedFiles()->attach($storedFile->id);
        }

        $taskRun->inputArtifacts()->attach($resolvedArtifact->id);

        // Create an existing (incomplete) window process
        TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'started_at'  => now(),
        ]);

        $initialWindowCount = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->count();

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: No additional window processes are created
        $finalWindowCount = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->count();

        $this->assertEquals($initialWindowCount, $finalWindowCount, 'No additional window processes should be created');
    }

    #[Test]
    public function creates_resolution_processes_after_merge_completes(): void
    {
        // Given: A task run with a completed merge process that has groups_for_deduplication metadata
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create a "Resolved Pages" artifact so page resolution is skipped
        $resolvedArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Resolved Pages',
        ]);
        $taskRun->inputArtifacts()->attach($resolvedArtifact->id);

        // Create completed window processes so window phase is skipped
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        // Create a completed merge process with groups_for_deduplication
        TaskProcess::factory()->create([
            'task_run_id'  => $taskRun->id,
            'operation'    => FileOrganizationTaskRunner::OPERATION_MERGE,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
            'meta'         => [
                'groups_for_deduplication' => [
                    [
                        'name'         => 'Group A',
                        'description'  => 'Test Group A',
                        'file_count'   => 3,
                        'sample_files' => [
                            ['page_number' => 1, 'confidence' => 5, 'description' => 'Sample 1'],
                        ],
                    ],
                    [
                        'name'         => 'Group B',
                        'description'  => 'Test Group B',
                        'file_count'   => 2,
                        'sample_files' => [
                            ['page_number' => 4, 'confidence' => 4, 'description' => 'Sample 4'],
                        ],
                    ],
                ],
            ],
        ]);

        // When: advanceToNextPhase
        app(FileOrganizationStateOrchestrator::class)->advanceToNextPhase($taskRun);

        // Then: Creates duplicate group resolution process
        $duplicateProcess = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_DUPLICATE_GROUP_RESOLUTION)
            ->first();

        $this->assertNotNull($duplicateProcess, 'Duplicate group resolution process should be created');
        $this->assertTrue($duplicateProcess->is_ready);
        $this->assertNotEmpty($duplicateProcess->meta['groups_for_deduplication']);
    }
}
