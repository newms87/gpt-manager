<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests window overlap and conflict resolution scenarios for FileOrganization.
 *
 * These tests verify that the algorithm correctly handles:
 * - Multiple windows contributing different/conflicting data for the same files
 * - Confidence-based conflict resolution (higher confidence wins)
 * - Tie-breaking when confidences are equal (first window wins)
 * - MAX aggregation of confidence scores across windows
 * - Consensus detection when multiple windows agree
 * - Partial overlap handling where windows provide different context
 */
class WindowOverlapTest extends AuthenticatedTestCase
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
    public function same_file_different_group_names_higher_confidence_wins(): void
    {
        // Given: Two windows disagree on group name for file 5
        // Window A says "Acme Corp" with confidence 3
        // Window B says "Beta Inc" with confidence 5
        $artifacts = $this->createWindowArtifacts([
            // Window A: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 4, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 4, 5, 'Same letterhead', 'Acme page 2'),
                    $this->makeFileEntry(3, 'Acme Corp', 4, 5, 'Same letterhead', 'Acme page 3'),
                    $this->makeFileEntry(4, 'Acme Corp', 4, 5, 'Same letterhead', 'Acme page 4'),
                    $this->makeFileEntry(5, 'Acme Corp', 3, 5, 'Possibly same', 'Acme page 5?'),
                ],
            ],
            // Window B: Pages 4-8
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Acme Corp', 4, null, null, 'Acme continuation'),
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Clear new header', 'Beta header visible'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 2'),
                    $this->makeFileEntry(7, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 3'),
                    $this->makeFileEntry(8, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 4'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: File 5 should be in "Beta Inc" group (higher confidence wins)
        // STRICT: Explicitly verify file 5 is in Beta Inc (NOT Acme Corp)
        // Rule: Same file, different group names → highest confidence wins
        // Window A: file 5 → "Acme Corp" (conf 3)
        // Window B: file 5 → "Beta Inc" (conf 5)
        // Winner: "Beta Inc" because 5 > 3
        $this->assertFileInGroup(5, 'Beta Inc', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']);

        // Verify group structure
        $this->assertGroupsMatch([
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4]],
            ['name' => 'Beta Inc', 'files' => [5, 6, 7, 8]],
        ], $result['groups']);
    }

    #[Test]
    public function same_file_same_confidence_earlier_window_wins(): void
    {
        // Given: Two windows disagree on group name for file 5
        // Both have confidence 4 - should prefer Window A (first seen)
        $artifacts = $this->createWindowArtifacts([
            // Window A: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme page 2'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme page 3'),
                    $this->makeFileEntry(4, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme page 4'),
                    $this->makeFileEntry(5, 'Acme Corp', 4, 5, 'Possibly same', 'Acme page 5'),
                ],
            ],
            // Window B: Pages 4-8
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Acme Corp', 5, null, null, 'Acme continuation'),
                    $this->makeFileEntry(5, 'Beta Inc', 4, 2, 'Uncertain boundary', 'Beta header?'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 2'),
                    $this->makeFileEntry(7, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 3'),
                    $this->makeFileEntry(8, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta page 4'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: File 5 should be in "Acme Corp" group (tie-breaking: first window wins)
        // STRICT: Explicitly verify file 5 is in Acme Corp (NOT Beta Inc)
        // Rule: Same file, same confidence → earlier window wins (first seen)
        // Window A: file 5 → "Acme Corp" (conf 4) - FIRST
        // Window B: file 5 → "Beta Inc" (conf 4) - SECOND
        // Winner: "Acme Corp" because Window A came first (tie-breaker)
        $this->assertFileInGroup(5, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 4, $result['file_to_group_mapping']);

        // Verify group structure
        $this->assertGroupsMatch([
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4, 5]],
            ['name' => 'Beta Inc', 'files' => [6, 7, 8]],
        ], $result['groups']);
    }

    #[Test]
    public function overlapping_windows_agree_on_grouping(): void
    {
        // Given: Windows A and B both say files 4-5 belong to "Same Group"
        // They agree on grouping - confidence should be combined (MAX)
        $artifacts = $this->createWindowArtifacts([
            // Window A: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Group A', 5, null, null, 'Group A header'),
                    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead', 'Group A page 2'),
                    $this->makeFileEntry(3, 'Group A', 5, 5, 'Same letterhead', 'Group A page 3'),
                    $this->makeFileEntry(4, 'Same Group', 4, 0, 'New header', 'Same Group header'),
                    $this->makeFileEntry(5, 'Same Group', 4, 5, 'Same letterhead', 'Same Group page 2'),
                ],
            ],
            // Window B: Pages 4-8
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Same Group', 5, null, null, 'Same Group header'),
                    $this->makeFileEntry(5, 'Same Group', 5, 5, 'Same letterhead', 'Same Group page 2'),
                    $this->makeFileEntry(6, 'Same Group', 5, 5, 'Same letterhead', 'Same Group page 3'),
                    $this->makeFileEntry(7, 'Same Group', 5, 5, 'Same letterhead', 'Same Group page 4'),
                    $this->makeFileEntry(8, 'Same Group', 5, 5, 'Same letterhead', 'Same Group page 5'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: Files 4-8 in "Same Group" with MAX confidence from both windows
        // Rule: When windows agree on group name, use MAX confidence aggregation
        $this->assertGroupsMatch([
            ['name' => 'Group A', 'files' => [1, 2, 3]],
            ['name' => 'Same Group', 'files' => [4, 5, 6, 7, 8]],
        ], $result['groups']);

        // STRICT: Verify MAX confidence aggregation across windows
        // File 4: Window A conf 4, Window B conf 5 → MAX = 5
        $this->assertFileInGroup(4, 'Same Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 5, $result['file_to_group_mapping']); // MAX(4, 5) = 5

        // File 5: Window A conf 4, Window B conf 5 → MAX = 5
        $this->assertFileInGroup(5, 'Same Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']); // MAX(4, 5) = 5
    }

    #[Test]
    public function windows_disagree_on_boundary_location_higher_confidence_wins(): void
    {
        // Given: Window A says boundary after file 4, Window B says boundary after file 5
        // Window B has higher confidence for the boundary marker
        $artifacts = $this->createWindowArtifacts([
            // Window A: Boundary after file 4
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Group X', 5, null, null, 'Group X header'),
                    $this->makeFileEntry(2, 'Group X', 5, 5, 'Same letterhead', 'Group X page 2'),
                    $this->makeFileEntry(3, 'Group X', 5, 5, 'Same letterhead', 'Group X page 3'),
                    $this->makeFileEntry(4, 'Group X', 5, 5, 'Same letterhead', 'Group X page 4'),
                    $this->makeFileEntry(5, 'Group Y', 3, 2, 'Maybe new?', 'Uncertain boundary'),
                    $this->makeFileEntry(6, 'Group Y', 4, 4, 'Probably same', 'Group Y page 2'),
                ],
            ],
            // Window B: Boundary after file 5
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Group X', 5, null, null, 'Group X continuation'),
                    $this->makeFileEntry(5, 'Group X', 5, 5, 'Same letterhead', 'Group X page 5'),
                    $this->makeFileEntry(6, 'Group Y', 5, 0, 'Clear new header', 'Group Y header'),
                    $this->makeFileEntry(7, 'Group Y', 5, 5, 'Same letterhead', 'Group Y page 2'),
                    $this->makeFileEntry(8, 'Group Y', 5, 5, 'Same letterhead', 'Group Y page 3'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: File 5 should be in Group X (Window B's higher confidence wins)
        // STRICT: Boundary disagreement resolved by higher confidence
        // Window A: file 5 → "Group Y" (conf 3)
        // Window B: file 5 → "Group X" (conf 5)
        // Winner: "Group X" because 5 > 3
        $this->assertFileInGroup(5, 'Group X', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']); // MAX(3, 5) = 5

        // Verify group structure
        $this->assertGroupsMatch([
            ['name' => 'Group X', 'files' => [1, 2, 3, 4, 5]],
            ['name' => 'Group Y', 'files' => [6, 7, 8]],
        ], $result['groups']);
    }

    #[Test]
    public function three_overlapping_windows_with_consensus(): void
    {
        // Given: Three windows overlap on files 5-7, all agree on grouping
        // Should build consensus and use highest confidence from any window
        $artifacts = $this->createWindowArtifacts([
            // Window A: Pages 1-6
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Initial Group', 5, null, null, 'Initial header'),
                    $this->makeFileEntry(2, 'Initial Group', 5, 5, 'Same letterhead', 'Initial page 2'),
                    $this->makeFileEntry(3, 'Initial Group', 5, 5, 'Same letterhead', 'Initial page 3'),
                    $this->makeFileEntry(4, 'Consensus Group', 4, 0, 'New header', 'Consensus header'),
                    $this->makeFileEntry(5, 'Consensus Group', 4, 5, 'Same letterhead', 'Consensus page 2'),
                    $this->makeFileEntry(6, 'Consensus Group', 4, 5, 'Same letterhead', 'Consensus page 3'),
                ],
            ],
            // Window B: Pages 4-9
            [
                'start' => 4,
                'end'   => 9,
                'files' => [
                    $this->makeFileEntry(4, 'Consensus Group', 5, null, null, 'Consensus header'),
                    $this->makeFileEntry(5, 'Consensus Group', 3, 5, 'Same letterhead', 'Consensus page 2'),
                    $this->makeFileEntry(6, 'Consensus Group', 5, 5, 'Same letterhead', 'Consensus page 3'),
                    $this->makeFileEntry(7, 'Consensus Group', 5, 5, 'Same letterhead', 'Consensus page 4'),
                    $this->makeFileEntry(8, 'Consensus Group', 5, 5, 'Same letterhead', 'Consensus page 5'),
                    $this->makeFileEntry(9, 'Consensus Group', 5, 5, 'Same letterhead', 'Consensus page 6'),
                ],
            ],
            // Window C: Pages 7-10
            [
                'start' => 7,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(7, 'Consensus Group', 4, null, null, 'Consensus continuation'),
                    $this->makeFileEntry(8, 'Consensus Group', 4, 5, 'Same letterhead', 'Consensus page 5'),
                    $this->makeFileEntry(9, 'Consensus Group', 4, 5, 'Same letterhead', 'Consensus page 6'),
                    $this->makeFileEntry(10, 'Consensus Group', 4, 5, 'Same letterhead', 'Consensus page 7'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: All files 4-10 in "Consensus Group" with MAX confidence
        // Rule: When 3+ windows agree on grouping, build consensus with MAX confidence
        $this->assertGroupsMatch([
            ['name' => 'Initial Group', 'files' => [1, 2, 3]],
            ['name' => 'Consensus Group', 'files' => [4, 5, 6, 7, 8, 9, 10]],
        ], $result['groups']);

        // STRICT: Verify MAX confidence aggregation across all 3 windows
        // File 4: Windows A(4), B(5) → MAX = 5
        $this->assertFileInGroup(4, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 5, $result['file_to_group_mapping']); // MAX(4, 5) = 5

        // File 5: Windows A(4), B(3) → MAX = 4
        $this->assertFileInGroup(5, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 4, $result['file_to_group_mapping']); // MAX(4, 3) = 4

        // File 6: Windows A(4), B(5) → MAX = 5
        $this->assertFileInGroup(6, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(6, 5, $result['file_to_group_mapping']); // MAX(4, 5) = 5

        // File 7: Windows B(5), C(4) → MAX = 5
        $this->assertFileInGroup(7, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(7, 5, $result['file_to_group_mapping']); // MAX(5, 4) = 5

        // File 8: Windows B(5), C(4) → MAX = 5
        $this->assertFileInGroup(8, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(8, 5, $result['file_to_group_mapping']); // MAX(5, 4) = 5

        // File 9: Windows B(5), C(4) → MAX = 5
        $this->assertFileInGroup(9, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(9, 5, $result['file_to_group_mapping']); // MAX(5, 4) = 5

        // File 10: Only Window C(4)
        $this->assertFileInGroup(10, 'Consensus Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(10, 4, $result['file_to_group_mapping']); // Only Window C
    }

    #[Test]
    public function partial_overlap_with_one_window_having_null_for_file(): void
    {
        // Given: Window A covers files 1-5 and gives group for all
        // Window B covers files 4-8, but file 4 is first in window (belongs_to_previous null)
        // Window A has more context for file 4 since it's not the first file there
        $artifacts = $this->createWindowArtifacts([
            // Window A: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Alpha Group', 5, null, null, 'Alpha header'),
                    $this->makeFileEntry(2, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 2'),
                    $this->makeFileEntry(3, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 3'),
                    $this->makeFileEntry(4, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 4'),
                    $this->makeFileEntry(5, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 5'),
                ],
            ],
            // Window B: Pages 4-8
            // File 4 is first in this window, so belongs_to_previous is null
            // But Window A already classified it with high confidence
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Alpha Group', 4, null, null, 'Alpha continuation'),
                    $this->makeFileEntry(5, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 5'),
                    $this->makeFileEntry(6, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 6'),
                    $this->makeFileEntry(7, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 7'),
                    $this->makeFileEntry(8, 'Alpha Group', 5, 5, 'Same letterhead', 'Alpha page 8'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: All files in Alpha Group - Window A's context for file 4 is preserved
        // Rule: Windows with null belongs_to_previous (first in window) still contribute group name and confidence
        $this->assertGroupsMatch([
            ['name' => 'Alpha Group', 'files' => [1, 2, 3, 4, 5, 6, 7, 8]],
        ], $result['groups']);

        // STRICT: Verify MAX confidence aggregation even when one window has null context
        // File 4: Window A conf 5, Window B conf 4 (null belongs_to_previous) → MAX = 5
        $this->assertFileInGroup(4, 'Alpha Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 5, $result['file_to_group_mapping']); // MAX(5, 4) = 5

        // File 5: Window A conf 5, Window B conf 5 → MAX = 5
        $this->assertFileInGroup(5, 'Alpha Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']); // MAX(5, 5) = 5
    }

    #[Test]
    public function max_confidence_aggregation_across_windows(): void
    {
        // Given: File 5 gets different confidences from different windows
        // Should use MAX confidence across all windows
        $artifacts = $this->createWindowArtifacts([
            // Window A: Low confidence for file 5
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Test Group', 5, null, null, 'Test header'),
                    $this->makeFileEntry(2, 'Test Group', 5, 5, 'Same letterhead', 'Test page 2'),
                    $this->makeFileEntry(3, 'Test Group', 5, 5, 'Same letterhead', 'Test page 3'),
                    $this->makeFileEntry(4, 'Test Group', 5, 5, 'Same letterhead', 'Test page 4'),
                    $this->makeFileEntry(5, 'Test Group', 2, 4, 'Somewhat similar', 'Test page 5'),
                    $this->makeFileEntry(6, 'Test Group', 4, 5, 'Same letterhead', 'Test page 6'),
                ],
            ],
            // Window B: Medium confidence for file 5
            [
                'start' => 4,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(4, 'Test Group', 5, null, null, 'Test continuation'),
                    $this->makeFileEntry(5, 'Test Group', 3, 5, 'Probably same', 'Test page 5'),
                    $this->makeFileEntry(6, 'Test Group', 5, 5, 'Same letterhead', 'Test page 6'),
                    $this->makeFileEntry(7, 'Test Group', 5, 5, 'Same letterhead', 'Test page 7'),
                    $this->makeFileEntry(8, 'Test Group', 5, 5, 'Same letterhead', 'Test page 8'),
                ],
            ],
            // Window C: High confidence for file 5
            [
                'start' => 5,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(5, 'Test Group', 5, null, null, 'Test continuation'),
                    $this->makeFileEntry(6, 'Test Group', 5, 5, 'Same letterhead', 'Test page 6'),
                    $this->makeFileEntry(7, 'Test Group', 5, 5, 'Same letterhead', 'Test page 7'),
                    $this->makeFileEntry(8, 'Test Group', 5, 5, 'Same letterhead', 'Test page 8'),
                    $this->makeFileEntry(9, 'Test Group', 5, 5, 'Same letterhead', 'Test page 9'),
                    $this->makeFileEntry(10, 'Test Group', 5, 5, 'Same letterhead', 'Test page 10'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: All files in Test Group with MAX confidence aggregation
        // Rule: Confidence is always MAX across all windows that see the file
        $this->assertGroupsMatch([
            ['name' => 'Test Group', 'files' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
        ], $result['groups']);

        // STRICT: Verify MAX confidence across 3 windows with varying confidence
        // File 5: Window A(2), Window B(3), Window C(5) → MAX = 5
        $this->assertFileInGroup(5, 'Test Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']); // MAX(2, 3, 5) = 5

        // File 6: Window A(4), Window B(5), Window C(5) → MAX = 5
        $this->assertFileInGroup(6, 'Test Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(6, 5, $result['file_to_group_mapping']); // MAX(4, 5, 5) = 5
    }

    #[Test]
    public function adjacency_votes_from_multiple_windows_use_max(): void
    {
        // Given: File 5's belongs_to_previous gets different scores from different windows
        // Window A says 3 (moderate adjacency)
        // Window B says 5 (strong adjacency)
        // Should use MAX for adjacency score
        $artifacts = $this->createWindowArtifacts([
            // Window A: Moderate adjacency for file 5
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Combined Group', 5, null, null, 'Combined header'),
                    $this->makeFileEntry(2, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 2'),
                    $this->makeFileEntry(3, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 3'),
                    $this->makeFileEntry(4, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 4'),
                    $this->makeFileEntry(5, 'Combined Group', 5, 3, 'Somewhat similar', 'Combined page 5'),
                    $this->makeFileEntry(6, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 6'),
                ],
            ],
            // Window B: Strong adjacency for file 5
            [
                'start' => 3,
                'end'   => 8,
                'files' => [
                    $this->makeFileEntry(3, 'Combined Group', 5, null, null, 'Combined continuation'),
                    $this->makeFileEntry(4, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 4'),
                    $this->makeFileEntry(5, 'Combined Group', 5, 5, 'Clearly same', 'Combined page 5'),
                    $this->makeFileEntry(6, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 6'),
                    $this->makeFileEntry(7, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 7'),
                    $this->makeFileEntry(8, 'Combined Group', 5, 5, 'Same letterhead', 'Combined page 8'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: File 5 should be grouped correctly using MAX adjacency score
        // Rule: Adjacency votes (belongs_to_previous) use MAX aggregation across windows
        $this->assertGroupsMatch([
            ['name' => 'Combined Group', 'files' => [1, 2, 3, 4, 5, 6, 7, 8]],
        ], $result['groups']);

        // STRICT: All files in same group because adjacency score uses MAX(3, 5) = 5
        // File 5 adjacency: Window A(3), Window B(5) → MAX = 5 (strong adjacency to file 4)
        foreach (range(1, 8) as $pageNumber) {
            $this->assertFileInGroup($pageNumber, 'Combined Group', $result['file_to_group_mapping']);
        }

        // File 5 should have high confidence and strong adjacency
        $this->assertFileInGroup(5, 'Combined Group', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']);
    }

    #[Test]
    public function complex_multi_window_overlap_with_varying_confidences(): void
    {
        // Given: Complex scenario with 4 windows, overlapping regions, varying confidences
        // Tests realistic multi-window aggregation
        $artifacts = $this->createWindowArtifacts([
            // Window 1: Pages 1-5
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Company A', 5, null, null, 'Company A header'),
                    $this->makeFileEntry(2, 'Company A', 5, 5, 'Same letterhead', 'Company A page 2'),
                    $this->makeFileEntry(3, 'Company A', 4, 4, 'Likely same', 'Company A page 3'),
                    $this->makeFileEntry(4, 'Company B', 3, 2, 'Maybe different', 'Uncertain'),
                    $this->makeFileEntry(5, 'Company B', 3, 3, 'Possibly same', 'Company B page 2'),
                ],
            ],
            // Window 2: Pages 3-7
            [
                'start' => 3,
                'end'   => 7,
                'files' => [
                    $this->makeFileEntry(3, 'Company A', 5, null, null, 'Company A continuation'),
                    $this->makeFileEntry(4, 'Company A', 4, 5, 'Same letterhead', 'Company A page 4'),
                    $this->makeFileEntry(5, 'Company B', 5, 0, 'Clear new header', 'Company B header'),
                    $this->makeFileEntry(6, 'Company B', 5, 5, 'Same letterhead', 'Company B page 2'),
                    $this->makeFileEntry(7, 'Company B', 5, 5, 'Same letterhead', 'Company B page 3'),
                ],
            ],
            // Window 3: Pages 5-9
            [
                'start' => 5,
                'end'   => 9,
                'files' => [
                    $this->makeFileEntry(5, 'Company B', 5, null, null, 'Company B continuation'),
                    $this->makeFileEntry(6, 'Company B', 5, 5, 'Same letterhead', 'Company B page 2'),
                    $this->makeFileEntry(7, 'Company B', 5, 5, 'Same letterhead', 'Company B page 3'),
                    $this->makeFileEntry(8, 'Company B', 5, 5, 'Same letterhead', 'Company B page 4'),
                    $this->makeFileEntry(9, 'Company B', 5, 5, 'Same letterhead', 'Company B page 5'),
                ],
            ],
            // Window 4: Pages 7-10
            [
                'start' => 7,
                'end'   => 10,
                'files' => [
                    $this->makeFileEntry(7, 'Company B', 5, null, null, 'Company B continuation'),
                    $this->makeFileEntry(8, 'Company B', 5, 5, 'Same letterhead', 'Company B page 4'),
                    $this->makeFileEntry(9, 'Company B', 5, 5, 'Same letterhead', 'Company B page 5'),
                    $this->makeFileEntry(10, 'Company C', 5, 0, 'New header', 'Company C header'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: Verify correct grouping with confidence resolution
        // This is a complex edge case testing both group name conflicts AND adjacency
        //
        // File 4 Analysis:
        // - Window 1: "Company B" (conf 3), belongs_to_previous=2 (weak boundary)
        // - Window 2: "Company A" (conf 4), belongs_to_previous=5 (strong adjacency to file 3)
        // → "Company A" wins (higher confidence 4 > 3)
        //
        // File 5 Analysis:
        // - Window 1: "Company B" (conf 3), belongs_to_previous=3 (moderate adjacency to file 4)
        // - Window 2: "Company B" (conf 5), belongs_to_previous=0 (NEW HEADER - boundary marker!)
        // - Window 3: "Company B" (conf 5), belongs_to_previous=null (first in window)
        // → MAX confidence = 5, which is >= threshold (3)
        // → HIGH confidence group assignments are NEVER overridden by adjacency
        // → File 5 stays in "Company B" (its assigned group)
        //
        // Expected: File 5 in Company B (high confidence prevents adjacency override)
        $this->assertGroupsMatch([
            ['name' => 'Company A', 'files' => [1, 2, 3, 4]],
            ['name' => 'Company B', 'files' => [5, 6, 7, 8, 9]],
            ['name' => 'Company C', 'files' => [10]],
        ], $result['groups']);

        // STRICT assertions for disputed files with detailed reasoning
        // File 3: Window 1 "Company A" (conf 4), Window 2 "Company A" (conf 5)
        // → Company A (both agree), MAX(4,5) = 5
        $this->assertFileInGroup(3, 'Company A', $result['file_to_group_mapping']);
        $this->assertFileConfidence(3, 5, $result['file_to_group_mapping']); // MAX(4, 5) = 5

        // File 4: Window 1 "Company B" (conf 3), Window 2 "Company A" (conf 4)
        // → Company A wins (higher confidence: 4 > 3)
        $this->assertFileInGroup(4, 'Company A', $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 4, $result['file_to_group_mapping']); // MAX(3, 4) = 4

        // File 5: High confidence group assignment prevents adjacency override
        // Window 1 "Company B" (conf 3), Window 2 "Company B" (conf 5), Window 3 "Company B" (conf 5)
        // → MAX confidence = 5 >= threshold (3)
        // → File 5 stays in "Company B" (high confidence, adjacency cannot override)
        $this->assertFileInGroup(5, 'Company B', $result['file_to_group_mapping']);
        $this->assertFileConfidence(5, 5, $result['file_to_group_mapping']); // MAX(3, 5, 5) = 5
    }
}
