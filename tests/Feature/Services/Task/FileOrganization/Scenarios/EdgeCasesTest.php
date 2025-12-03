<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests edge cases and boundary conditions in the file organization algorithm.
 *
 * These tests cover unusual scenarios, extreme inputs, and corner cases that
 * might not be covered by standard functional tests:
 * - Last file in document with various confidence/adjacency combinations
 * - Single file documents
 * - Very small documents (2 files)
 * - All files with zero confidence
 * - Very long documents (many windows)
 * - Alternating confidence patterns
 * - Extreme string lengths and special characters
 * - Confidence exactly at threshold boundaries
 * - Null/missing adjacency values in unexpected positions
 *
 * Based on Edge Cases 3 and 5 from breezy-finding-wind.md (lines 770-838).
 */
class EdgeCasesTest extends AuthenticatedTestCase
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
    public function last_file_with_low_confidence_and_high_adjacency_uses_previous_group(): void
    {
        // Given: Last file (10) has low confidence (2) but high adjacency (4) to previous
        // Based on Edge Case 5: "Use adjacency to previous - there's no next to consider"
        //
        // Files 1-9: Normal grouping in "Acme Corp"
        // File 10: Low confidence (2), high adjacency (4) to file 9
        //
        // Expected: File 10 joins file 9's group based on adjacency
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(4, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(5, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(6, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(7, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(8, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(9, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(10, 'Acme Corp', 2, 4, 'Consistent style', 'Last page - header unclear but same format'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: File 10 should join Acme Corp based on high adjacency to file 9
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(10, 'Acme Corp', $result['file_to_group_mapping']);
    }

    #[Test]
    public function last_file_with_low_confidence_and_low_adjacency_stays_in_assigned_group(): void
    {
        // Given: Last file (10) has both low confidence (2) AND low adjacency (1)
        // Based on Edge Case 5: "Use adjacency to previous - there's no next to consider"
        //
        // Files 1-9: "Acme Corp"
        // File 10: Low confidence (2), low adjacency (1) - boundary indicator
        //
        // Expected: File 10 stays in its assigned group "Beta Inc" (no "next" to reassign to)
        // The low adjacency indicates a boundary, so file 10 is NOT pulled into Acme Corp
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(4, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(5, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(6, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(7, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(8, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(9, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(10, 'Beta Inc', 2, 1, 'Very different style', 'Last page - looks like different group but unclear'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: File 10 stays in Beta Inc (its assigned group)
        // Low adjacency (1) indicates boundary with Acme Corp
        // Since it's the last file, there's no "next" group to reassign to
        // Therefore, file 10 remains in its originally assigned group "Beta Inc"
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4, 5, 6, 7, 8, 9]],
            ['name' => 'Beta Inc', 'files' => [10]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(9, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(10, 'Beta Inc', $result['file_to_group_mapping'], 'Last file with low adjacency should stay in assigned group (no next to reassign to)');
        $this->assertFileConfidence(10, 2, $result['file_to_group_mapping']);
    }

    #[Test]
    public function single_file_document_creates_single_group(): void
    {
        // Given: A document with just one file
        // Edge case: Minimal document size
        //
        // Expected: Single group with one file
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 1,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Single page document'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with single file
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
    }

    #[Test]
    public function two_files_different_groups_with_clear_boundary(): void
    {
        // Given: Minimal document with two files in different groups
        // Edge case: Smallest possible multi-group document
        //
        // File 1: "Group A" (conf 5)
        // File 2: "Group B" (conf 5), belongs_prev: 0 (explicit boundary)
        //
        // Expected: Two separate groups
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 2,
                'files' => [
                    $this->makeFileEntry(1, 'Group A', 5, null, null, 'First group'),
                    $this->makeFileEntry(2, 'Group B', 5, 0, 'Completely different', 'Second group'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Two separate groups
        $expectedGroups = [
            ['name' => 'Group A', 'files' => [1]],
            ['name' => 'Group B', 'files' => [2]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Group A', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Group B', $result['file_to_group_mapping']);
    }

    #[Test]
    public function all_files_with_zero_confidence_handles_gracefully(): void
    {
        // Given: Multiple files, all with group_name_confidence: 0
        // Edge case: No confidence in any assignments
        //
        // Expected behavior:
        // - All files have same group_name "Unknown Group"
        // - All have conf=0 (below threshold of 3)
        // - Files 2-5 have moderate adjacency (3) to previous
        // - Since all have same group_name and moderate adjacency, they should stay together
        // - Even with 0 confidence, the consistent group_name and adjacency signals
        //   indicate they belong in the same group
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Unknown Group', 0, null, null, 'No clear indicators'),
                    $this->makeFileEntry(2, 'Unknown Group', 0, 3, 'Somewhat similar', 'No confidence'),
                    $this->makeFileEntry(3, 'Unknown Group', 0, 3, 'Somewhat similar', 'No confidence'),
                    $this->makeFileEntry(4, 'Unknown Group', 0, 3, 'Somewhat similar', 'No confidence'),
                    $this->makeFileEntry(5, 'Unknown Group', 0, 3, 'Somewhat similar', 'No confidence'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files grouped together under "Unknown Group"
        // Despite zero confidence, consistent group_name + moderate adjacency = single group
        $expectedGroups = [
            ['name' => 'Unknown Group', 'files' => [1, 2, 3, 4, 5]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Unknown Group', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Unknown Group', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Unknown Group', $result['file_to_group_mapping']);
        $this->assertFileInGroup(4, 'Unknown Group', $result['file_to_group_mapping']);
        $this->assertFileInGroup(5, 'Unknown Group', $result['file_to_group_mapping']);

        // Verify confidence is preserved as 0
        $this->assertFileConfidence(1, 0, $result['file_to_group_mapping']);
        $this->assertFileConfidence(2, 0, $result['file_to_group_mapping']);
    }

    #[Test]
    public function very_long_document_with_many_windows_maintains_correct_grouping(): void
    {
        // Given: 50+ files across many overlapping windows
        // Edge case: Stress test for window processing and merging
        //
        // Pattern: 20 files "Acme", 15 files "Beta", 15 files "Gamma"
        //
        // Expected: Three distinct groups maintained throughout
        $files = [];

        // First 20 files: Acme Corp
        for ($i = 1; $i <= 20; $i++) {
            $files[] = $this->makeFileEntry(
                $i,
                'Acme Corp',
                5,
                $i === 1 ? null : 5,
                $i === 1 ? null : 'Consistent Acme style',
                'Acme page ' . $i
            );
        }

        // Next 15 files: Beta Inc
        for ($i = 21; $i <= 35; $i++) {
            $files[] = $this->makeFileEntry(
                $i,
                'Beta Inc',
                5,
                $i === 21 ? 1 : 5, // First Beta file has low adjacency
                $i === 21 ? 'Style change from Acme' : 'Consistent Beta style',
                'Beta page ' . $i
            );
        }

        // Last 15 files: Gamma LLC
        for ($i = 36; $i <= 50; $i++) {
            $files[] = $this->makeFileEntry(
                $i,
                'Gamma LLC',
                5,
                $i === 36 ? 1 : 5, // First Gamma file has low adjacency
                $i === 36 ? 'Style change from Beta' : 'Consistent Gamma style',
                'Gamma page ' . $i
            );
        }

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 50,
                'files' => $files,
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Three distinct groups with correct file counts
        $this->assertCount(3, $result['groups']);

        // Verify key boundary files
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(20, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(21, 'Beta Inc', $result['file_to_group_mapping']);
        $this->assertFileInGroup(35, 'Beta Inc', $result['file_to_group_mapping']);
        $this->assertFileInGroup(36, 'Gamma LLC', $result['file_to_group_mapping']);
        $this->assertFileInGroup(50, 'Gamma LLC', $result['file_to_group_mapping']);
    }

    #[Test]
    public function alternating_high_low_confidence_same_group_uses_adjacency_to_unify(): void
    {
        // Given: Files alternating between high and low confidence, all same group name
        // Edge case: Confidence oscillation pattern
        //
        // File 1: conf 5
        // File 2: conf 1
        // File 3: conf 5
        // File 4: conf 1
        // All "Acme Corp", high adjacency throughout
        //
        // Expected: All in same group (adjacency helps low-conf files)
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong header'),
                    $this->makeFileEntry(2, 'Acme Corp', 1, 5, 'Same style', 'Header unclear'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same style', 'Strong header'),
                    $this->makeFileEntry(4, 'Acme Corp', 1, 5, 'Same style', 'Header unclear'),
                    $this->makeFileEntry(5, 'Acme Corp', 5, 5, 'Same style', 'Strong header'),
                    $this->makeFileEntry(6, 'Acme Corp', 1, 5, 'Same style', 'Header unclear'),
                    $this->makeFileEntry(7, 'Acme Corp', 5, 5, 'Same style', 'Strong header'),
                    $this->makeFileEntry(8, 'Acme Corp', 1, 5, 'Same style', 'Header unclear'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files in same group despite alternating confidence
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4, 5, 6, 7, 8]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);

        // Verify alternating confidence pattern is preserved
        $this->assertFileConfidence(1, 5, $result['file_to_group_mapping']);
        $this->assertFileConfidence(2, 1, $result['file_to_group_mapping']);
        $this->assertFileConfidence(3, 5, $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 1, $result['file_to_group_mapping']);
    }

    #[Test]
    public function group_name_with_very_long_string_handles_correctly(): void
    {
        // Given: Group name with 500+ characters
        // Edge case: Extreme string length
        //
        // Expected: Handled correctly without truncation issues
        $longGroupName = str_repeat('Very Long Corporation Name With Many Words ', 15); // ~660 chars

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, $longGroupName, 5, null, null, 'First page'),
                    $this->makeFileEntry(2, $longGroupName, 5, 5, 'Same letterhead', 'Second page'),
                    $this->makeFileEntry(3, $longGroupName, 5, 5, 'Same letterhead', 'Third page'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files grouped correctly with long name
        $this->assertCount(1, $result['groups']);
        $this->assertEquals($longGroupName, $result['groups'][0]['name']);
        $this->assertFileInGroup(1, $longGroupName, $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, $longGroupName, $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, $longGroupName, $result['file_to_group_mapping']);
    }

    #[Test]
    public function group_name_with_special_characters_and_unicode_handles_correctly(): void
    {
        // Given: Group name with Unicode, emojis, special chars
        // Edge case: Character encoding and special characters
        //
        // Expected: Handled correctly
        $specialGroupName = 'Société Française™ & Co. 日本 🏢 <Special> "Quotes" \'Apostrophe\'';

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, $specialGroupName, 5, null, null, 'First page'),
                    $this->makeFileEntry(2, $specialGroupName, 5, 5, 'Same letterhead', 'Second page'),
                    $this->makeFileEntry(3, $specialGroupName, 5, 5, 'Same letterhead', 'Third page'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files grouped correctly with special characters preserved
        $this->assertCount(1, $result['groups']);
        $this->assertEquals($specialGroupName, $result['groups'][0]['name']);
        $this->assertFileInGroup(1, $specialGroupName, $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, $specialGroupName, $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, $specialGroupName, $result['file_to_group_mapping']);
    }

    #[Test]
    public function confidence_exactly_at_threshold_is_eligible_for_reassignment(): void
    {
        // Given: File with group_conf = 3 (exactly at threshold 3)
        // Edge case: Boundary test for > threshold
        // From task config: group_confidence_threshold: 3
        //
        // Rule from plan (lines 798-804):
        // - If file.group_conf > threshold → NOT eligible for reassignment
        // - If file.group_conf <= threshold → eligible for reassignment
        //
        // Test scenario:
        // File 1: "Acme" (conf 5) - Strong group assignment
        // File 2: "Acme" (conf 3), belongs_prev: 1 - AT threshold with LOW adjacency to file 1
        // File 3: "Beta" (conf 5), belongs_prev: 5 - HIGH adjacency (claims file 2)
        //
        // Expected behavior:
        // - File 2 has conf=3 which is AT threshold (not above)
        // - File 2 has low adjacency (1) to file 1
        // - File 3 has high adjacency (5) claiming file 2
        // - File 2 should be REASSIGNED to "Beta Inc" because conf <= threshold
        // - Files at or below threshold are eligible for reassignment based on adjacency
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 3, 1, 'Low similarity to previous', 'Exactly at threshold confidence'),
                    $this->makeFileEntry(3, 'Beta Inc', 5, 5, 'High similarity to file 2', 'Strong Beta header'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: File 2 should be reassigned to Beta Inc due to high adjacency from file 3
        // Confidence at threshold (3 <= 3) allows reassignment based on adjacency
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1]],
            ['name' => 'Beta Inc', 'files' => [2, 3]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Beta Inc', $result['file_to_group_mapping'], 'File with conf=3 (at threshold) should be reassigned based on high adjacency from next file');
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping']);
        $this->assertFileConfidence(2, 3, $result['file_to_group_mapping']);
    }

    #[Test]
    public function file_with_null_belongs_to_previous_in_middle_of_window_handles_gracefully(): void
    {
        // Given: Middle file has null adjacency (shouldn't happen normally but test robustness)
        // Edge case: Malformed or missing data
        //
        // File 1: "Acme" (conf 5), belongs_prev: null (expected)
        // File 2: "Acme" (conf 5), belongs_prev: 5 (normal)
        // File 3: "Acme" (conf 5), belongs_prev: null (unexpected!)
        // File 4: "Acme" (conf 5), belongs_prev: 5 (normal)
        //
        // Expected: Graceful handling, files still grouped correctly
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'First file'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Second file'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, null, null, 'Third file - null adjacency'),
                    $this->makeFileEntry(4, 'Acme Corp', 5, 5, 'Same letterhead', 'Fourth file'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files should still be grouped together despite null adjacency in middle
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping']);
    }

    #[Test]
    public function confidence_below_threshold_with_no_adjacent_files_stays_in_assigned_group(): void
    {
        // Given: File with confidence below threshold (2 < 3) but isolated (no next file)
        // Edge case: Similar to last file test but more explicit about threshold
        // Based on Edge Case 5 from plan (lines 833-837)
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 5)
        // File 3: "Beta" (conf 2), belongs_prev: 1 - Below threshold, low adjacency, but last
        //
        // Expected behavior:
        // - File 3 has conf=2 (< threshold 3) so it IS eligible for reassignment
        // - File 3 has low adjacency (1) to file 2, indicating boundary
        // - BUT file 3 is the LAST file, so there's no "next" to reassign to
        // - Therefore, file 3 stays in its assigned group "Beta Inc"
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Beta Inc', 2, 1, 'Different style', 'Weak Beta identification'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: File 3 stays in Beta Inc (its assigned group)
        // Even though conf < threshold and low adjacency, it's the last file
        // so there's no "next" group to reassign to
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
            ['name' => 'Beta Inc', 'files' => [3]],
        ];

        $this->assertGroupsMatch($expectedGroups, $result['groups']);
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping'], 'Last file stays in assigned group even with low conf and low adjacency (no next to reassign to)');
        $this->assertFileConfidence(3, 2, $result['file_to_group_mapping']);
    }

    #[Test]
    public function empty_group_name_blank_pages_handle_correctly_in_edge_positions(): void
    {
        // Given: Blank pages (empty group name) in edge positions (first, last)
        // Edge case: Blank page handling at document boundaries
        //
        // File 1: '' (blank, conf 1) - First file is blank
        // File 2: "Acme" (conf 5)
        // File 3: "Acme" (conf 5)
        // File 4: '' (blank, conf 1) - Last file is blank
        //
        // Expected: Blank pages handled according to blank_page_handling config ('join_previous')
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeBlankFileEntry(1, null), // First file blank
                    $this->makeFileEntry(2, 'Acme Corp', 5, 3, 'Some similarity', 'Strong Acme header'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeBlankFileEntry(4, 5), // Last file blank
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: All files should be assigned
        $this->assertArrayHasKey(1, $result['file_to_group_mapping']);
        $this->assertArrayHasKey(2, $result['file_to_group_mapping']);
        $this->assertArrayHasKey(3, $result['file_to_group_mapping']);
        $this->assertArrayHasKey(4, $result['file_to_group_mapping']);

        // Acme files should be together
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping']);
    }
}
