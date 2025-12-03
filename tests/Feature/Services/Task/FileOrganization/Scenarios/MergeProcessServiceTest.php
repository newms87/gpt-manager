<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\MergeProcessService;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests the MergeProcessService artifact lookup by page number.
 *
 * This test verifies that the merge service correctly looks up artifacts
 * by their stored files' page_number field, not by artifact ID.
 *
 * This test prevents regression of the bug where the service was using
 * whereIn('artifacts.id', $fileIds) when $fileIds contained page numbers.
 */
class MergeProcessServiceTest extends AuthenticatedTestCase
{
    use FileOrganizationTestHelpers;
    use SetUpTeamTrait;

    private MergeProcessService $mergeService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->setUpFileOrganization();
        $this->mergeService = app(MergeProcessService::class);
    }

    #[Test]
    public function creates_output_artifacts_by_looking_up_page_numbers_not_artifact_ids(): void
    {
        // GIVEN: A task run with input artifacts that have stored files with specific page numbers
        // The key is that artifact IDs will NOT match page numbers - they'll be different
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);

        // Create some dummy artifacts first to offset the IDs so they don't match page numbers
        // This simulates a real scenario where artifact IDs are much higher than page numbers
        for ($i = 1; $i <= 10; $i++) {
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
            ]);
        }

        // Create input artifacts with stored files having page_number set
        // Now artifact IDs will be 11-14, while page numbers are 1-4
        $inputArtifacts = [];
        $storedFiles    = [];

        // Create 4 pages (page_number 1, 2, 3, 4) with artifact IDs that will be much higher
        for ($pageNum = 1; $pageNum <= 4; $pageNum++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'name'     => "Input Page $pageNum",
                'position' => $pageNum,
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $pageNum, // This is the page number we'll reference
                'filename'    => "page-$pageNum.jpg",
                'filepath'    => "test/page-$pageNum.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $inputArtifacts[]                = $artifact;
            $storedFiles[$pageNum]           = $storedFile;
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // Verify that artifact IDs do NOT match page numbers
        // This is critical - the old bug would fail in this scenario
        foreach ($inputArtifacts as $artifact) {
            $pageNumber = $artifact->storedFiles->first()->page_number;
            $this->assertNotEquals(
                $artifact->id,
                $pageNumber,
                'Artifact ID should NOT match page number for this test to be valid. ' .
                "Got artifact ID {$artifact->id} and page number {$pageNumber}"
            );
        }

        // Create window task processes with artifacts that use the NEW flat format
        // The merge groups will contain page numbers [1, 2] and [3, 4]
        $windowProcess1 = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'status'      => TaskProcess::STATUS_COMPLETED,
        ]);

        // Create window artifact with flat format containing groups by page number
        $windowArtifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Window 1-4',
            'json_content' => [
                'files' => [
                    [
                        'page_number'                => 1,
                        'belongs_to_previous'        => null,
                        'belongs_to_previous_reason' => 'First page',
                        'group_name'                 => 'Group A',
                        'group_name_confidence'      => 5,
                        'group_explanation'          => 'Clear Group A header',
                    ],
                    [
                        'page_number'                => 2,
                        'belongs_to_previous'        => 5,
                        'belongs_to_previous_reason' => 'Same letterhead',
                        'group_name'                 => 'Group A',
                        'group_name_confidence'      => 5,
                        'group_explanation'          => 'Group A continuation',
                    ],
                    [
                        'page_number'                => 3,
                        'belongs_to_previous'        => 0,
                        'belongs_to_previous_reason' => 'Different document',
                        'group_name'                 => 'Group B',
                        'group_name_confidence'      => 5,
                        'group_explanation'          => 'Clear Group B header',
                    ],
                    [
                        'page_number'                => 4,
                        'belongs_to_previous'        => 5,
                        'belongs_to_previous_reason' => 'Same letterhead',
                        'group_name'                 => 'Group B',
                        'group_name_confidence'      => 5,
                        'group_explanation'          => 'Group B continuation',
                    ],
                ],
            ],
        ]);

        $windowProcess1->outputArtifacts()->attach($windowArtifact1->id, ['category' => 'output']);

        // Create a merge process that will consume the window artifacts
        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'status'      => TaskProcess::STATUS_PENDING,
        ]);

        // WHEN: The merge process runs
        $result = $this->mergeService->runMergeProcess($taskRun, $mergeProcess);

        // THEN: Output artifacts should be created correctly based on page numbers
        $this->assertNotEmpty($result['artifacts'], 'Should create output artifacts');
        $this->assertCount(2, $result['artifacts'], 'Should create 2 groups');

        // Verify Group A artifact
        $groupAArtifact = collect($result['artifacts'])->first(function ($artifact) {
            return ($artifact->meta['group_name'] ?? null) === 'Group A';
        });

        $this->assertNotNull($groupAArtifact, 'Group A artifact should exist');
        $this->assertEquals(2, $groupAArtifact->meta['file_count'], 'Group A should have 2 files');

        // Verify Group A contains the correct stored files (by page_number 1 and 2)
        $groupAStoredFiles = $groupAArtifact->storedFiles()->orderByPivot('id')->get();
        $this->assertCount(2, $groupAStoredFiles, 'Group A should have 2 stored files attached');

        $groupAPageNumbers = $groupAStoredFiles->pluck('page_number')->sort()->values()->toArray();
        $this->assertEquals([1, 2], $groupAPageNumbers, 'Group A should contain pages 1 and 2');

        // Verify Group B artifact
        $groupBArtifact = collect($result['artifacts'])->first(function ($artifact) {
            return ($artifact->meta['group_name'] ?? null) === 'Group B';
        });

        $this->assertNotNull($groupBArtifact, 'Group B artifact should exist');
        $this->assertEquals(2, $groupBArtifact->meta['file_count'], 'Group B should have 2 files');

        // Verify Group B contains the correct stored files (by page_number 3 and 4)
        $groupBStoredFiles = $groupBArtifact->storedFiles()->orderByPivot('id')->get();
        $this->assertCount(2, $groupBStoredFiles, 'Group B should have 2 stored files attached');

        $groupBPageNumbers = $groupBStoredFiles->pluck('page_number')->sort()->values()->toArray();
        $this->assertEquals([3, 4], $groupBPageNumbers, 'Group B should contain pages 3 and 4');

        // CRITICAL ASSERTION: This test would FAIL with the old bug
        // The old code used whereIn('artifacts.id', [1, 2, 3, 4]) which would look for
        // artifact IDs 1-4, but our artifacts have much higher IDs (11-14 from factory auto-increment)
        // The new code correctly uses whereIn('stored_files.page_number', [1, 2, 3, 4])
        // which properly finds the artifacts by their stored files' page_number field
    }

    #[Test]
    public function handles_merge_with_single_group(): void
    {
        // GIVEN: Task run with window artifacts containing a single group
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);

        // Create input artifacts with stored files
        for ($pageNum = 1; $pageNum <= 3; $pageNum++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'name'     => "Page $pageNum",
                'position' => $pageNum,
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $pageNum,
                'filename'    => "page-$pageNum.jpg",
                'filepath'    => "test/page-$pageNum.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // Create window process with single group
        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'status'      => TaskProcess::STATUS_COMPLETED,
        ]);

        $windowArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Window 1-3',
            'json_content' => [
                'files' => [
                    [
                        'page_number'           => 1,
                        'belongs_to_previous'   => null,
                        'group_name'            => 'Single Group',
                        'group_name_confidence' => 5,
                    ],
                    [
                        'page_number'           => 2,
                        'belongs_to_previous'   => 5,
                        'group_name'            => 'Single Group',
                        'group_name_confidence' => 5,
                    ],
                    [
                        'page_number'           => 3,
                        'belongs_to_previous'   => 5,
                        'group_name'            => 'Single Group',
                        'group_name_confidence' => 5,
                    ],
                ],
            ],
        ]);

        $windowProcess->outputArtifacts()->attach($windowArtifact->id, ['category' => 'output']);

        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'status'      => TaskProcess::STATUS_PENDING,
        ]);

        // WHEN
        $result = $this->mergeService->runMergeProcess($taskRun, $mergeProcess);

        // THEN
        $this->assertCount(1, $result['artifacts'], 'Should create 1 group');

        $artifact = $result['artifacts'][0];
        $this->assertEquals('Single Group', $artifact->meta['group_name']);
        $this->assertEquals(3, $artifact->meta['file_count']);

        $pageNumbers = $artifact->storedFiles->pluck('page_number')->sort()->values()->toArray();
        $this->assertEquals([1, 2, 3], $pageNumbers);
    }

    #[Test]
    public function returns_empty_result_when_no_window_artifacts_exist(): void
    {
        // GIVEN: Task run with no window processes
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);

        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'status'      => TaskProcess::STATUS_PENDING,
        ]);

        // WHEN
        $result = $this->mergeService->runMergeProcess($taskRun, $mergeProcess);

        // THEN
        $this->assertEmpty($result['artifacts'], 'Should return empty artifacts array');
        $this->assertIsArray($result['metadata'], 'Should return metadata array');
    }

    #[Test]
    public function skips_groups_with_no_matching_artifacts(): void
    {
        // GIVEN: Task run with window artifacts referencing non-existent page numbers
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);

        // Create input artifacts for pages 1-2 ONLY (not 3-4)
        for ($pageNum = 1; $pageNum <= 2; $pageNum++) {
            $artifact = Artifact::factory()->create([
                'team_id'  => $this->user->currentTeam->id,
                'name'     => "Page $pageNum",
                'position' => $pageNum,
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $pageNum,
                'filename'    => "page-$pageNum.jpg",
                'filepath'    => "test/page-$pageNum.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        // Create window artifact that references pages 1-2 (exists) and 3-4 (doesn't exist)
        $windowProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW,
            'status'      => TaskProcess::STATUS_COMPLETED,
        ]);

        $windowArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Window 1-4',
            'json_content' => [
                'files' => [
                    [
                        'page_number'           => 1,
                        'belongs_to_previous'   => null,
                        'group_name'            => 'Existing Group',
                        'group_name_confidence' => 5,
                    ],
                    [
                        'page_number'           => 2,
                        'belongs_to_previous'   => 5,
                        'group_name'            => 'Existing Group',
                        'group_name_confidence' => 5,
                    ],
                    [
                        'page_number'           => 3,
                        'belongs_to_previous'   => 0,
                        'group_name'            => 'Non-existent Group',
                        'group_name_confidence' => 5,
                    ],
                    [
                        'page_number'           => 4,
                        'belongs_to_previous'   => 5,
                        'group_name'            => 'Non-existent Group',
                        'group_name_confidence' => 5,
                    ],
                ],
            ],
        ]);

        $windowProcess->outputArtifacts()->attach($windowArtifact->id, ['category' => 'output']);

        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'status'      => TaskProcess::STATUS_PENDING,
        ]);

        // WHEN
        $result = $this->mergeService->runMergeProcess($taskRun, $mergeProcess);

        // THEN: Only the existing group should be created, non-existent group should be skipped
        $this->assertCount(1, $result['artifacts'], 'Should only create group for existing pages');

        $artifact = $result['artifacts'][0];
        $this->assertEquals('Existing Group', $artifact->meta['group_name']);
        $this->assertEquals(2, $artifact->meta['file_count']);

        $pageNumbers = $artifact->storedFiles->pluck('page_number')->sort()->values()->toArray();
        $this->assertEquals([1, 2], $pageNumbers, 'Should only include pages that exist');
    }

    #[Test]
    public function merges_duplicate_groups_without_updating_nonexistent_relation_counter(): void
    {
        // BUG REPRODUCTION TEST for line 423 in MergeProcessService
        //
        // Bug: The mergeGroups() method calls updateRelationCounter('storedFiles')
        // but the Artifact model's $relationCounters array only defines 'children',
        // NOT 'storedFiles'. There is no stored_files_count column in the artifacts table.
        //
        // Error: Undefined array key "Newms87\Danx\Models\Utilities\StoredFile"
        //
        // This test reproduces the issue by:
        // 1. Creating two artifacts with storedFiles and group_name metadata
        // 2. Calling applyDuplicateGroupResolution() to merge one group into another
        // 3. Verifying the merge succeeds without the "Undefined array key" error

        // GIVEN: Task run with merge process and merged artifacts
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);

        // Create stored files
        $storedFile1 = StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        $storedFile2 = StoredFile::factory()->create([
            'page_number' => 2,
            'filename'    => 'page-2.jpg',
            'filepath'    => 'test/page-2.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);

        // Create two artifacts representing the same logical group with slightly different names
        $sourceArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Acme Corp (1 files)',
            'meta'    => [
                'group_name' => 'Acme Corp',
                'file_count' => 1,
            ],
        ]);
        $sourceArtifact->storedFiles()->attach($storedFile1->id, ['category' => 'input']);

        $targetArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Acme Corporation (1 files)',
            'meta'    => [
                'group_name' => 'Acme Corporation',
                'file_count' => 1,
            ],
        ]);
        $targetArtifact->storedFiles()->attach($storedFile2->id, ['category' => 'input']);

        // Create merge process with these artifacts as outputs
        $mergeProcess = TaskProcess::factory()->create([
            'task_run_id' => $taskRun->id,
            'operation'   => FileOrganizationTaskRunner::OPERATION_MERGE,
            'status'      => TaskProcess::STATUS_COMPLETED,
        ]);

        $mergeProcess->outputArtifacts()->attach($sourceArtifact->id, ['category' => 'output']);
        $mergeProcess->outputArtifacts()->attach($targetArtifact->id, ['category' => 'output']);

        // Create resolution data that merges "Acme Corp" into "Acme Corporation"
        $resolutionData = [
            'group_decisions' => [
                [
                    'original_names' => ['Acme Corp', 'Acme Corporation'],
                    'canonical_name' => 'Acme Corporation',
                    'reason'         => 'Same company, merge groups',
                ],
            ],
        ];

        // WHEN: Apply duplicate group resolution (this should trigger the bug at line 423)
        $this->mergeService->applyDuplicateGroupResolution($taskRun, $resolutionData);

        // THEN: The merge should succeed without errors
        // Refresh the target artifact to see merged results
        $targetArtifact->refresh();

        // Target artifact should now have both stored files
        $mergedStoredFiles = $targetArtifact->storedFiles()->get();
        $this->assertCount(2, $mergedStoredFiles, 'Target artifact should have both stored files merged');

        $pageNumbers = $mergedStoredFiles->pluck('page_number')->sort()->values()->toArray();
        $this->assertEquals([1, 2], $pageNumbers, 'Target should contain both pages');

        // Source artifact should be deleted
        $this->assertNull(
            Artifact::find($sourceArtifact->id),
            'Source artifact should be deleted after merge'
        );

        // Target artifact name should be updated to reflect merged file count
        $this->assertEquals('Acme Corporation (2 files)', $targetArtifact->name);
    }
}
