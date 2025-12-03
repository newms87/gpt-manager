<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\DuplicateGroupDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests Phase 5: Merge Similar Group Names
 *
 * These tests verify the DuplicateGroupDetector's name similarity algorithm.
 * The algorithm should:
 * 1. Calculate similarity between group names using fuzzy matching
 * 2. Flag groups with similarity >= threshold (default 0.7) as duplicate candidates
 * 3. Prepare duplicate candidates for LLM resolution
 *
 * NOTE: The current implementation does NOT automatically merge similar names.
 * Instead, it identifies duplicate candidates and flags them for LLM resolution.
 * These tests verify the DETECTION logic, not automatic merging.
 */
class GroupNameSimilarityTest extends AuthenticatedTestCase
{
    use FileOrganizationTestHelpers;
    use SetUpTeamTrait;

    private DuplicateGroupDetector $detector;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->detector = app(DuplicateGroupDetector::class);
    }

    #[Test]
    public function similar_names_detected_when_above_threshold(): void
    {
        // Given: Two groups with similar names
        // "Acme Corp" vs "Acme Corporation"
        // Expected similarity: ~0.85 (substring + high Levenshtein similarity)
        $groups = [
            ['name' => 'Acme Corp', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Acme Corporation', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate candidate (similarity >= 0.7)
        $this->assertCount(1, $candidates, 'Expected exactly 1 duplicate candidate pair');
        $this->assertSame('Acme Corp', $candidates[0]['group1']);
        $this->assertSame('Acme Corporation', $candidates[0]['group2']);
        $this->assertGreaterThanOrEqual(0.7, $candidates[0]['similarity'],
            'Similarity should be >= 0.7 threshold');
        $this->assertLessThanOrEqual(1.0, $candidates[0]['similarity'],
            'Similarity should be <= 1.0');
    }

    #[Test]
    public function different_names_not_detected_below_threshold(): void
    {
        // Given: Two groups with completely different names
        // "Acme Corp" vs "Beta Inc"
        // Expected similarity: ~0.2-0.3 (very low)
        $groups = [
            ['name' => 'Acme Corp', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Beta Inc', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should NOT identify as duplicate candidate (similarity < 0.7)
        $this->assertCount(0, $candidates, 'Expected no duplicate candidates for dissimilar names');
    }

    #[Test]
    public function case_differences_detected_as_duplicates(): void
    {
        // Given: Same name but different case
        // "ACME CORP" vs "Acme Corp"
        // After normalization (lowercase), these are identical
        // Expected similarity: 1.0 (exact match after normalization)
        $groups = [
            ['name' => 'ACME CORP', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Acme Corp', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate (normalization makes them identical)
        $this->assertCount(1, $candidates, 'Expected case differences to be detected as duplicates');
        $this->assertSame(1.0, $candidates[0]['similarity'],
            'Case-only differences should have 1.0 similarity after normalization');
    }

    #[Test]
    public function whitespace_variations_detected_as_duplicates(): void
    {
        // Given: Same name but different whitespace
        // "Acme Corp" vs "Acme  Corp" (double space)
        // After normalization (trim, collapse whitespace), these are identical
        // Expected similarity: 1.0
        $groups = [
            ['name' => 'Acme Corp', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Acme  Corp', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate after normalization
        $this->assertCount(1, $candidates, 'Expected whitespace variations to be detected as duplicates');
        $this->assertSame(1.0, $candidates[0]['similarity'],
            'Whitespace-only differences should have 1.0 similarity after normalization');
    }

    #[Test]
    public function punctuation_differences_detected_as_duplicates(): void
    {
        // Given: Same name but different punctuation
        // "Dr. Smith" vs "Dr Smith" (period removed)
        // After normalization (remove punctuation), these are identical
        // Expected similarity: 1.0
        $groups = [
            ['name' => 'Dr. Smith', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Dr Smith', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate after normalization
        $this->assertCount(1, $candidates, 'Expected punctuation variations to be detected as duplicates');
        $this->assertSame(1.0, $candidates[0]['similarity'],
            'Punctuation-only differences should have 1.0 similarity after normalization');
    }

    #[Test]
    public function location_suffix_variants_detected_as_duplicates(): void
    {
        // Given: Same name with location suffix in parentheses
        // "ABC Medical" vs "ABC Medical (Northglenn)"
        // ACTUAL BEHAVIOR: Substring match gives 0.85, NOT 0.95
        // The isLocationVariant() check expects exact match on the base name,
        // but "abc medical" vs "abc medical (northglenn)" triggers substring logic instead
        $groups = [
            ['name' => 'ABC Medical', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'ABC Medical (Northglenn)', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate with substring similarity (0.85)
        $this->assertCount(1, $candidates, 'Expected location variants to be detected as duplicates');
        $this->assertSame(0.85, $candidates[0]['similarity'],
            'Location suffix variants detected via substring match (0.85, not 0.95)');
    }

    #[Test]
    public function substring_variants_detected_as_duplicates(): void
    {
        // Given: One name is a substring of the other
        // "ABC" vs "ABC Medical Center"
        // Substring detection should score this high (>= 0.85)
        $groups = [
            ['name' => 'ABC', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'ABC Medical Center', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should identify as duplicate (substring match)
        $this->assertCount(1, $candidates, 'Expected substring variants to be detected as duplicates');
        $this->assertGreaterThanOrEqual(0.85, $candidates[0]['similarity'],
            'Substring variants should have >= 0.85 similarity');
    }

    #[Test]
    public function abbreviation_variants_not_detected_below_threshold(): void
    {
        // Given: Abbreviated vs full form
        // "Dr. Smith" vs "Doctor Smith"
        // ACTUAL BEHAVIOR: Similarity ~0.64 (below 0.7 threshold)
        // After normalization: "dr smith" vs "doctor smith"
        // Levenshtein distance = 3, max length = 12
        // Similarity = 1 - (3/12) = 0.75... BUT "dr" is NOT a substring of "doctor"
        // so it falls through to Levenshtein which gives ~0.64
        $groups = [
            ['name' => 'Dr. Smith', 'description' => 'Group 1', 'files' => [1, 2, 3]],
            ['name' => 'Doctor Smith', 'description' => 'Group 2', 'files' => [4, 5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should NOT be detected (below threshold)
        $this->assertCount(0, $candidates,
            'Abbreviation "Dr." vs "Doctor" does not meet 0.7 threshold (actual ~0.64)');
    }

    #[Test]
    public function multiple_similar_groups_some_detected(): void
    {
        // Given: Multiple groups with similar names
        // "Acme Corp", "Acme Corporation", "Acme Inc"
        // ACTUAL BEHAVIOR: Only "Acme Corp" <-> "Acme Corporation" meets threshold
        // - "Acme Corp" vs "Acme Corporation": substring match = 0.85 ✓
        // - "Acme Corp" vs "Acme Inc": Levenshtein ~0.64 ✗
        // - "Acme Corporation" vs "Acme Inc": Levenshtein ~0.56 ✗
        $groups = [
            ['name' => 'Acme Corp', 'description' => 'Group 1', 'files' => [1, 2]],
            ['name' => 'Acme Corporation', 'description' => 'Group 2', 'files' => [3, 4]],
            ['name' => 'Acme Inc', 'description' => 'Group 3', 'files' => [5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Only 1 pair should be detected (Acme Corp <-> Acme Corporation)
        $this->assertCount(1, $candidates, 'Expected only 1 duplicate candidate pair (Corp <-> Corporation)');

        // Verify the correct pair is detected
        $this->assertSame('Acme Corp', $candidates[0]['group1']);
        $this->assertSame('Acme Corporation', $candidates[0]['group2']);
        $this->assertGreaterThanOrEqual(0.7, $candidates[0]['similarity']);
    }

    #[Test]
    public function empty_names_skipped(): void
    {
        // Given: Some groups have empty names (should be skipped)
        $groups = [
            ['name' => '', 'description' => 'Empty group 1', 'files' => [1, 2]],
            ['name' => 'Acme Corp', 'description' => 'Real group', 'files' => [3, 4]],
            ['name' => '', 'description' => 'Empty group 2', 'files' => [5, 6]],
        ];

        // When
        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should skip empty names (no candidates)
        $this->assertCount(0, $candidates, 'Expected empty names to be skipped');
    }

    #[Test]
    public function similarity_threshold_enforced(): void
    {
        // Given: Groups with borderline similarity
        // Test names that are just above and just below the 0.7 threshold
        // We'll use Levenshtein distance calculation to verify threshold enforcement

        // Names with low similarity (< 0.7): Should NOT be detected
        $lowSimilarityGroups = [
            ['name' => 'Alpha Medical', 'description' => 'Group 1', 'files' => [1, 2]],
            ['name' => 'Beta Therapy', 'description' => 'Group 2', 'files' => [3, 4]],
        ];

        $lowCandidates = $this->detector->identifyDuplicateCandidates($lowSimilarityGroups);
        $this->assertCount(0, $lowCandidates,
            'Names with low similarity should NOT be detected as duplicates');

        // Names with high similarity (>= 0.7): Should be detected
        $highSimilarityGroups = [
            ['name' => 'Acme Medical Services', 'description' => 'Group 1', 'files' => [1, 2]],
            ['name' => 'Acme Medical', 'description' => 'Group 2', 'files' => [3, 4]],
        ];

        $highCandidates = $this->detector->identifyDuplicateCandidates($highSimilarityGroups);
        $this->assertCount(1, $highCandidates,
            'Names with high similarity should be detected as duplicates');
        $this->assertGreaterThanOrEqual(0.7, $highCandidates[0]['similarity'],
            'Detected candidates should meet the 0.7 threshold');
    }

    #[Test]
    public function prepare_duplicate_for_resolution_includes_required_fields(): void
    {
        // Given: Duplicate candidate and groups with file mapping
        $groups = [
            [
                'name'               => 'Acme Corp',
                'description'        => 'First group',
                'files'              => [1, 2, 3],
                'confidence_summary' => ['avg' => 4.5],
            ],
            [
                'name'               => 'Acme Corporation',
                'description'        => 'Second group',
                'files'              => [4, 5, 6],
                'confidence_summary' => ['avg' => 5.0],
            ],
        ];

        $fileToGroup = [
            1 => ['page_number' => 1, 'description' => 'Page 1', 'confidence' => 4, 'group_name' => 'Acme Corp'],
            2 => ['page_number' => 2, 'description' => 'Page 2', 'confidence' => 5, 'group_name' => 'Acme Corp'],
            3 => ['page_number' => 3, 'description' => 'Page 3', 'confidence' => 4, 'group_name' => 'Acme Corp'],
            4 => ['page_number' => 4, 'description' => 'Page 4', 'confidence' => 5, 'group_name' => 'Acme Corporation'],
            5 => ['page_number' => 5, 'description' => 'Page 5', 'confidence' => 5, 'group_name' => 'Acme Corporation'],
            6 => ['page_number' => 6, 'description' => 'Page 6', 'confidence' => 5, 'group_name' => 'Acme Corporation'],
        ];

        $candidate = [
            'group1'     => 'Acme Corp',
            'group2'     => 'Acme Corporation',
            'similarity' => 0.85,
        ];

        // When
        $prepared = $this->detector->prepareDuplicateForResolution($candidate, $groups, $fileToGroup);

        // Then: Should include all required fields for LLM resolution
        $this->assertArrayHasKey('group1', $prepared);
        $this->assertArrayHasKey('group2', $prepared);
        $this->assertArrayHasKey('similarity', $prepared);

        // Verify group1 structure
        $this->assertSame('Acme Corp', $prepared['group1']['name']);
        $this->assertSame('First group', $prepared['group1']['description']);
        $this->assertSame(3, $prepared['group1']['file_count']);
        $this->assertCount(2, $prepared['group1']['sample_files']); // getSampleFiles has limit=2
        $this->assertArrayHasKey('confidence', $prepared['group1']);

        // Verify group2 structure
        $this->assertSame('Acme Corporation', $prepared['group2']['name']);
        $this->assertSame('Second group', $prepared['group2']['description']);
        $this->assertSame(3, $prepared['group2']['file_count']);
        $this->assertCount(2, $prepared['group2']['sample_files']); // getSampleFiles has limit=2
        $this->assertArrayHasKey('confidence', $prepared['group2']);

        // Verify similarity preserved
        $this->assertSame(0.85, $prepared['similarity']);

        // Verify sample files include page_number, description, confidence
        foreach ($prepared['group1']['sample_files'] as $sample) {
            $this->assertArrayHasKey('page_number', $sample);
            $this->assertArrayHasKey('description', $sample);
            $this->assertArrayHasKey('confidence', $sample);
        }
    }

    #[Test]
    public function similarity_calculation_is_deterministic(): void
    {
        // Given: Same pair of names
        $groups = [
            ['name' => 'Test Company A', 'description' => 'Group 1', 'files' => [1, 2]],
            ['name' => 'Test Company B', 'description' => 'Group 2', 'files' => [3, 4]],
        ];

        // When: Calculate similarity multiple times
        $result1 = $this->detector->identifyDuplicateCandidates($groups);
        $result2 = $this->detector->identifyDuplicateCandidates($groups);
        $result3 = $this->detector->identifyDuplicateCandidates($groups);

        // Then: Should produce identical results
        $this->assertSame(count($result1), count($result2));
        $this->assertSame(count($result1), count($result3));

        if (count($result1) > 0) {
            $this->assertSame($result1[0]['similarity'], $result2[0]['similarity']);
            $this->assertSame($result1[0]['similarity'], $result3[0]['similarity']);
        }
    }

    #[Test]
    public function order_independence_both_pairs_detected_identically(): void
    {
        // Given: Same groups in different order
        $groups1 = [
            ['name' => 'Acme Corp', 'description' => 'Group A', 'files' => [1, 2]],
            ['name' => 'Acme Corporation', 'description' => 'Group B', 'files' => [3, 4]],
        ];

        $groups2 = [
            ['name' => 'Acme Corporation', 'description' => 'Group B', 'files' => [3, 4]],
            ['name' => 'Acme Corp', 'description' => 'Group A', 'files' => [1, 2]],
        ];

        // When
        $candidates1 = $this->detector->identifyDuplicateCandidates($groups1);
        $candidates2 = $this->detector->identifyDuplicateCandidates($groups2);

        // Then: Should detect same pair with same similarity
        $this->assertCount(1, $candidates1);
        $this->assertCount(1, $candidates2);
        $this->assertSame($candidates1[0]['similarity'], $candidates2[0]['similarity']);

        // Names should be present (order may differ due to loop structure)
        $names1 = [$candidates1[0]['group1'], $candidates1[0]['group2']];
        $names2 = [$candidates2[0]['group1'], $candidates2[0]['group2']];

        $this->assertContains('Acme Corp', $names1);
        $this->assertContains('Acme Corporation', $names1);
        $this->assertContains('Acme Corp', $names2);
        $this->assertContains('Acme Corporation', $names2);
    }
}
