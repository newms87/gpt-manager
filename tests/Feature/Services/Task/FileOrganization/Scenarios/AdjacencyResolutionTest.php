<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * STRICT ACCEPTANCE TESTS for Adjacency Resolution
 *
 * These tests assert the EXACT expected behavior from the algorithm plan.
 * They are IMPLEMENTATION-AGNOSTIC - they don't care if current code passes.
 * They serve as ACCEPTANCE CRITERIA for the new adjacency-based algorithm.
 *
 * Based on Phase 4 of the merge algorithm (lines 234-329 of breezy-finding-wind.md):
 * - HIGH group confidence (4-5): Keep current assignment, ignore adjacency
 * - LOW group confidence + HIGH adjacency to prev: Keep with previous group
 * - LOW group confidence + LOW adjacency to prev: Consider reassigning to next group
 * - Ambiguous adjacency (score 3): Flag for resolution
 * - TIES resolve to PREVIOUS group (continuity over forward association)
 */
class AdjacencyResolutionTest extends AuthenticatedTestCase
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
    public function tie_breaker_equal_adjacency_scores_resolve_to_previous_group(): void
    {
        // CRITICAL TIE-BREAKER TEST (Algorithm lines 284-309)
        //
        // Rule: When adjacency scores are tied, PREVIOUS wins (continuity over forward association)
        //
        // File 1: "Acme" (conf 5) - Strong anchor
        // File 2: "Unknown" (conf 1), belongs_prev: 3 - AMBIGUOUS connection to file 1
        // File 3: "Beta" (conf 5), belongs_prev: 3 - SAME SCORE claiming file 2
        //
        // EXPECTED: File 2 MUST go to "Acme Corp" (previous wins ties)
        // This is the MOST CRITICAL test - ties MUST resolve to previous!
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme letterhead'),
                    $this->makeFileEntry(2, 'Unknown', 1, 3, 'Moderate similarity to previous', 'Very unclear which group'),
                    $this->makeFileEntry(3, 'Beta Inc', 5, 3, 'Moderate similarity to previous', 'Strong Beta letterhead'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // DEBUG
        dump([
            'file_mapping' => $result['file_to_group_mapping'],
            'groups'       => $result['groups'],
        ]);

        // Then: STRICT ASSERTION - File 2 MUST be in "Acme Corp" (previous wins ties)
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping']);

        // THE CRITICAL ASSERTION: File 2 MUST go to Acme (previous), NOT Beta (next)
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping'],
            'When adjacency scores are tied (both 3), file MUST go to PREVIOUS group (Acme), not next group (Beta). This is the tie-breaker rule.');

        // Verify group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
            ['name' => 'Beta Inc', 'files' => [3]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function low_group_confidence_with_high_adjacency_keeps_file_with_previous_group(): void
    {
        // Algorithm Rule 2 (line 276): LOW group_conf + HIGH adjacency to prev → Keep with previous group
        //
        // File 1: "Acme" (conf 5) - Strong start
        // File 2: "Acme" (conf 5), belongs_prev: 5 - Strongly continues
        // File 3: "Acme" (conf 2), belongs_prev: 5 - WEAK group name but STRONG adjacency
        //
        // EXPECTED: File 3 MUST stay in "Acme Corp" because high adjacency (5) confirms it belongs
        // despite low group confidence (2)
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Clear Acme letterhead'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Acme Corp', 2, 5, 'Same style and format', 'Header is unclear but style matches'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTION - All files MUST be in "Acme Corp"
        // High adjacency (5) overrides low group confidence (2)
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping'],
            'File 3 has LOW group confidence (2) but HIGH adjacency (5). Rule: Keep with previous group.');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function low_group_confidence_with_low_adjacency_suggests_boundary(): void
    {
        // Algorithm Rule 3 (line 277): LOW group_conf + LOW adjacency to prev → Consider reassigning to next group
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 5), belongs_prev: 5
        // File 3: "Acme" (conf 2), belongs_prev: 1 - WEAK group AND WEAK adjacency to file 2
        // File 4: "Beta" (conf 5), belongs_prev: 5 - Strongly claims file 3
        //
        // EXPECTED: File 3 should be REASSIGNED to "Beta Inc"
        // Low adjacency (1) to previous + high adjacency (5) from next = boundary + reassignment
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme letterhead'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Acme Corp', 2, 1, 'Different style', 'Unclear - transition page?'),
                    $this->makeFileEntry(4, 'Beta Inc', 5, 5, 'Strong Beta letterhead', 'New group starts'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS
        // Files 1-2 stay in Acme
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping']);

        // File 4 starts Beta
        $this->assertFileInGroup(4, 'Beta Inc', $result['file_to_group_mapping']);

        // THE CRITICAL ASSERTION: File 3 MUST be reassigned to "Beta Inc"
        // File 3 has low conf (2) + low adjacency to prev (1) + next file claims it with high adjacency (5)
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping'],
            'File 3 has LOW group confidence (2) AND LOW adjacency to previous (1). File 4 claims it with HIGH adjacency (5). Rule: Reassign to next group (Beta).');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
            ['name' => 'Beta Inc', 'files' => [3, 4]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function high_group_confidence_ignores_adjacency_score(): void
    {
        // Algorithm Rule 1 (line 275): HIGH group_conf (4-5) → Keep current assignment, ignore adjacency
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 5), belongs_prev: 1 - LOW adjacency but HIGH confidence
        //
        // EXPECTED: File 2 MUST stay in "Acme" because confidence is high (adjacency ignored)
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 2,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme letterhead'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 1, 'Different format but clear Acme logo', 'Confident it is Acme despite style change'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTION - Both files MUST be in "Acme Corp"
        // High confidence (5) means adjacency is IGNORED
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping'],
            'File 2 has HIGH group confidence (5). Rule: Keep current assignment, ignore LOW adjacency (1).');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);

        // Verify confidence is preserved
        $this->assertFileConfidence(2, 5, $result['file_to_group_mapping']);
    }

    #[Test]
    public function files_below_confidence_threshold_are_eligible_for_reassignment(): void
    {
        // Algorithm Rule (lines 171-187): Files with group_conf < threshold are eligible for reassignment
        // Default threshold: 3
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 2), belongs_prev: 5 - BELOW threshold (2 < 3), eligible for reassignment
        // File 3: "Acme" (conf 3), belongs_prev: 5 - AT threshold (3 >= 3), NOT eligible
        // File 4: "Acme" (conf 4), belongs_prev: 5 - ABOVE threshold (4 > 3), NOT eligible
        //
        // EXPECTED: All stay in "Acme" because HIGH adjacency (5) keeps even low-conf files
        // But we're verifying that threshold logic is APPLIED
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 2, 5, 'Same style', 'Weak confidence but strong adjacency'),
                    $this->makeFileEntry(3, 'Acme Corp', 3, 5, 'Same style', 'At threshold confidence'),
                    $this->makeFileEntry(4, 'Acme Corp', 4, 5, 'Same style', 'Good confidence'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS - All files MUST stay in "Acme Corp"
        // Even file 2 (conf 2 < threshold 3) stays because adjacency is HIGH (5)
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping'],
            'File 2 has conf (2) BELOW threshold (3) making it eligible for reassignment, but HIGH adjacency (5) keeps it in Acme.');
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping'],
            'File 3 has conf (3) AT threshold - NOT eligible for reassignment.');
        $this->assertFileInGroup(4, 'Acme Corp', $result['file_to_group_mapping'],
            'File 4 has conf (4) ABOVE threshold - definitely NOT eligible for reassignment.');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);

        // Verify confidence levels are PRESERVED
        $this->assertFileConfidence(1, 5, $result['file_to_group_mapping']);
        $this->assertFileConfidence(2, 2, $result['file_to_group_mapping']);
        $this->assertFileConfidence(3, 3, $result['file_to_group_mapping']);
        $this->assertFileConfidence(4, 4, $result['file_to_group_mapping']);
    }

    #[Test]
    public function chain_of_low_confidence_files_uses_adjacency_to_group_correctly(): void
    {
        // Algorithm: Multiple sequential files with low confidence but strong adjacency
        // Adjacency should chain them together correctly
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 2), belongs_prev: 5 - Low conf, high adjacency
        // File 3: "Acme" (conf 2), belongs_prev: 5 - Low conf, high adjacency
        // File 4: "Acme" (conf 2), belongs_prev: 5 - Low conf, high adjacency
        // File 5: "Beta" (conf 5), belongs_prev: 1 - Strong start of new group, LOW adjacency to file 4
        //
        // EXPECTED: Files 1-4 stay in "Acme" (adjacency chains them), file 5 starts "Beta"
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Strong Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 2, 5, 'Consistent style', 'Header unclear but same format'),
                    $this->makeFileEntry(3, 'Acme Corp', 2, 5, 'Consistent style', 'Header unclear but same format'),
                    $this->makeFileEntry(4, 'Acme Corp', 2, 5, 'Consistent style', 'Header unclear but same format'),
                    $this->makeFileEntry(5, 'Beta Inc', 5, 1, 'Clear style break', 'Strong Beta letterhead starts'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS - High adjacency MUST chain the low-confidence files together
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping'],
            'File 2 has low conf (2) but HIGH adjacency (5) - chained to file 1.');
        $this->assertFileInGroup(3, 'Acme Corp', $result['file_to_group_mapping'],
            'File 3 has low conf (2) but HIGH adjacency (5) - chained to file 2.');
        $this->assertFileInGroup(4, 'Acme Corp', $result['file_to_group_mapping'],
            'File 4 has low conf (2) but HIGH adjacency (5) - chained to file 3.');
        $this->assertFileInGroup(5, 'Beta Inc', $result['file_to_group_mapping'],
            'File 5 has HIGH conf (5) AND LOW adjacency (1) - starts new group.');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2, 3, 4]],
            ['name' => 'Beta Inc', 'files' => [5]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function low_confidence_with_conflicting_adjacency_creates_clear_boundary(): void
    {
        // Algorithm: Clear boundary indicated by LOW adjacency
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Acme" (conf 5), belongs_prev: 5
        // File 3: "Beta" (conf 2), belongs_prev: 1 - LOW adjacency to file 2 (BOUNDARY!)
        // File 4: "Beta" (conf 5), belongs_prev: 5 - HIGH adjacency to file 3
        //
        // EXPECTED: Files 1-2 in "Acme", Files 3-4 in "Beta" (boundary at 2-3)
        // File 3's low adjacency (1) to file 2 creates clear boundary
        // File 4's high adjacency (5) to file 3 pulls file 3 into Beta
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme letterhead'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Clearly Acme'),
                    $this->makeFileEntry(3, 'Beta Inc', 2, 1, 'Different style from previous', 'Looks like Beta but unclear'),
                    $this->makeFileEntry(4, 'Beta Inc', 5, 5, 'Strong Beta letterhead', 'Definitely Beta'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS - The low adjacency (1) at file 3 MUST create clear boundary
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping']);

        // File 3 MUST be with Beta (low adjacency to prev + high adjacency from next)
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping'],
            'File 3 has LOW adjacency (1) to file 2, creating boundary. File 4 claims it with HIGH adjacency (5). File 3 goes to Beta.');
        $this->assertFileInGroup(4, 'Beta Inc', $result['file_to_group_mapping']);

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
            ['name' => 'Beta Inc', 'files' => [3, 4]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function ambiguous_adjacency_score_with_low_confidence_requires_resolution(): void
    {
        // Algorithm Rule 4 (line 278): LOW group_conf + ambiguous adjacency (3) → Flag for AI resolution
        //
        // File 1: "Acme" (conf 5)
        // File 2: "Unclear" (conf 2), belongs_prev: 3 - AMBIGUOUS adjacency + LOW confidence
        // File 3: "Beta" (conf 5), belongs_prev: 3 - AMBIGUOUS from other side too
        //
        // EXPECTED: This is truly ambiguous - algorithm must make a choice
        // According to tie-breaker rule (line 279), TIES resolve to PREVIOUS
        // So file 2 should go to "Acme Corp" (previous wins ties)
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme letterhead'),
                    $this->makeFileEntry(2, 'Uncertain', 2, 3, 'Some similarity to previous', 'Could go either way'),
                    $this->makeFileEntry(3, 'Beta Inc', 5, 3, 'Some similarity to previous', 'Beta letterhead'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS
        $this->assertFileInGroup(1, 'Acme Corp', $result['file_to_group_mapping']);
        $this->assertFileInGroup(3, 'Beta Inc', $result['file_to_group_mapping']);

        // File 2 has ambiguous adjacency (3) in both directions
        // Tie-breaker rule: PREVIOUS wins ties
        $this->assertFileInGroup(2, 'Acme Corp', $result['file_to_group_mapping'],
            'File 2 has ambiguous adjacency (3) to file 1 AND file 3 has ambiguous adjacency (3) to file 2. Tie-breaker rule: PREVIOUS wins. File 2 goes to Acme.');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Acme Corp', 'files' => [1, 2]],
            ['name' => 'Beta Inc', 'files' => [3]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);
    }

    #[Test]
    public function files_with_exactly_threshold_confidence_are_eligible_for_adjacency_resolution(): void
    {
        // BUG REPRODUCTION TEST - Confidence Threshold Boundary Bug
        //
        // Default threshold is 3
        // Files with confidence = 3 (exactly at threshold) should be eligible for reassignment
        // based on adjacency scores, NOT treated as "high confidence" and skipped.
        //
        // BUG: Line 240-241 of FileOrganizationMergeService uses `>=` instead of `>`
        // This causes files with confidence = 3 to be treated as HIGH confidence
        // when they should be treated as LOW confidence.
        //
        // Real-world impact: Pages 110-111 in task run 81 have:
        // - confidence = 3 (at the threshold)
        // - belongs_to_previous = 5 (high adjacency)
        // - assigned to "Ivo Milic-Strkalj, DPT"
        // They SHOULD be reassigned to "ME Physical Therapy" due to high adjacency.
        //
        // File 1: "Group A" (conf 5) - Strong anchor
        // File 2: "Group A" (conf 5), belongs_prev: 5 - Strong continuation
        // File 3: "Group B" (conf 3), belongs_prev: 5 - EXACTLY threshold confidence (3) but HIGH adjacency (5)
        //
        // EXPECTED: File 3 should be reassigned to Group A because:
        // - confidence = 3 is NOT high (should be eligible for reassignment)
        // - belongs_to_previous = 5 is high (strong adjacency to Group A)
        // Therefore adjacency should win and reassign file 3 to Group A
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeFileEntry(1, 'Group A', 5, null, null, 'Strong Group A letterhead'),
                    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead', 'Clearly Group A'),
                    $this->makeFileEntry(3, 'Group B', 3, 5, 'Same style as Group A', 'Header says Group B but style matches Group A'),
                ],
            ],
        ]);

        // When: Merging the window results
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);

        // Then: STRICT ASSERTIONS
        $this->assertFileInGroup(1, 'Group A', $result['file_to_group_mapping']);
        $this->assertFileInGroup(2, 'Group A', $result['file_to_group_mapping']);

        // THE CRITICAL ASSERTION: File 3 should be reassigned to Group A
        // Because confidence=3 (at threshold) should be treated as LOW confidence (eligible for reassignment)
        // And belongs_to_previous=5 (high adjacency) should cause reassignment to Group A
        $this->assertFileInGroup(3, 'Group A', $result['file_to_group_mapping'],
            'File 3 has confidence=3 (exactly at threshold) and high adjacency=5. Should be reassigned to Group A because confidence at threshold is NOT high confidence.');

        // Verify exact group structure
        $expectedGroups = [
            ['name' => 'Group A', 'files' => [1, 2, 3]],
        ];
        $this->assertGroupsMatch($expectedGroups, $result['groups']);

        // Verify confidence is preserved
        $this->assertFileConfidence(3, 3, $result['file_to_group_mapping']);
    }
}
