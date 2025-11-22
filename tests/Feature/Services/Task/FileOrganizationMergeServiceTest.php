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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                ],
                groups: [
                    ['name' => 'group1', 'description' => 'First group', 'files' => [0, 1]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'group2', 'description' => 'Second group', 'files' => [2, 3]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [0, 1, 2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [1, 2, 3]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: Single group with all files in order
        $this->assertCount(1, $result);
        $this->assertEquals('groupA', $result[0]['name']);
        $this->assertEquals([1, 2, 3, 4], $result[0]['files']);
    }

    #[Test]
    public function merges_overlapping_windows_with_conflicts_later_wins(): void
    {
        // Given: Two windows that disagree on file grouping - later should override
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                ],
                groups: [
                    ['name' => 'group1', 'description' => 'First group', 'files' => [0, 1, 2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'group2', 'description' => 'Second group', 'files' => [1, 2, 3]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: File 1 stays in group1, files 2-4 move to group2 (later window wins)
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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                    ['file_id' => 5, 'position' => 4],
                ],
                groups: [
                    ['name' => 'ordered', 'description' => 'Ordered group', 'files' => [4, 1, 3, 0, 2]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                ],
                groups: [
                    ['name' => 'solo', 'description' => 'Solo group', 'files' => [0, 1, 2]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                ],
                groups: [
                    ['name' => 'empty', 'description' => 'Empty group', 'files' => []],
                    ['name' => 'valid', 'description' => 'Valid group', 'files' => [0]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: Only valid group with files
        $this->assertCount(1, $result);
        $this->assertEquals('valid', $result[0]['name']);
    }

    #[Test]
    public function handles_overlapping_files_in_different_groups(): void
    {
        // Given: Same file appears in different groups in different windows
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
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
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'second', 'description' => 'Second group', 'files' => [1, 2, 3]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: Later window reassigns file 2 to 'second' group
        $this->assertCount(2, $result);

        $first  = collect($result)->firstWhere('name', 'first');
        $second = collect($result)->firstWhere('name', 'second');

        $this->assertEquals([1], $first['files']);
        $this->assertEquals([2, 3, 4], $second['files']);
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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                    ['file_id' => 5, 'position' => 4],
                ],
                groups: [
                    ['name' => 'max', 'description' => 'Max size group', 'files' => [0, 1, 2, 3, 4]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                ],
                groups: [
                    ['name' => 'min', 'description' => 'Min size group', 'files' => [0, 1]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [0, 1, 2]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 3,
                windowFiles: [
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [1, 2, 3]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 2,
                windowEnd: 4,
                windowFiles: [
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                    ['file_id' => 5, 'position' => 4],
                ],
                groups: [
                    ['name' => 'continuous', 'description' => 'Continuous group', 'files' => [2, 3, 4]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: One continuous group with all files
        $this->assertCount(1, $result);
        $this->assertEquals('continuous', $result[0]['name']);
        $this->assertEquals([1, 2, 3, 4, 5], $result[0]['files']);
    }

    #[Test]
    public function handles_split_groups_file_breaks_continuity(): void
    {
        // Given: Windows that create a split in grouping
        $artifacts = new Collection([
            $this->createWindowArtifact(
                windowStart: 0,
                windowEnd: 1,
                windowFiles: [
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [0, 1]],
                ]
            ),
            $this->createWindowArtifact(
                windowStart: 1,
                windowEnd: 2,
                windowFiles: [
                    ['file_id' => 2, 'position' => 1],
                    ['file_id' => 3, 'position' => 2],
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
                    ['file_id' => 3, 'position' => 2],
                    ['file_id' => 4, 'position' => 3],
                ],
                groups: [
                    ['name' => 'groupA', 'description' => 'Group A', 'files' => [2, 3]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: Later windows override - all files end up in groupA
        $this->assertCount(1, $result);
        $this->assertEquals('groupA', $result[0]['name']);
        $this->assertEquals([1, 2, 3, 4], $result[0]['files']);
    }

    #[Test]
    public function returns_empty_array_for_empty_collection(): void
    {
        // Given: Empty collection
        $artifacts = new Collection([]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
                    ['file_id' => 1, 'position' => 0],
                    ['file_id' => 2, 'position' => 1],
                ],
                groups: [
                    ['name' => 'valid', 'description' => 'Valid group', 'files' => [0]],
                ]
            ),
        ]);

        // When
        $result = $this->service->mergeWindowResults($artifacts);

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
        $result = $this->service->mergeWindowResults($artifacts);

        // Then: Artifact skipped due to missing metadata
        $this->assertEmpty($result);
    }

    #[Test]
    public function creates_overlapping_windows_from_file_list(): void
    {
        // Given: List of 5 files with window size 3
        $files = [
            ['file_id' => 1, 'position' => 0],
            ['file_id' => 2, 'position' => 1],
            ['file_id' => 3, 'position' => 2],
            ['file_id' => 4, 'position' => 3],
            ['file_id' => 5, 'position' => 4],
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
            ['file_id' => 1, 'position' => 0],
            ['file_id' => 2, 'position' => 1],
            ['file_id' => 3, 'position' => 2],
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
            ['file_id' => 1, 'position' => 0],
            ['file_id' => 2, 'position' => 1],
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
        // Given: Artifacts with different positions
        $artifacts = new Collection([
            Artifact::factory()->create(['position' => 2]),
            Artifact::factory()->create(['position' => 0]),
            Artifact::factory()->create(['position' => 1]),
        ]);

        // When
        $files = $this->service->getFileListFromArtifacts($artifacts);

        // Then: Files ordered by position
        $this->assertCount(3, $files);
        $this->assertEquals(0, $files[0]['position']);
        $this->assertEquals(1, $files[1]['position']);
        $this->assertEquals(2, $files[2]['position']);
    }

    #[Test]
    public function gets_file_list_from_artifacts_uses_artifact_id_as_file_id(): void
    {
        // Given: Artifacts
        $artifact1 = Artifact::factory()->create(['position' => 0]);
        $artifact2 = Artifact::factory()->create(['position' => 1]);

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
}
