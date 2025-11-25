<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\Artifact;
use App\Services\Task\FileOrganizationMergeService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class FileOrganizationMergeServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private FileOrganizationMergeService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(FileOrganizationMergeService::class);
    }

    #[Test]
    public function merges_simple_non_overlapping_windows(): void
    {
        // Given: Two windows with no overlapping files
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                ],
                groups: [
                    ['name' => 'group1', 'description' => 'First group', 'files' => [0, 1]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    ['name' => 'group2', 'description' => 'Second group', 'files' => [2, 3]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Both groups preserved with all files
        $this->assertCount(2, $result);
        $this->assertEquals('group1', $result[0]['name']);
        $this->assertEquals([1, 2], $result[0]['files']);
        $this->assertEquals('group2', $result[1]['name']);
        $this->assertEquals([3, 4], $result[1]['files']);
    }

    #[Test]
    public function merges_overlapping_windows_with_agreement(): void
    {
        // Given: Two windows that overlap and agree on file grouping
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [0, 1, 2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [1, 2, 3]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Single group with all files in order
        $this->assertCount(1, $result);
        $this->assertEquals('groupA', $result[0]['name']);
        $this->assertEquals([1, 2, 3, 4], $result[0]['files']);
    }

    #[Test]
    public function merges_overlapping_windows_with_conflicts_higher_confidence_wins(): void
    {
        // Given: Two windows that disagree on file grouping - higher confidence wins
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    [
                        'name'        => 'group1',
                        'description' => 'First group',
                        'files'       => [
                            ['page_number' => 0, 'confidence' => 3, 'explanation' => 'Low confidence'],
                            ['page_number' => 1, 'confidence' => 2, 'explanation' => 'Uncertain'],
                            ['page_number' => 2, 'confidence' => 2, 'explanation' => 'Uncertain'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    [
                        'name'        => 'group2',
                        'description' => 'Second group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 4, 'explanation' => 'High confidence'],
                            ['page_number' => 2, 'confidence' => 4, 'explanation' => 'High confidence'],
                            ['page_number' => 3, 'confidence' => 4, 'explanation' => 'High confidence'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: ALL files absorbed into group2 because files 1-2-3 were grouped together in window 1,
        // and when files 2-3 got reassigned to group2 with higher confidence, file 1 should follow
        // (conflict boundary absorption - files grouped together stay together when higher confidence wins)
        $this->assertCount(1, $result);

        // Find group by name
        $group2 = collect($result)->firstWhere('name', 'group2');

        $this->assertNotNull($group2);
        $this->assertEquals([1, 2, 3, 4], $group2['files']);
    }

    #[Test]
    public function maintains_file_order_by_position(): void
    {
        // Given: Files added out of order
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                    ['file_id' => 5, 'page_number' => 4],
                ],
                groups: [
                    ['name' => 'ordered', 'description' => 'Ordered group', 'files' => [4, 1, 3, 0, 2]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Files ordered by position
        $this->assertEquals([1, 2, 3, 4, 5], $result[0]['files']);
    }

    #[Test]
    public function handles_single_window(): void
    {
        // Given: Only one window
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    ['name' => 'solo', 'description' => 'Solo group', 'files' => [0, 1, 2]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Single group with all files
        $this->assertCount(1, $result);
        $this->assertEquals('solo', $result[0]['name']);
        $this->assertEquals([1, 2, 3], $result[0]['files']);
    }

    #[Test]
    public function handles_empty_groups(): void
    {
        // Given: Windows with empty groups
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                ],
                groups: [
                    ['name' => 'empty', 'description' => 'Empty group', 'files' => []],
                    ['name' => 'valid', 'description' => 'Valid group', 'files' => [0]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Only valid group with files
        $this->assertCount(1, $result);
        $this->assertEquals('valid', $result[0]['name']);
    }

    #[Test]
    public function handles_overlapping_files_in_different_groups(): void
    {
        // Given: Same file appears in different groups - same confidence means first wins
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    ['name' => 'first', 'description' => 'First group', 'files' => [0, 1]],
                    ['name' => 'second', 'description' => 'Second group', 'files' => [2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    ['name' => 'second', 'description' => 'Second group', 'files' => [1, 2, 3]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: With same confidence (default 3), first assignment wins - file 2 stays in 'first'
        $this->assertCount(2, $result);

        $first  = collect($result)->firstWhere('name', 'first');
        $second = collect($result)->firstWhere('name', 'second');

        $this->assertEquals([1, 2], $first['files']); // File 2 stays in 'first' (first assignment wins on tie)
        $this->assertEquals([3, 4], $second['files']);
    }

    #[Test]
    public function handles_maximum_window_size(): void
    {
        // Given: Window with 5 files (maximum overlap size)
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                    ['file_id' => 5, 'page_number' => 4],
                ],
                groups: [
                    ['name' => 'max', 'description' => 'Max size group', 'files' => [0, 1, 2, 3, 4]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: All 5 files grouped correctly
        $this->assertCount(1, $result);
        $this->assertEquals([1, 2, 3, 4, 5], $result[0]['files']);
    }

    #[Test]
    public function handles_minimum_window_size(): void
    {
        // Given: Window with 2 files (minimum valid size)
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                ],
                groups: [
                    ['name' => 'min', 'description' => 'Min size group', 'files' => [0, 1]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Both files grouped correctly
        $this->assertCount(1, $result);
        $this->assertEquals([1, 2], $result[0]['files']);
    }

    #[Test]
    public function handles_multiple_overlapping_windows_forming_continuous_groups(): void
    {
        // Given: Three overlapping windows that all agree on grouping
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [0, 1, 2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [1, 2, 3]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                    ['file_id' => 5, 'page_number' => 4],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [2, 3, 4]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: One continuous group with all files
        $this->assertCount(1, $result);
        $this->assertEquals('continuous', $result[0]['name']);
        $this->assertEquals([1, 2, 3, 4, 5], $result[0]['files']);
    }

    #[Test]
    public function handles_split_groups_file_breaks_continuity(): void
    {
        // Given: Windows that create a split in grouping - with same confidence, first assignment wins
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [0, 1]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [1]],
                    ['name' => 'groupB', 'description' => 'Group B', 'files' => [2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [2, 3]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: With same confidence, first assignment wins - creates split
        $this->assertCount(2, $result);

        $groupA = collect($result)->firstWhere('name', 'groupA');
        $groupB = collect($result)->firstWhere('name', 'groupB');

        $this->assertEquals([1, 2, 4], $groupA['files']); // File 3 goes to groupB (first assignment)
        $this->assertEquals([3], $groupB['files']);
    }

    #[Test]
    public function returns_empty_array_for_empty_collection(): void
    {
        // Given: Empty collection
        $artifacts = new Collection([]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Empty result
        $this->assertEmpty($result);
    }

    #[Test]
    public function handles_artifacts_with_missing_groups_data(): void
    {
        // Given: Artifacts with invalid or missing groups data
        $artifacts = new Collection([
            Artifact::factory()->create(['json_content' => null]),
            Artifact::factory()->create(['json_content' => ['other' => 'data']]),
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                ],
                groups: [
                    ['name' => 'valid', 'description' => 'Valid group', 'files' => [0]],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Only valid artifact processed
        $this->assertCount(1, $result);
        $this->assertEquals('valid', $result[0]['name']);
    }

    #[Test]
    public function handles_artifacts_with_missing_window_metadata(): void
    {
        // Given: Artifacts missing window_start or window_end
        $artifact = Artifact::factory()->create([
            'json_content' => [
                'groups' => [
                    ['name' => 'test', 'description' => 'Test group', 'files' => [0]],
                ],
            ],
            'meta' => ['other' => 'metadata'],
        ]);

        $artifacts = new Collection([$artifact]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Artifact skipped due to missing metadata
        $this->assertEmpty($result);
    }

    #[Test]
    public function creates_overlapping_windows_from_file_list(): void
    {
        // Given: List of 5 files with window size 3
        $files = [
            ['file_id' => 1, 'page_number' => 0],
            ['file_id' => 2, 'page_number' => 1],
            ['file_id' => 3, 'page_number' => 2],
            ['file_id' => 4, 'page_number' => 3],
            ['file_id' => 5, 'page_number' => 4],
        ];

        // When
        $windows = $this->service->createOverlappingWindows($files, 3);

        // Then: 2 overlapping windows created (last file of window N = first file of window N+1)
        $this->assertCount(2, $windows);

        // Window 0: files 1,2,3 (positions 0-2)
        $this->assertEquals(0, $windows[0]['window_start']);
        $this->assertEquals(2, $windows[0]['window_end']);
        $this->assertCount(3, $windows[0]['files']);
        $this->assertEquals(1, $windows[0]['files'][0]['file_id']);
        $this->assertEquals(3, $windows[0]['files'][2]['file_id']);

        // Window 1: files 3,4,5 (positions 2-4, overlapping at file 3)
        $this->assertEquals(2, $windows[1]['window_start']);
        $this->assertEquals(4, $windows[1]['window_end']);
        $this->assertCount(3, $windows[1]['files']);
        $this->assertEquals(3, $windows[1]['files'][0]['file_id']);
        $this->assertEquals(5, $windows[1]['files'][2]['file_id']);
    }

    #[Test]
    public function creates_overlapping_windows_skips_single_file_windows(): void
    {
        // Given: List of 3 files with window size 3
        $files = [
            ['file_id' => 1, 'page_number' => 0],
            ['file_id' => 2, 'page_number' => 1],
            ['file_id' => 3, 'page_number' => 2],
        ];

        // When
        $windows = $this->service->createOverlappingWindows($files, 3);

        // Then: 1 window created (files 1-3 fit in single window)
        $this->assertCount(1, $windows);
        $this->assertEquals(0, $windows[0]['window_start']);
        $this->assertEquals(2, $windows[0]['window_end']);
        $this->assertCount(3, $windows[0]['files']);
    }

    #[Test]
    public function creates_overlapping_windows_returns_empty_for_invalid_window_size(): void
    {
        // Given: Window size less than 2
        $files = [
            ['file_id' => 1, 'page_number' => 0],
            ['file_id' => 2, 'page_number' => 1],
        ];

        // When
        $windows = $this->service->createOverlappingWindows($files, 1);

        // Then: No windows created
        $this->assertEmpty($windows);
    }

    #[Test]
    public function creates_overlapping_windows_returns_empty_for_empty_file_list(): void
    {
        // Given: Empty file list
        $files = [];

        // When
        $windows = $this->service->createOverlappingWindows($files, 3);

        // Then: No windows created
        $this->assertEmpty($windows);
    }

    #[Test]
    public function gets_file_list_from_artifacts_in_position_order(): void
    {
        // Given: Artifacts with StoredFiles that have different page_numbers
        $artifacts = new Collection();
        foreach ([2, 0, 1] as $pageNum) {
            $artifact   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
            $storedFile = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
                'page_number' => $pageNum,
                'filename'    => "page-$pageNum.jpg",
                'filepath'    => "test/page-$pageNum.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
            $artifacts->push($artifact);
        }

        // When
        $files = $this->service->getFileListFromArtifacts($artifacts);

        // Then: Files ordered by page_number
        $this->assertCount(3, $files);
        $this->assertEquals(0, $files[0]['page_number']);
        $this->assertEquals(1, $files[1]['page_number']);
        $this->assertEquals(2, $files[2]['page_number']);
    }

    #[Test]
    public function gets_file_list_from_artifacts_uses_artifact_id_as_file_id(): void
    {
        // Given: Artifacts with StoredFiles
        $artifact1   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile1 = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 0,
            'filename'    => 'page-0.jpg',
            'filepath'    => 'test/page-0.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);
        $artifact1->storedFiles()->attach($storedFile1->id);

        $artifact2   = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 1,
            'filename'    => 'page-1.jpg',
            'filepath'    => 'test/page-1.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);
        $artifact2->storedFiles()->attach($storedFile2->id);

        $artifacts = new Collection([$artifact1, $artifact2]);

        // When
        $files = $this->service->getFileListFromArtifacts($artifacts);

        // Then: file_id matches artifact id
        $this->assertEquals($artifact1->id, $files[0]['file_id']);
        $this->assertEquals($artifact2->id, $files[1]['file_id']);
    }

    /**
     * Helper method to create a window artifact with specific structure
     */
    private function createWindowArtifact(int $windowStart, int $windowEnd, array $windowFiles, array $groups): Artifact
    {
        return Artifact::factory()->create([
            'json_content' => [
                'groups' => $groups,
            ],
            'meta' => [
                'window_start' => $windowStart,
                'window_end'   => $windowEnd,
                'window_files' => $windowFiles,
            ],
        ]);
    }

    #[Test]
    public function identifyLowConfidenceFiles_only_returns_files_with_multiple_assignments(): void
    {
        // Given: Three windows with various low-confidence assignments
        $artifacts = new Collection([
            // Window 1: File 1 in Group A (confidence 2), File 2 in Group B (confidence 1)
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    [
                        'name'        => 'Group A',
                        'description' => 'First group',
                        'files'       => [
                            ['page_number' => 0, 'confidence' => 2, 'explanation' => 'Uncertain about Group A'],
                        ],
                    ],
                    [
                        'name'        => 'Group B',
                        'description' => 'Second group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 1, 'explanation' => 'Very uncertain'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'Definitely Group B'],
                        ],
                    ],
                ]
            ),
            // Window 2: File 1 appears again in Group B (confidence 2) - CONFLICT!
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                ],
                groups: [
                    [
                        'name'        => 'Group B',
                        'description' => 'Second group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 2, 'explanation' => 'Could be Group B'],
                            ['page_number' => 2, 'confidence' => 4, 'explanation' => 'Likely Group B'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'Definitely Group B'],
                        ],
                    ],
                ]
            ),
            // Window 3: File 1 appears in Group C (confidence 1) - ANOTHER CONFLICT!
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 3, 'page_number' => 2],
                    ['file_id' => 4, 'page_number' => 3],
                    ['file_id' => 1, 'page_number' => 0],
                ],
                groups: [
                    [
                        'name'        => 'Group C',
                        'description' => 'Third group',
                        'files'       => [
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'Definitely Group C'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'Definitely Group C'],
                            ['page_number' => 0, 'confidence' => 1, 'explanation' => 'Maybe Group C?'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $fileToGroup = $mergeResult['file_to_group_mapping'];

        $lowConfidenceFiles = $this->service->identifyLowConfidenceFiles($fileToGroup);

        // Then: Only File 1 should be returned (appeared in Groups A, B, and C with low confidence)
        // File 2 should NOT be returned (only appeared in Group B, even though confidence is 2)
        $this->assertCount(1, $lowConfidenceFiles, 'Only files with MULTIPLE different group assignments should need resolution');

        $lowConfFile = $lowConfidenceFiles[0];
        $this->assertEquals(1, $lowConfFile['file_id'], 'File 1 should need resolution (appeared in 3 different groups)');
        $this->assertEquals(0, $lowConfFile['page_number']);

        // Verify it has multiple explanations from different groups
        $uniqueGroups = array_unique(array_column($lowConfFile['all_explanations'], 'group_name'));
        $this->assertGreaterThan(1, count($uniqueGroups), 'File should have appeared in multiple different groups');
    }

    #[Test]
    public function identifyLowConfidenceFiles_keeps_single_low_confidence_assignment(): void
    {
        // Given: A file with low confidence but only ONE group assignment
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 0],
                    ['file_id' => 2, 'page_number' => 1],
                    ['file_id' => 3, 'page_number' => 2],
                ],
                groups: [
                    [
                        'name'        => 'Group A',
                        'description' => 'First group',
                        'files'       => [
                            ['page_number' => 0, 'confidence' => 5, 'explanation' => 'Definitely A'],
                            ['page_number' => 1, 'confidence' => 2, 'explanation' => 'Uncertain but only option'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'Definitely A'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $fileToGroup = $mergeResult['file_to_group_mapping'];

        $lowConfidenceFiles = $this->service->identifyLowConfidenceFiles($fileToGroup);

        // Then: No files should need resolution (File 2 only appeared in one group)
        $this->assertEmpty($lowConfidenceFiles, 'Files with only ONE low-confidence assignment should not need resolution');

        // Verify File 2 is still in the final groups (not discarded)
        $finalGroups = $mergeResult['groups'];
        $groupA      = $finalGroups[0];
        $this->assertContains(2, $groupA['files'], 'Low-confidence file with single assignment should still be included in final groups');
    }

    #[Test]
    public function cascade_absorption_two_level_chain(): void
    {
        // Given: 3 windows A, B, C where:
        // A: pages 1-4 in "Tiger" (conf 5)
        // B: pages 4-7 in "Lion" (conf 4) - page 4 overlaps with A
        // C: pages 7-10 in "Bear" (conf 4) - page 7 overlaps with B
        // Expected: A wins page 4, absorbs all of B, which then absorbs all of C
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 1],
                    ['file_id' => 2, 'page_number' => 2],
                    ['file_id' => 3, 'page_number' => 3],
                    ['file_id' => 4, 'page_number' => 4],
                ],
                groups: [
                    [
                        'name'        => 'Tiger',
                        'description' => 'High confidence group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'High conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 4,
                windowEnd: 7,
                windowFiles: [
                    ['file_id' => 4, 'page_number' => 4],
                    ['file_id' => 5, 'page_number' => 5],
                    ['file_id' => 6, 'page_number' => 6],
                    ['file_id' => 7, 'page_number' => 7],
                ],
                groups: [
                    [
                        'name'        => 'Lion',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 6, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 7,
                windowEnd: 10,
                windowFiles: [
                    ['file_id' => 7, 'page_number' => 7],
                    ['file_id' => 8, 'page_number' => 8],
                    ['file_id' => 9, 'page_number' => 9],
                    ['file_id' => 10, 'page_number' => 10],
                ],
                groups: [
                    [
                        'name'        => 'Bear',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 8, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 9, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 10, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: All files should be in Tiger due to cascade absorption
        $this->assertCount(1, $result, 'Should have only one group after cascade absorption');
        $tiger = collect($result)->firstWhere('name', 'Tiger');
        $this->assertNotNull($tiger);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $tiger['files'], 'All files should cascade into Tiger');
    }

    #[Test]
    public function cascade_absorption_stops_at_split_group(): void
    {
        // Given: 4 windows A, B, C, D where:
        // A: pages 1-4 in "Tiger" (conf 5)
        // B: pages 4-7 in "Lion" (conf 4) - page 4 overlaps with A
        // C: pages 7-10 in "Bear" (conf 4) - page 7 overlaps with B
        // D: pages 10-13 with TWO groups: "Pig" (10-11) and "Wolf" (12-13)
        // Expected: A absorbs B, B absorbs C, C absorbs only Pig (not Wolf because split)
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 1],
                    ['file_id' => 2, 'page_number' => 2],
                    ['file_id' => 3, 'page_number' => 3],
                    ['file_id' => 4, 'page_number' => 4],
                ],
                groups: [
                    [
                        'name'        => 'Tiger',
                        'description' => 'High confidence group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'High conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 4,
                windowEnd: 7,
                windowFiles: [
                    ['file_id' => 4, 'page_number' => 4],
                    ['file_id' => 5, 'page_number' => 5],
                    ['file_id' => 6, 'page_number' => 6],
                    ['file_id' => 7, 'page_number' => 7],
                ],
                groups: [
                    [
                        'name'        => 'Lion',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 6, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 7,
                windowEnd: 10,
                windowFiles: [
                    ['file_id' => 7, 'page_number' => 7],
                    ['file_id' => 8, 'page_number' => 8],
                    ['file_id' => 9, 'page_number' => 9],
                    ['file_id' => 10, 'page_number' => 10],
                ],
                groups: [
                    [
                        'name'        => 'Bear',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 8, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 9, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 10, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 10,
                windowEnd: 13,
                windowFiles: [
                    ['file_id' => 10, 'page_number' => 10],
                    ['file_id' => 11, 'page_number' => 11],
                    ['file_id' => 12, 'page_number' => 12],
                    ['file_id' => 13, 'page_number' => 13],
                ],
                groups: [
                    [
                        'name'        => 'Pig',
                        'description' => 'First split group',
                        'files'       => [
                            ['page_number' => 10, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 11, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                    [
                        'name'        => 'Wolf',
                        'description' => 'Second split group',
                        'files'       => [
                            ['page_number' => 12, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 13, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Tiger should have pages 1-11, Wolf should remain separate
        $this->assertCount(2, $result, 'Should have Tiger (with cascade absorption) and Wolf (separate)');

        $tiger = collect($result)->firstWhere('name', 'Tiger');
        $wolf  = collect($result)->firstWhere('name', 'Wolf');

        $this->assertNotNull($tiger);
        $this->assertNotNull($wolf);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], $tiger['files'], 'Tiger should cascade through to include Pig');
        $this->assertEquals([12, 13], $wolf['files'], 'Wolf should remain separate (different group in window D)');
    }

    #[Test]
    public function cascade_absorption_with_same_confidence_no_absorption(): void
    {
        // Given: 2 windows A, B with same confidence
        // A: pages 1-4 in "Tiger" (conf 4)
        // B: pages 4-7 in "Lion" (conf 4) - page 4 overlaps with A
        // Expected: NO absorption because confidence is equal (not higher)
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 1],
                    ['file_id' => 2, 'page_number' => 2],
                    ['file_id' => 3, 'page_number' => 3],
                    ['file_id' => 4, 'page_number' => 4],
                ],
                groups: [
                    [
                        'name'        => 'Tiger',
                        'description' => 'First group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 2, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 3, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 4,
                windowEnd: 7,
                windowFiles: [
                    ['file_id' => 4, 'page_number' => 4],
                    ['file_id' => 5, 'page_number' => 5],
                    ['file_id' => 6, 'page_number' => 6],
                    ['file_id' => 7, 'page_number' => 7],
                ],
                groups: [
                    [
                        'name'        => 'Lion',
                        'description' => 'Second group',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 6, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Both groups should remain (no absorption with equal confidence - first wins)
        $this->assertCount(2, $result, 'Should have both groups (no absorption with equal confidence)');

        $tiger = collect($result)->firstWhere('name', 'Tiger');
        $lion  = collect($result)->firstWhere('name', 'Lion');

        $this->assertNotNull($tiger);
        $this->assertNotNull($lion);
        $this->assertEquals([1, 2, 3, 4], $tiger['files'], 'Tiger keeps its original files (first wins on tie)');
        $this->assertEquals([5, 6, 7], $lion['files'], 'Lion keeps non-overlapping files');
    }

    #[Test]
    public function cascade_absorption_backward_direction(): void
    {
        // Given: Test backward absorption where later high-confidence group pulls in earlier files
        // Window A: Pages 93-95 in group "X" (conf 3)
        // Window B: Pages 95-97 in group "Y" where page 97 is conf 5, page 95 is conf 4
        // Expected: Page 95 wins with GroupY (conf 4 > 3), absorbs pages 96-97
        //           Then backward absorption pulls in pages 93-94 from GroupX
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 93,
                windowEnd: 95,
                windowFiles: [
                    ['file_id' => 93, 'page_number' => 93],
                    ['file_id' => 94, 'page_number' => 94],
                    ['file_id' => 95, 'page_number' => 95],
                ],
                groups: [
                    [
                        'name'        => 'GroupX',
                        'description' => 'Earlier group',
                        'files'       => [
                            ['page_number' => 93, 'confidence' => 3, 'explanation' => 'Low conf'],
                            ['page_number' => 94, 'confidence' => 3, 'explanation' => 'Low conf'],
                            ['page_number' => 95, 'confidence' => 3, 'explanation' => 'Low conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 95,
                windowEnd: 97,
                windowFiles: [
                    ['file_id' => 95, 'page_number' => 95],
                    ['file_id' => 96, 'page_number' => 96],
                    ['file_id' => 97, 'page_number' => 97],
                ],
                groups: [
                    [
                        'name'        => 'GroupY',
                        'description' => 'Later group with high confidence',
                        'files'       => [
                            ['page_number' => 95, 'confidence' => 4, 'explanation' => 'Med conf - wins!'],
                            ['page_number' => 96, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 97, 'confidence' => 5, 'explanation' => 'High conf!'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: All files should be in GroupY due to backward cascade absorption
        $this->assertCount(1, $result, 'Should have only GroupY after backward cascade absorption');

        $groupY = collect($result)->firstWhere('name', 'GroupY');
        $this->assertNotNull($groupY);
        $this->assertEquals([93, 94, 95, 96, 97], $groupY['files'], 'All files absorbed into GroupY (page 95 wins, triggers backward absorption of 93-94)');
    }

    #[Test]
    public function cascade_absorption_bidirectional_complex(): void
    {
        // Given: Test both forward and backward absorption in a complex chain
        // Window A: Pages 1-3 in "Alpha" (conf 3)
        // Window B: Pages 3-5 in "Beta" (conf 4) - page 3 overlaps
        // Window C: Pages 5-7 in "Gamma" (conf 5) - page 5 overlaps
        // Expected:
        // 1. Page 5: Gamma (conf 5) beats Beta (conf 4) → forward absorb Beta pages 3-4
        // 2. Page 3 now in Gamma (conf 5) → backward absorb Alpha pages 1-2
        // Result: All pages in Gamma
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 1],
                    ['file_id' => 2, 'page_number' => 2],
                    ['file_id' => 3, 'page_number' => 3],
                ],
                groups: [
                    [
                        'name'        => 'Alpha',
                        'description' => 'Low confidence group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 3, 'explanation' => 'Low conf'],
                            ['page_number' => 2, 'confidence' => 3, 'explanation' => 'Low conf'],
                            ['page_number' => 3, 'confidence' => 3, 'explanation' => 'Low conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 3,
                windowEnd: 5,
                windowFiles: [
                    ['file_id' => 3, 'page_number' => 3],
                    ['file_id' => 4, 'page_number' => 4],
                    ['file_id' => 5, 'page_number' => 5],
                ],
                groups: [
                    [
                        'name'        => 'Beta',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 3, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Med conf'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Med conf'],
                        ],
                    ],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 5,
                windowEnd: 7,
                windowFiles: [
                    ['file_id' => 5, 'page_number' => 5],
                    ['file_id' => 6, 'page_number' => 6],
                    ['file_id' => 7, 'page_number' => 7],
                ],
                groups: [
                    [
                        'name'        => 'Gamma',
                        'description' => 'High confidence group',
                        'files'       => [
                            ['page_number' => 5, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 6, 'confidence' => 5, 'explanation' => 'High conf'],
                            ['page_number' => 7, 'confidence' => 5, 'explanation' => 'High conf'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: All files should be in Gamma due to bidirectional cascade
        $this->assertCount(1, $result, 'Should have only Gamma after bidirectional cascade');

        $gamma = collect($result)->firstWhere('name', 'Gamma');
        $this->assertNotNull($gamma);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7], $gamma['files'], 'All files absorbed into Gamma (forward from page 5, then backward through page 3)');
    }
}
