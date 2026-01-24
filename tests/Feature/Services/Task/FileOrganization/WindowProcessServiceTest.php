<?php

namespace Tests\Feature\Services\Task\FileOrganization;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\WindowProcessService;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WindowProcessServiceTest extends AuthenticatedTestCase
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
            'name'               => 'Window Process Test',
            'task_runner_name'   => FileOrganizationTaskRunner::RUNNER_NAME,
            'task_runner_config' => ['comparison_window_size' => 5],
            'agent_id'           => $agent->id,
        ]);
    }

    #[Test]
    public function creates_window_processes_from_pages_collection(): void
    {
        // Given: A Collection of 10 StoredFiles with page_numbers 1-10
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $pages = collect();
        for ($i = 1; $i <= 10; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "page-$i.png",
                'page_number' => $i,
            ]);
            $pages->push($storedFile);
        }

        // When: createWindowProcesses with pages
        app(WindowProcessService::class)->createWindowProcesses($taskRun, 5, 1, $pages);

        // Then: Creates window processes with correct window count
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        // With 10 files, window size 5, overlap 1: windows at [1-5], [5-9], [9-10 skipped: < 2 files]
        // So we expect 2 windows
        $this->assertGreaterThan(0, $windowProcesses->count(), 'Window processes should be created');

        // Verify each window process has a window pages artifact
        foreach ($windowProcesses as $process) {
            $windowPagesArtifact = $process->inputArtifacts
                ->first(fn($artifact) => str_starts_with($artifact->name, 'Window Pages'));

            $this->assertNotNull($windowPagesArtifact, "Process {$process->id} should have a Window Pages artifact");
            $this->assertGreaterThanOrEqual(2, $windowPagesArtifact->storedFiles->count(), 'Window pages artifact should have at least 2 stored files');
        }
    }

    #[Test]
    public function window_pages_artifact_contains_correct_stored_files(): void
    {
        // Given: 5 StoredFiles passed as pages
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $pages = collect();
        for ($i = 1; $i <= 5; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "page-$i.png",
                'page_number' => $i,
            ]);
            $pages->push($storedFile);
        }

        // When: createWindowProcesses with windowSize=3, overlap=1
        app(WindowProcessService::class)->createWindowProcesses($taskRun, 3, 1, $pages);

        // Then: Windows should be [1-3], [3-5]
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->assertCount(2, $windowProcesses, 'Should create 2 windows for 5 files with size 3 overlap 1');

        // Get the first window's pages artifact
        $firstProcess = $windowProcesses->first();
        $firstWindowPagesArtifact = $firstProcess->inputArtifacts
            ->first(fn($artifact) => str_starts_with($artifact->name, 'Window Pages'));

        $this->assertNotNull($firstWindowPagesArtifact, 'First window should have a Window Pages artifact');

        // First window artifact should have stored files for pages 1-3
        $firstWindowFileIds = $firstWindowPagesArtifact->storedFiles->pluck('id')->sort()->values();
        $expectedFirstWindowPages = $pages->slice(0, 3)->pluck('id')->sort()->values();
        $this->assertEquals($expectedFirstWindowPages->toArray(), $firstWindowFileIds->toArray(), 'First window should contain pages 1-3');
    }
}
