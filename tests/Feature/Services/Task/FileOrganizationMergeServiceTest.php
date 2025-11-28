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
            'meta'         => ['other' => 'metadata'],
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
            'meta'         => [
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

    #[Test]
    public function test_high_confidence_file_not_absorbed_when_adjacent_null_group_is_absorbed(): void
    {
        // Given: 3 agent responses (windows) that reproduce the bug
        //
        // Agent A (pages 105-109): All in single high confidence group "ME Physical Therapy"
        // Agent B (pages 109-113): 2 groups:
        //   - Group 1: pages 109-112 in "" (null/low confidence group)
        //   - Group 2: page 113 in "Mountain View Pain Specialists" (high confidence 5)
        // Agent C (pages 113-117): All in single high confidence group "Mountain View Pain Specialists"
        //
        // Expected correct behavior:
        // - Pages 105-109: "ME Physical Therapy" (from Agent A)
        // - Pages 110-112: Should be absorbed into "ME Physical Therapy" (null group absorbed to previous)
        // - Pages 113-117: "Mountain View Pain Specialists" (page 113 has conf 5, should NOT be absorbed)
        //
        // The bug: Page 113 (confidence 5 in "Mountain View Pain Specialists") is incorrectly
        // absorbed into "ME Physical Therapy" along with subsequent pages 114-117, even though
        // page 113 was in a DIFFERENT group than the null group pages in Agent B's response.

        $artifacts = new Collection([
            // Agent A: Pages 105-109 all in "ME Physical Therapy" (conf 5)
            $this->createWindowArtifact(
                windowStart: 105,
                windowEnd: 109,
                windowFiles: [
                    ['file_id' => 105, 'page_number' => 105],
                    ['file_id' => 106, 'page_number' => 106],
                    ['file_id' => 107, 'page_number' => 107],
                    ['file_id' => 108, 'page_number' => 108],
                    ['file_id' => 109, 'page_number' => 109],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'High confidence medical group',
                        'files'       => [
                            ['page_number' => 105, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 106, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 107, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 108, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 109, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                        ],
                    ],
                ]
            ),
            // Agent B: Pages 109-113 with 2 groups
            // - Group 1: pages 109-112 in null group (low confidence)
            // - Group 2: page 113 in "Mountain View Pain Specialists" (high confidence)
            $this->createWindowArtifact(
                windowStart: 109,
                windowEnd: 113,
                windowFiles: [
                    ['file_id' => 109, 'page_number' => 109],
                    ['file_id' => 110, 'page_number' => 110],
                    ['file_id' => 111, 'page_number' => 111],
                    ['file_id' => 112, 'page_number' => 112],
                    ['file_id' => 113, 'page_number' => 113],
                ],
                groups: [
                    [
                        'name'        => '',
                        'description' => 'Unknown - no clear identifier found on these pages',
                        'files'       => [
                            ['page_number' => 109, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 110, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 111, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 112, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                        ],
                    ],
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'CMS-1500 claim form with explicit Billing Provider',
                        'files'       => [
                            ['page_number' => 113, 'confidence' => 5, 'explanation' => 'Clear Mountain View Pain Specialists identifier on claim form'],
                        ],
                    ],
                ]
            ),
            // Agent C: Pages 113-117 all in "Mountain View Pain Specialists" (conf 4-5)
            $this->createWindowArtifact(
                windowStart: 113,
                windowEnd: 117,
                windowFiles: [
                    ['file_id' => 113, 'page_number' => 113],
                    ['file_id' => 114, 'page_number' => 114],
                    ['file_id' => 115, 'page_number' => 115],
                    ['file_id' => 116, 'page_number' => 116],
                    ['file_id' => 117, 'page_number' => 117],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'Complete visit record for Mountain View Pain Specialists',
                        'files'       => [
                            ['page_number' => 113, 'confidence' => 5, 'explanation' => 'Health Insurance Claim Form with Billing Provider'],
                            ['page_number' => 114, 'confidence' => 4, 'explanation' => 'Clinical note with Mountain View header'],
                            ['page_number' => 115, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                            ['page_number' => 116, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                            ['page_number' => 117, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];
        $fileToGroup = $mergeResult['file_to_group_mapping'];

        // Then: Expected correct behavior
        // The null group (pages 110-112) should be absorbed into "ME Physical Therapy"
        // Page 109 stays in "ME Physical Therapy" (higher confidence from Agent A wins)
        // Page 113 should stay in "Mountain View Pain Specialists" (high confidence, different group)
        $this->assertCount(2, $result, 'Should have 2 distinct groups (ME Physical Therapy and Mountain View Pain Specialists)');

        $mePhysicalTherapy = collect($result)->firstWhere('name', 'ME Physical Therapy');
        $mountainView      = collect($result)->firstWhere('name', 'Mountain View Pain Specialists');

        // Verify ME Physical Therapy group includes absorbed null group pages
        $this->assertNotNull($mePhysicalTherapy, 'ME Physical Therapy group should exist');
        $this->assertEquals(
            [105, 106, 107, 108, 109, 110, 111, 112],
            $mePhysicalTherapy['files'],
            'ME Physical Therapy should include pages 105-112 (including absorbed null group pages 110-112)'
        );

        // Verify Mountain View Pain Specialists group is separate and NOT absorbed
        $this->assertNotNull($mountainView, 'Mountain View Pain Specialists group should exist');
        $this->assertEquals(
            [113, 114, 115, 116, 117],
            $mountainView['files'],
            'Mountain View Pain Specialists should include pages 113-117 (page 113 should NOT be absorbed by ME Physical Therapy)'
        );
    }

    #[Test]
    public function test_production_bug_page_113_absorbed_incorrectly(): void
    {
        // This test reproduces the ACTUAL bug from production
        // The difference: After null group resolution, the absorption logic incorrectly
        // absorbs page 113 into "ME Physical Therapy" even though it was in a DIFFERENT
        // group within Agent B's window

        $artifacts = collect([
            // Agent A: Pages 105-109 all in "ME Physical Therapy" (conf 5)
            $this->createWindowArtifact(
                windowStart: 105,
                windowEnd: 109,
                windowFiles: [
                    ['file_id' => 105, 'page_number' => 105],
                    ['file_id' => 106, 'page_number' => 106],
                    ['file_id' => 107, 'page_number' => 107],
                    ['file_id' => 108, 'page_number' => 108],
                    ['file_id' => 109, 'page_number' => 109],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'Physical therapy records with clear ME PT letterhead',
                        'files'       => [
                            ['page_number' => 105, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 106, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 107, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 108, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                            ['page_number' => 109, 'confidence' => 5, 'explanation' => 'Clear ME Physical Therapy identifier'],
                        ],
                    ],
                ]
            ),
            // Agent B: Pages 109-113 with 2 groups
            // - Group 1: pages 109-112 in null/"" group (confidence 1)
            // - Group 2: page 113 in "Mountain View Pain Specialists" (confidence 5)
            // KEY: Page 113 is in a DIFFERENT group than 109-112 within this window
            $this->createWindowArtifact(
                windowStart: 109,
                windowEnd: 113,
                windowFiles: [
                    ['file_id' => 109, 'page_number' => 109],
                    ['file_id' => 110, 'page_number' => 110],
                    ['file_id' => 111, 'page_number' => 111],
                    ['file_id' => 112, 'page_number' => 112],
                    ['file_id' => 113, 'page_number' => 113],
                ],
                groups: [
                    [
                        'name'        => '',
                        'description' => 'Unknown - no clear identifier found on these pages',
                        'files'       => [
                            ['page_number' => 109, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 110, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 111, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                            ['page_number' => 112, 'confidence' => 1, 'explanation' => 'No clear group identifier found'],
                        ],
                    ],
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'CMS-1500 claim form with explicit Billing Provider',
                        'files'       => [
                            ['page_number' => 113, 'confidence' => 5, 'explanation' => 'Clear Mountain View Pain Specialists identifier on claim form'],
                        ],
                    ],
                ]
            ),
            // Agent C: Pages 114-118 all in "Mountain View Pain Specialists" (conf 4)
            // KEY BUG TRIGGER: Page 113 is NOT in this window, so it only has ONE assignment
            // This creates a single-confidence file that is adjacent to the null group
            $this->createWindowArtifact(
                windowStart: 114,
                windowEnd: 118,
                windowFiles: [
                    ['file_id' => 114, 'page_number' => 114],
                    ['file_id' => 115, 'page_number' => 115],
                    ['file_id' => 116, 'page_number' => 116],
                    ['file_id' => 117, 'page_number' => 117],
                    ['file_id' => 118, 'page_number' => 118],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'Complete visit record for Mountain View Pain Specialists',
                        'files'       => [
                            ['page_number' => 114, 'confidence' => 4, 'explanation' => 'Clinical note with Mountain View header'],
                            ['page_number' => 115, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                            ['page_number' => 116, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                            ['page_number' => 117, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                            ['page_number' => 118, 'confidence' => 4, 'explanation' => 'Clinical note continuation'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];
        $fileToGroup = $mergeResult['file_to_group_mapping'];

        // Expected: This test should FAIL because of the bug
        // The bug causes page 113 (and 114-118) to be absorbed into "ME Physical Therapy"
        // when it should stay in "Mountain View Pain Specialists"

        // What SHOULD happen:
        $mePhysicalTherapy = collect($result)->firstWhere('name', 'ME Physical Therapy');
        $mountainView      = collect($result)->firstWhere('name', 'Mountain View Pain Specialists');

        // ME Physical Therapy should include pages 105-112 (null group absorbed)
        $this->assertNotNull($mePhysicalTherapy, 'ME Physical Therapy group should exist');
        $this->assertEquals(
            [105, 106, 107, 108, 109, 110, 111, 112],
            $mePhysicalTherapy['files'],
            'ME Physical Therapy should include pages 105-112 (null group pages 110-112 absorbed)'
        );

        // Mountain View Pain Specialists should include pages 113-118 (NOT absorbed)
        // THE BUG: Page 113 only appears in Agent B with conf 5
        // But it's adjacent to page 112 which was in the null group (now absorbed into ME PT)
        // The hasAdjacentFilesFromSameWindow check might incorrectly absorb page 113
        // because it's adjacent and was in the same window (Agent B) as the null group pages
        $this->assertNotNull($mountainView, 'Mountain View Pain Specialists group should exist');
        $this->assertEquals(
            [113, 114, 115, 116, 117, 118],
            $mountainView['files'],
            'BUG: Mountain View Pain Specialists should include pages 113-118, but page 113 gets incorrectly absorbed into ME Physical Therapy'
        );
    }

    #[Test]
    public function confidence_5_group_should_absorb_overlapping_confidence_4_group(): void
    {
        // Given: Two overlapping windows where Group A has confidence 5 and Group B has confidence 4
        // Since they overlap (page 4), Group A (conf 5) should ABSORB ALL of Group B (conf 4)
        // This tests the CORRECT direction of absorption - higher confidence absorbs lower
        $artifacts = new Collection([
            // Window 1: Pages 1-4 in "HighConf" with confidence 5
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
                        'name'        => 'HighConf',
                        'description' => 'High confidence group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 5, 'explanation' => 'Very high confidence'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'Very high confidence'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'Very high confidence'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'Very high confidence'],
                        ],
                    ],
                ]
            ),
            // Window 2: Pages 4-7 in "MedConf" with confidence 4 (page 4 overlaps)
            // MedConf should be ABSORBED into HighConf because:
            // 1. They overlap on page 4
            // 2. HighConf group confidence (5) > MedConf group confidence (4)
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
                        'name'        => 'MedConf',
                        'description' => 'Medium confidence group',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 6, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: HighConf (conf 5) should absorb ALL of MedConf (conf 4) because they overlap
        $this->assertCount(1, $result, 'Should have only HighConf after absorption (group conf 5 > 4 with overlap)');

        $highConf = collect($result)->firstWhere('name', 'HighConf');

        $this->assertNotNull($highConf, 'HighConf group should exist');

        $this->assertEquals(
            [1, 2, 3, 4, 5, 6, 7],
            $highConf['files'],
            'HighConf (group conf 5) should absorb ALL of MedConf (group conf 4) because they overlap on page 4'
        );
    }

    #[Test]
    public function equal_confidence_groups_should_not_absorb_each_other(): void
    {
        // Given: Two groups with EQUAL group-level confidence (both 5)
        // Even though they overlap, equal confidence means NO absorption (first wins on tie)
        $artifacts = new Collection([
            // Window 1: Pages 1-4 in "GroupA" - mixed confidence but MAX is 5
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
                        'name'        => 'GroupA',
                        'description' => 'First group with max confidence 5',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 3, 'explanation' => 'Lower confidence item'],
                            ['page_number' => 2, 'confidence' => 3, 'explanation' => 'Lower confidence item'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'High confidence item - defines group confidence'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'High confidence item'],
                        ],
                    ],
                ]
            ),
            // Window 2: Pages 4-7 in "GroupB" - all confidence 5
            // Group-level confidence: GroupA = 5 (max), GroupB = 5 (max) → EQUAL, no absorption
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
                        'name'        => 'GroupB',
                        'description' => 'Second group with all confidence 5',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'High confidence'],
                            ['page_number' => 5, 'confidence' => 5, 'explanation' => 'High confidence'],
                            ['page_number' => 6, 'confidence' => 5, 'explanation' => 'High confidence'],
                            ['page_number' => 7, 'confidence' => 5, 'explanation' => 'High confidence'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Both groups should remain separate (equal group-level confidence = no absorption)
        $this->assertCount(2, $result, 'Should have 2 separate groups (equal confidence = no absorption)');

        $groupA = collect($result)->firstWhere('name', 'GroupA');
        $groupB = collect($result)->firstWhere('name', 'GroupB');

        $this->assertNotNull($groupA, 'GroupA should exist');
        $this->assertNotNull($groupB, 'GroupB should exist');

        $this->assertEquals(
            [1, 2, 3, 4],
            $groupA['files'],
            'GroupA should keep page 4 (first assignment wins on tie, both groups have max confidence 5)'
        );

        $this->assertEquals(
            [5, 6, 7],
            $groupB['files'],
            'GroupB should only have non-overlapping pages 5-7'
        );
    }

    #[Test]
    public function group_confidence_based_on_max_item_confidence(): void
    {
        // Given: Group A has mixed confidence (1,1,5), Group B has uniform confidence (4,4,4)
        // Group-level confidence: A = 5 (max of items), B = 4 (max of items)
        // Expected: A (conf 5) absorbs B (conf 4) on overlapping page
        $artifacts = new Collection([
            // Window 1: Pages 1-4 in "GroupA" - mostly low confidence but one HIGH confidence item
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
                        'name'        => 'GroupA',
                        'description' => 'Mixed confidence group',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 1, 'explanation' => 'Very low confidence'],
                            ['page_number' => 2, 'confidence' => 1, 'explanation' => 'Very low confidence'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'VERY HIGH confidence - defines group!'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'VERY HIGH confidence'],
                        ],
                    ],
                ]
            ),
            // Window 2: Pages 4-7 in "GroupB" - all uniform medium confidence
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
                        'name'        => 'GroupB',
                        'description' => 'Uniform medium confidence group',
                        'files'       => [
                            ['page_number' => 4, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 5, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 6, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                            ['page_number' => 7, 'confidence' => 4, 'explanation' => 'Medium confidence'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: GroupA (max conf 5) should absorb ALL of GroupB (max conf 4)
        $this->assertCount(1, $result, 'Should have only GroupA after absorption (group conf 5 > 4)');

        $groupA = collect($result)->firstWhere('name', 'GroupA');

        $this->assertNotNull($groupA, 'GroupA should exist');
        $this->assertEquals(
            [1, 2, 3, 4, 5, 6, 7],
            $groupA['files'],
            'GroupA (group-level conf 5 from max item) should absorb ALL of GroupB (group-level conf 4)'
        );
    }

    #[Test]
    public function production_bug_page_113_confidence_5_absorbed_into_wrong_group(): void
    {
        // CRITICAL BUG: Page 113 with confidence 5 for Mountain View gets absorbed into ME Physical Therapy
        //
        // This test uses EXACTLY 3 agent responses from production:
        //
        // Agent 1 - Window 109-113 with 2 groups:
        //   - "Ivo Milic-Strkalj, DPT": pages 109-112 (conf 3,3,3,2)
        //   - "Mountain View Pain Specialists": page 113 (conf 5 - BILLING PROVIDER!)
        //
        // Agent 2 - Window 113-117 with 1 group:
        //   - "Mountain View Pain Specialists": pages 113-117 (conf 5,4,4,4,4)
        //
        // Agent 3 - Window 105-109 with 1 group:
        //   - "ME Physical Therapy": pages 105-109 (all conf 5)
        //
        // Expected result:
        //   - ME Physical Therapy: pages 105-112 (absorbed Ivo DPT via page 109 boundary)
        //   - Mountain View: pages 113-117 (MUST keep page 113 with conf 5!)
        //
        // Actual bug:
        //   - Page 113 gets absorbed into ME Physical Therapy (WRONG!)
        //   - Confidence 5 = Billing Provider absolute authority - should NEVER be absorbed

        $artifacts = new Collection([
            // Agent Response 0: Window 1-5 with 1 group (prior high confidence ME PT group)
            // This creates a high confidence base for ME Physical Therapy
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 5,
                windowFiles: [
                    ['file_id' => 1, 'page_number' => 1],
                    ['file_id' => 2, 'page_number' => 2],
                    ['file_id' => 3, 'page_number' => 3],
                    ['file_id' => 4, 'page_number' => 4],
                    ['file_id' => 5, 'page_number' => 5],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'Early PT records',
                        'files'       => [
                            ['page_number' => 1, 'confidence' => 5, 'explanation' => 'PT documentation'],
                            ['page_number' => 2, 'confidence' => 5, 'explanation' => 'PT documentation'],
                            ['page_number' => 3, 'confidence' => 5, 'explanation' => 'PT documentation'],
                            ['page_number' => 4, 'confidence' => 5, 'explanation' => 'PT documentation'],
                            ['page_number' => 5, 'confidence' => 5, 'explanation' => 'PT documentation'],
                        ],
                    ],
                ]
            ),

            // Agent Response 1: Window 105-109 with 1 group
            $this->createWindowArtifact(
                windowStart: 105,
                windowEnd: 109,
                windowFiles: [
                    ['file_id' => 105, 'page_number' => 105],
                    ['file_id' => 106, 'page_number' => 106],
                    ['file_id' => 107, 'page_number' => 107],
                    ['file_id' => 108, 'page_number' => 108],
                    ['file_id' => 109, 'page_number' => 109],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'PT visit packet with CMS-1500',
                        'files'       => [
                            ['page_number' => 105, 'confidence' => 5, 'explanation' => 'PT flowsheet'],
                            ['page_number' => 106, 'confidence' => 5, 'explanation' => 'PT note with signatures'],
                            ['page_number' => 107, 'confidence' => 5, 'explanation' => 'CMS-1500 with ME Physical Therapy Billing Provider'],
                            ['page_number' => 108, 'confidence' => 5, 'explanation' => 'Header with ME PT logo'],
                            ['page_number' => 109, 'confidence' => 5, 'explanation' => 'Patient history'],
                        ],
                    ],
                ]
            ),

            // Agent Response 2: Window 109-113 with 2 groups
            $this->createWindowArtifact(
                windowStart: 109,
                windowEnd: 113,
                windowFiles: [
                    ['file_id' => 109, 'page_number' => 109],
                    ['file_id' => 110, 'page_number' => 110],
                    ['file_id' => 111, 'page_number' => 111],
                    ['file_id' => 112, 'page_number' => 112],
                    ['file_id' => 113, 'page_number' => 113],
                ],
                groups: [
                    [
                        'name'        => 'Ivo Milic-Strkalj, DPT',
                        'description' => 'Physical therapy progress note',
                        'files'       => [
                            ['page_number' => 109, 'confidence' => 3, 'explanation' => 'Start of PT note, no Billing Provider'],
                            ['page_number' => 110, 'confidence' => 3, 'explanation' => 'Continuation PT note'],
                            ['page_number' => 111, 'confidence' => 3, 'explanation' => 'PT note with DPT signature'],
                            ['page_number' => 112, 'confidence' => 2, 'explanation' => 'Blank page'],
                        ],
                    ],
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'CMS-1500 claim form',
                        'files'       => [
                            ['page_number' => 113, 'confidence' => 5, 'explanation' => 'CMS-1500 with explicit Billing Provider - ABSOLUTE AUTHORITY'],
                        ],
                    ],
                ]
            ),

            // Agent Response 3: Window 113-117 with 1 group
            $this->createWindowArtifact(
                windowStart: 113,
                windowEnd: 117,
                windowFiles: [
                    ['file_id' => 113, 'page_number' => 113],
                    ['file_id' => 114, 'page_number' => 114],
                    ['file_id' => 115, 'page_number' => 115],
                    ['file_id' => 116, 'page_number' => 116],
                    ['file_id' => 117, 'page_number' => 117],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'CMS-1500 claim form with clinical notes',
                        'files'       => [
                            ['page_number' => 113, 'confidence' => 5, 'explanation' => 'CMS-1500 with explicit Billing Provider'],
                            ['page_number' => 114, 'confidence' => 4, 'explanation' => 'Clinical note page 1'],
                            ['page_number' => 115, 'confidence' => 4, 'explanation' => 'Clinical note page 2'],
                            ['page_number' => 116, 'confidence' => 4, 'explanation' => 'Clinical note page 3'],
                            ['page_number' => 117, 'confidence' => 4, 'explanation' => 'Clinical note page 4'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: Should have 2 groups
        $this->assertCount(2, $result, 'Should have ME Physical Therapy and Mountain View Pain Specialists');

        $mePT         = collect($result)->firstWhere('name', 'ME Physical Therapy');
        $mountainView = collect($result)->firstWhere('name', 'Mountain View Pain Specialists');

        $this->assertNotNull($mePT, 'ME Physical Therapy should exist');
        $this->assertNotNull($mountainView, 'Mountain View Pain Specialists should exist');

        // ME PT should have pages 1-5 (prior group) and 105-112 (absorbed Ivo DPT via page 109)
        $this->assertEquals(
            [1, 2, 3, 4, 5, 105, 106, 107, 108, 109, 110, 111, 112],
            $mePT['files'],
            'ME Physical Therapy should have pages 1-5 and 105-112'
        );

        // Mountain View MUST have pages 113-117
        $this->assertEquals(
            [113, 114, 115, 116, 117],
            $mountainView['files'],
            'CRITICAL BUG: Mountain View MUST keep page 113 (confidence 5 = Billing Provider absolute authority)!'
        );

        // CRITICAL: Verify page 113 is in Mountain View
        $page113Assignment = null;
        foreach ($result as $group) {
            if (in_array(113, $group['files'])) {
                $page113Assignment = $group['name'];
                break;
            }
        }

        $this->assertEquals(
            'Mountain View Pain Specialists',
            $page113Assignment,
            'CRITICAL BUG: Page 113 (confidence 5) was absorbed into wrong group!'
        );
    }

    #[Test]
    public function production_bug_page_73_scenario_mixed_confidence_mountain_view(): void
    {
        // This reproduces the EXACT bug from production logs where Mountain View has MIXED confidence:
        //
        // From logs:
        // - ME Physical Therapy: min=4, max=5 (high confidence group)
        // - Mountain View Pain Specialists: min=2, max=5 (NOT high confidence due to min=2!)
        //
        // Window 1 (pages 69-73): "ME Physical Therapy" with all confidence 5
        // Window 2 (pages 73-76): "Mountain View Pain Specialists" with page 73 conf 4, pages 74-76 conf 4
        // PLUS other windows gave Mountain View some files with confidence 2 (making group min=2)
        //
        // What happened:
        // - Page 73 correctly stayed in ME Physical Therapy (conf 5 > 4)
        // - BUT pages 74-76 were NOT absorbed into ME Physical Therapy
        // - They should have been absorbed because:
        //   1. They were grouped WITH page 73 in Window 2
        //   2. Page 73 lost the conflict (stayed in ME PT)
        //   3. Mountain View group has max=5, ME PT has max=5, so equal - NO absorption!
        //
        // THIS IS THE BUG: When groups have EQUAL max confidence, we should still check
        // the CONFLICT BOUNDARY - page 73 had conf 4 in Mountain View but conf 5 in ME PT
        //
        // Expected: ME Physical Therapy should absorb pages 74-76 from the Mountain View window
        // Actual: Mountain View kept pages 74-76 (WRONG!)

        $artifacts = new Collection([
            // Window 1: Pages 69-73 in "ME Physical Therapy" (all confidence 5)
            $this->createWindowArtifact(
                windowStart: 69,
                windowEnd: 73,
                windowFiles: [
                    ['file_id' => 69, 'page_number' => 69],
                    ['file_id' => 70, 'page_number' => 70],
                    ['file_id' => 71, 'page_number' => 71],
                    ['file_id' => 72, 'page_number' => 72],
                    ['file_id' => 73, 'page_number' => 73],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'Physical therapy records',
                        'files'       => [
                            ['page_number' => 69, 'confidence' => 5, 'explanation' => 'Clear ME PT identifier'],
                            ['page_number' => 70, 'confidence' => 5, 'explanation' => 'Clear ME PT identifier'],
                            ['page_number' => 71, 'confidence' => 5, 'explanation' => 'Clear ME PT identifier'],
                            ['page_number' => 72, 'confidence' => 5, 'explanation' => 'Clear ME PT identifier'],
                            ['page_number' => 73, 'confidence' => 5, 'explanation' => 'Clear ME PT identifier'],
                        ],
                    ],
                ]
            ),
            // Window 2: Pages 73-76 in "Mountain View Pain Specialists" (all confidence 4)
            // Page 73 overlaps with Window 1 - this creates the conflict boundary
            $this->createWindowArtifact(
                windowStart: 73,
                windowEnd: 76,
                windowFiles: [
                    ['file_id' => 73, 'page_number' => 73],
                    ['file_id' => 74, 'page_number' => 74],
                    ['file_id' => 75, 'page_number' => 75],
                    ['file_id' => 76, 'page_number' => 76],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'Pain management records',
                        'files'       => [
                            ['page_number' => 73, 'confidence' => 4, 'explanation' => 'Possible Mountain View'],
                            ['page_number' => 74, 'confidence' => 4, 'explanation' => 'Possible Mountain View'],
                            ['page_number' => 75, 'confidence' => 4, 'explanation' => 'Possible Mountain View'],
                            ['page_number' => 76, 'confidence' => 4, 'explanation' => 'Possible Mountain View'],
                        ],
                    ],
                ]
            ),
            // Window 3: Pages 137-140 in "Mountain View Pain Specialists" (confidence 2!)
            // This makes Mountain View have min=2, even though it also has files with conf 5 elsewhere
            $this->createWindowArtifact(
                windowStart: 137,
                windowEnd: 140,
                windowFiles: [
                    ['file_id' => 137, 'page_number' => 137],
                    ['file_id' => 138, 'page_number' => 138],
                    ['file_id' => 139, 'page_number' => 139],
                    ['file_id' => 140, 'page_number' => 140],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'Uncertain pages',
                        'files'       => [
                            ['page_number' => 137, 'confidence' => 2, 'explanation' => 'Uncertain if Mountain View'],
                            ['page_number' => 138, 'confidence' => 2, 'explanation' => 'Uncertain if Mountain View'],
                            ['page_number' => 139, 'confidence' => 2, 'explanation' => 'Uncertain if Mountain View'],
                            ['page_number' => 140, 'confidence' => 2, 'explanation' => 'Uncertain if Mountain View'],
                        ],
                    ],
                ]
            ),
            // Window 4: Some files with conf 5 for Mountain View (makes max=5)
            $this->createWindowArtifact(
                windowStart: 141,
                windowEnd: 144,
                windowFiles: [
                    ['file_id' => 141, 'page_number' => 141],
                    ['file_id' => 142, 'page_number' => 142],
                    ['file_id' => 143, 'page_number' => 143],
                    ['file_id' => 144, 'page_number' => 144],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'Clear Mountain View',
                        'files'       => [
                            ['page_number' => 141, 'confidence' => 5, 'explanation' => 'Clear Mountain View'],
                            ['page_number' => 142, 'confidence' => 5, 'explanation' => 'Clear Mountain View'],
                            ['page_number' => 143, 'confidence' => 5, 'explanation' => 'Clear Mountain View'],
                            ['page_number' => 144, 'confidence' => 5, 'explanation' => 'Clear Mountain View'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $result      = $mergeResult['groups'];

        // Then: ME Physical Therapy should absorb ALL of Mountain View
        // Even though both groups have max=5, the CONFLICT BOUNDARY shows ME PT won page 73
        // with conf 5 vs Mountain View's conf 4, proving ME PT is the winner
        // When a group loses at ANY boundary, the ENTIRE group gets absorbed
        $this->assertCount(1, $result, 'Should have only ME Physical Therapy after absorbing ALL of Mountain View');

        $mePT = collect($result)->firstWhere('name', 'ME Physical Therapy');

        $this->assertNotNull($mePT, 'ME Physical Therapy should exist');

        $this->assertEquals(
            [69, 70, 71, 72, 73, 74, 75, 76, 137, 138, 139, 140, 141, 142, 143, 144],
            $mePT['files'],
            'ME Physical Therapy should absorb ALL of Mountain View because page 73 conflict boundary shows ME PT (file conf 5) > Mountain View (file conf 4), proving ME PT wins even though groups have equal max'
        );
    }
}
