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
        $this->service = new FileOrganizationMergeService();
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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
                        'name' => 'group1',
                        'description' => 'First group',
                        'files' => [
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
                        'name' => 'group2',
                        'description' => 'Second group',
                        'files' => [
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
        $result = $mergeResult['groups'];

        // Then: File 1 stays in group1 (only one assignment), files 2-3 move to group2 (higher confidence wins)
        $this->assertCount(2, $result);

        // Find groups by name
        $group1 = collect($result)->firstWhere('name', 'group1');
        $group2 = collect($result)->firstWhere('name', 'group2');

        $this->assertEquals([1], $group1['files']);
        $this->assertEquals([2, 3, 4], $group2['files']);
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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
        $result = $mergeResult['groups'];

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
            $artifact = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
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
        $artifact1 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile1 = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'page_number' => 0,
            'filename'    => 'page-0.jpg',
            'filepath'    => 'test/page-0.jpg',
            'disk'        => 'public',
            'mime'        => 'image/jpeg',
        ]);
        $artifact1->storedFiles()->attach($storedFile1->id);

        $artifact2 = Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]);
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
        $groupA = $finalGroups[0];
        $this->assertContains(2, $groupA['files'], 'Low-confidence file with single assignment should still be included in final groups');
    }
}
