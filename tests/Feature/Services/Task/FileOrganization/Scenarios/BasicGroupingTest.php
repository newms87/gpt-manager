<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests basic/happy path scenarios for the FileOrganization algorithm.
 *
 * These tests verify that the algorithm correctly groups files in straightforward cases:
 * - Single groups with high confidence
 * - Multiple distinct groups with clear boundaries
 * - Sequential groups without overlaps
 * - Single-file groups
 * - Unified groups across multiple windows
 */
class BasicGroupingTest extends AuthenticatedTestCase
{
    use FileOrganizationTestHelpers;
    use SetUpTeamTrait;

    private FileOrganizationMergeService $mergeService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->setUpFileOrganization();
        $this->mergeService = app(FileOrganizationMergeService::class);
    }

    #[Test]
    public function single_group_all_files_high_confidence(): void
    {
        // Given: 5 pages all belonging to same group with high confidence
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Header visible'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(4, 'Acme Corp', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(5, 'Acme Corp', 5, 5, 'Same letterhead', 'Continuation'),
                ],
            ],
        ]);

        // When: Merge service processes the windows
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with all 5 files
        $this->assertGroupsMatch([
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4, 5]],
        ], $result['groups']);

        // Verify all files have high confidence
        foreach ([1, 2, 3, 4, 5] as $pageNumber) {
            $this->assertFileConfidence($pageNumber, 5, $result['file_to_group_mapping']);
        }
    }

    #[Test]
    public function two_distinct_groups_with_clear_boundary(): void
    {
        // Given: Pages 1-3 belong to Acme Corp, pages 4-6 belong to Beta Inc
        // Page 4 has belongs_to_previous: 0 indicating a clear boundary
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    // Group 1: Acme Corp (pages 1-3)
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme continuation'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme continuation'),
                    // Group 2: Beta Inc (pages 4-6)
                    $this->makeFileEntry(4, 'Beta Inc', 5, 0, 'Different header', 'Beta header'),
                    $this->makeFileEntry(5, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta continuation'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta continuation'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Two distinct groups
        $this->assertGroupsMatch([
            ['name' => 'Acme Corp', 'files' => [1, 2, 3]],
            ['name' => 'Beta Inc', 'files' => [4, 5, 6]],
        ], $result['groups']);

        // Verify boundary files
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(4, 'Beta Inc', $result['file_to_group_mapping']);
    }

    #[Test]
    public function three_groups_in_sequence(): void
    {
        // Given: Three distinct groups with clear boundaries between each
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    // Group A (pages 1-2)
                    $this->makeFileEntry(1, 'Group A', 5, null, null, 'Group A header'),
                    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same header', 'Group A continuation'),
                    // Group B (pages 3-4)
                    $this->makeFileEntry(3, 'Group B', 5, 0, 'Different header', 'Group B header'),
                    $this->makeFileEntry(4, 'Group B', 5, 5, 'Same header', 'Group B continuation'),
                    // Group C (pages 5-6)
                    $this->makeFileEntry(5, 'Group C', 5, 0, 'Different header', 'Group C header'),
                    $this->makeFileEntry(6, 'Group C', 5, 5, 'Same header', 'Group C continuation'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Three groups with correct file assignments
        $this->assertGroupsMatch([
            ['name' => 'Group A', 'files' => [1, 2]],
            ['name' => 'Group B', 'files' => [3, 4]],
            ['name' => 'Group C', 'files' => [5, 6]],
        ], $result['groups']);

        // Verify each boundary
        $this->assertFileInGroup(2, 'Group A', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Group B', $result['file_to_group_mapping']);
        $this->assertFileInGroup(4, 'Group B', $result['file_to_group_mapping']);
        $this->assertFileInGroup(5, 'Group C', $result['file_to_group_mapping']);
    }

    #[Test]
    public function single_file_per_group(): void
    {
        // Given: 3 pages, each a different group with no adjacency
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Group X', 5, null, null, 'Group X document'),
                    $this->makeFileEntry(2, 'Group Y', 5, 0, 'Different document', 'Group Y document'),
                    $this->makeFileEntry(3, 'Group Z', 5, 0, 'Different document', 'Group Z document'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Three groups with 1 file each
        $this->assertGroupsMatch([
            ['name' => 'Group X', 'files' => [1]],
            ['name' => 'Group Y', 'files' => [2]],
            ['name' => 'Group Z', 'files' => [3]],
        ], $result['groups']);

        // Verify each file is in its own group
        $this->assertFileInGroup(1, 'Group X', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Group Y', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Group Z', $result['file_to_group_mapping']);
    }

    #[Test]
    public function all_files_same_group_across_multiple_windows(): void
    {
        // Given: 10 files processed in overlapping windows, all same group
        $artifacts = $this->createWindowArtifacts([
            // Window 1: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Unified Group', 5, null, null, 'Header visible'),
                    $this->makeFileEntry(2, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(3, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(4, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(5, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                ],
            ],
            // Window 2: Pages 4-8 (overlaps with window 1)
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(5, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(6, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(7, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(8, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                ],
            ],
            // Window 3: Pages 7-10 (overlaps with window 2)
            [
                'start' => 7,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(7, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(8, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(9, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                    $this->makeFileEntry(10, 'Unified Group', 5, 5, 'Same letterhead', 'Continuation'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with all 10 files
        $this->assertGroupsMatch([
            ['name' => 'Unified Group', 'files' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
        ], $result['groups']);

        // Verify all files in the unified group
        foreach (range(1, 10) as $pageNumber) {
            $this->assertFileInGroup($pageNumber, 'Unified Group', $result['file_to_group_mapping']);
            $this->assertFileConfidence($pageNumber, 5, $result['file_to_group_mapping']);
        }
    }

    #[Test]
    public function overlapping_windows_with_consistent_grouping(): void
    {
        // Given: Windows overlap but all agree on the grouping
        // Window 1: [1,2,3,4,5] - Group A: 1-3, Group B: 4-5
        // Window 2: [4,5,6,7,8] - Group B: 4-6, Group C: 7-8
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Group A', 5, null, null, 'Group A header'),
                    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same header', 'Group A continuation'),
                    $this->makeFileEntry(3, 'Group A', 5, 5, 'Same header', 'Group A continuation'),
                    $this->makeFileEntry(4, 'Group B', 5, 0, 'Different header', 'Group B header'),
                    $this->makeFileEntry(5, 'Group B', 5, 5, 'Same header', 'Group B continuation'),
                ],
            ],
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Group B', 5, null, null, 'Group B header'),
                    $this->makeFileEntry(5, 'Group B', 5, 5, 'Same header', 'Group B continuation'),
                    $this->makeFileEntry(6, 'Group B', 5, 5, 'Same header', 'Group B continuation'),
                    $this->makeFileEntry(7, 'Group C', 5, 0, 'Different header', 'Group C header'),
                    $this->makeFileEntry(8, 'Group C', 5, 5, 'Same header', 'Group C continuation'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Three groups with correct boundaries
        $this->assertGroupsMatch([
            ['name' => 'Group A', 'files' => [1, 2, 3]],
            ['name' => 'Group B', 'files' => [4, 5, 6]],
            ['name' => 'Group C', 'files' => [7, 8]],
        ], $result['groups']);
    }

    #[Test]
    public function large_group_with_multiple_windows(): void
    {
        // Given: A larger document (15 pages) split across multiple windows
        // All pages belong to the same group
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Large Document', 5, null, null, 'Document start'),
                    $this->makeFileEntry(2, 'Large Document', 5, 5, 'Continuation', 'Page 2'),
                    $this->makeFileEntry(3, 'Large Document', 5, 5, 'Continuation', 'Page 3'),
                    $this->makeFileEntry(4, 'Large Document', 5, 5, 'Continuation', 'Page 4'),
                    $this->makeFileEntry(5, 'Large Document', 5, 5, 'Continuation', 'Page 5'),
                ],
            ],
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Large Document', 5, 5, 'Continuation', 'Page 4'),
                    $this->makeFileEntry(5, 'Large Document', 5, 5, 'Continuation', 'Page 5'),
                    $this->makeFileEntry(6, 'Large Document', 5, 5, 'Continuation', 'Page 6'),
                    $this->makeFileEntry(7, 'Large Document', 5, 5, 'Continuation', 'Page 7'),
                    $this->makeFileEntry(8, 'Large Document', 5, 5, 'Continuation', 'Page 8'),
                ],
            ],
            [
                'start' => 7,
                'end'   => 11,
                'files' => [
                    $this->makeFileEntry(7, 'Large Document', 5, 5, 'Continuation', 'Page 7'),
                    $this->makeFileEntry(8, 'Large Document', 5, 5, 'Continuation', 'Page 8'),
                    $this->makeFileEntry(9, 'Large Document', 5, 5, 'Continuation', 'Page 9'),
                    $this->makeFileEntry(10, 'Large Document', 5, 5, 'Continuation', 'Page 10'),
                    $this->makeFileEntry(11, 'Large Document', 5, 5, 'Continuation', 'Page 11'),
                ],
            ],
            [
                'start' => 10,
                'end'   => 15,
                'files' => [
                    $this->makeFileEntry(10, 'Large Document', 5, 5, 'Continuation', 'Page 10'),
                    $this->makeFileEntry(11, 'Large Document', 5, 5, 'Continuation', 'Page 11'),
                    $this->makeFileEntry(12, 'Large Document', 5, 5, 'Continuation', 'Page 12'),
                    $this->makeFileEntry(13, 'Large Document', 5, 5, 'Continuation', 'Page 13'),
                    $this->makeFileEntry(14, 'Large Document', 5, 5, 'Continuation', 'Page 14'),
                    $this->makeFileEntry(15, 'Large Document', 5, 5, 'Continuation', 'Page 15'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with all 15 files
        $this->assertGroupsMatch([
            ['name' => 'Large Document', 'files' => range(1, 15)],
        ], $result['groups']);

        // Verify all files maintain high confidence
        foreach (range(1, 15) as $pageNumber) {
            $this->assertFileConfidence($pageNumber, 5, $result['file_to_group_mapping']);
        }
    }

    #[Test]
    public function empty_windows_produce_empty_groups(): void
    {
        // Given: Empty collection of artifacts
        $artifacts = collect([]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: No groups, no file mappings
        $this->assertCount(0, $result['groups']);
        $this->assertCount(0, $result['file_to_group_mapping']);
    }

    #[Test]
    public function high_confidence_files_with_clear_boundaries(): void
    {
        // Given: Multiple groups with high confidence and explicit boundary markers
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 8,
                'files' => [
                    // Alpha Company (pages 1-2)
                    $this->makeFileEntry(1, 'Alpha Company', 5, null, null, 'Alpha header'),
                    $this->makeFileEntry(2, 'Alpha Company', 5, 5, 'Same letterhead', 'Alpha page 2'),
                    // Beta Corporation (pages 3-5)
                    $this->makeFileEntry(3, 'Beta Corporation', 5, 0, 'Clear new document', 'Beta header'),
                    $this->makeFileEntry(4, 'Beta Corporation', 5, 5, 'Same letterhead', 'Beta page 2'),
                    $this->makeFileEntry(5, 'Beta Corporation', 5, 5, 'Same letterhead', 'Beta page 3'),
                    // Gamma Industries (pages 6-8)
                    $this->makeFileEntry(6, 'Gamma Industries', 5, 0, 'Clear new document', 'Gamma header'),
                    $this->makeFileEntry(7, 'Gamma Industries', 5, 5, 'Same letterhead', 'Gamma page 2'),
                    $this->makeFileEntry(8, 'Gamma Industries', 5, 5, 'Same letterhead', 'Gamma page 3'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Three distinct groups
        $this->assertGroupsMatch([
            ['name' => 'Alpha Company', 'files' => [1, 2]],
            ['name' => 'Beta Corporation', 'files' => [3, 4, 5]],
            ['name' => 'Gamma Industries', 'files' => [6, 7, 8]],
        ], $result['groups']);

        // Verify all files have maximum confidence
        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $pageNumber) {
            $this->assertFileConfidence($pageNumber, 5, $result['file_to_group_mapping']);
        }
    }
}
