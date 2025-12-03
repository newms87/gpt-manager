<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Models\Task\Artifact;
use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests the new flat schema format for file organization.
 *
 * The NEW format has a flat `files` array with:
 * - group_name directly on each file (not nested under groups)
 * - group_name_confidence instead of just confidence
 * - belongs_to_previous and belongs_to_previous_reason
 * - group_explanation
 *
 * Example:
 * {
 *   "files": [
 *     {
 *       "page_number": 1,
 *       "belongs_to_previous": null,
 *       "belongs_to_previous_reason": "First page...",
 *       "group_name": "ME Physical Therapy",
 *       "group_name_confidence": 5,
 *       "group_explanation": "..."
 *     }
 *   ]
 * }
 */
class NewFlatSchemaFormatTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private FileOrganizationMergeService $mergeService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->mergeService = app(FileOrganizationMergeService::class);
    }

    #[Test]
    public function handles_new_flat_format_with_single_group(): void
    {
        // Given: Artifact with NEW flat format (files array, not groups array)
        $artifacts = $this->createFlatFormatArtifacts([
            [
                'page_number'                => 1,
                'belongs_to_previous'        => null,
                'belongs_to_previous_reason' => 'First page in window — no previous page to compare.',
                'group_name'                 => 'ME Physical Therapy',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'CMS-1500 claim form lists Billing Provider for ME Physical Therapy...',
            ],
            [
                'page_number'                => 2,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same letterhead/logo and continued clinical note...',
                'group_name'                 => 'ME Physical Therapy',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Document header shows ME Physical Therapy logo...',
            ],
            [
                'page_number'                => 3,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Continuation of same clinical note...',
                'group_name'                 => 'ME Physical Therapy',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Same document format and header...',
            ],
        ]);

        // When: Merge service processes the new format
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with all 3 files
        $this->assertCount(1, $result['groups'], 'Expected 1 group');
        $this->assertEquals('ME Physical Therapy', $result['groups'][0]['name']);
        $this->assertEquals([1, 2, 3], $result['groups'][0]['files']);

        // Verify file mapping has correct data
        $this->assertArrayHasKey(1, $result['file_to_group_mapping']);
        $this->assertEquals('ME Physical Therapy', $result['file_to_group_mapping'][1]['group_name']);
        $this->assertEquals(5, $result['file_to_group_mapping'][1]['confidence']);
        $this->assertNull($result['file_to_group_mapping'][1]['belongs_to_previous']);

        $this->assertArrayHasKey(2, $result['file_to_group_mapping']);
        $this->assertEquals(5, $result['file_to_group_mapping'][2]['belongs_to_previous']);
    }

    #[Test]
    public function handles_new_flat_format_with_multiple_groups(): void
    {
        // Given: Artifact with NEW flat format containing multiple groups
        $artifacts = $this->createFlatFormatArtifacts([
            [
                'page_number'                => 1,
                'belongs_to_previous'        => null,
                'belongs_to_previous_reason' => 'First page',
                'group_name'                 => 'Acme Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Acme Corp header visible',
            ],
            [
                'page_number'                => 2,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same letterhead',
                'group_name'                 => 'Acme Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Continuation of Acme Corp document',
            ],
            [
                'page_number'                => 3,
                'belongs_to_previous'        => 0,
                'belongs_to_previous_reason' => 'Different header - clear boundary',
                'group_name'                 => 'Beta Inc',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Beta Inc header visible',
            ],
            [
                'page_number'                => 4,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same letterhead',
                'group_name'                 => 'Beta Inc',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Continuation of Beta Inc document',
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Two groups with correct files
        $this->assertCount(2, $result['groups'], 'Expected 2 groups');

        $acmeGroup = collect($result['groups'])->firstWhere('name', 'Acme Corp');
        $betaGroup = collect($result['groups'])->firstWhere('name', 'Beta Inc');

        $this->assertNotNull($acmeGroup, 'Acme Corp group not found');
        $this->assertNotNull($betaGroup, 'Beta Inc group not found');

        $this->assertEquals([1, 2], $acmeGroup['files']);
        $this->assertEquals([3, 4], $betaGroup['files']);

        // Verify boundary file
        $this->assertEquals(0, $result['file_to_group_mapping'][3]['belongs_to_previous']);
    }

    #[Test]
    public function handles_new_flat_format_with_low_confidence_files(): void
    {
        // Given: Artifact with low confidence file that should be resolved via adjacency
        $artifacts = $this->createFlatFormatArtifacts([
            [
                'page_number'                => 1,
                'belongs_to_previous'        => null,
                'belongs_to_previous_reason' => 'First page',
                'group_name'                 => 'Company A',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Clear header',
            ],
            [
                'page_number'                => 2,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Continuation',
                'group_name'                 => 'Company A',
                'group_name_confidence'      => 2, // LOW confidence
                'group_explanation'          => 'Unclear header',
            ],
            [
                'page_number'                => 3,
                'belongs_to_previous'        => 0,
                'belongs_to_previous_reason' => 'Different document',
                'group_name'                 => 'Company B',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'Clear new header',
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Page 2 should stay with Company A due to high adjacency to page 1
        $this->assertCount(2, $result['groups']);

        $companyAGroup = collect($result['groups'])->firstWhere('name', 'Company A');
        $this->assertEquals([1, 2], $companyAGroup['files']);
    }

    #[Test]
    public function handles_new_flat_format_across_multiple_windows(): void
    {
        // Given: Multiple artifacts with NEW flat format
        $artifact1 = $this->createSingleFlatFormatArtifact([
            [
                'page_number'                => 1,
                'belongs_to_previous'        => null,
                'belongs_to_previous_reason' => 'First page',
                'group_name'                 => 'XYZ Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'XYZ header',
            ],
            [
                'page_number'                => 2,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same document',
                'group_name'                 => 'XYZ Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'XYZ continuation',
            ],
        ]);

        $artifact2 = $this->createSingleFlatFormatArtifact([
            [
                'page_number'                => 2,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same document',
                'group_name'                 => 'XYZ Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'XYZ continuation',
            ],
            [
                'page_number'                => 3,
                'belongs_to_previous'        => 5,
                'belongs_to_previous_reason' => 'Same document',
                'group_name'                 => 'XYZ Corp',
                'group_name_confidence'      => 5,
                'group_explanation'          => 'XYZ continuation',
            ],
        ]);

        $artifacts = new Collection([$artifact1, $artifact2]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts);

        // Then: Single group with all files merged
        $this->assertCount(1, $result['groups']);
        $this->assertEquals([1, 2, 3], $result['groups'][0]['files']);
    }

    /**
     * Create artifacts using the NEW flat format.
     */
    private function createFlatFormatArtifacts(array $files): Collection
    {
        return new Collection([
            $this->createSingleFlatFormatArtifact($files),
        ]);
    }

    /**
     * Create a single artifact with NEW flat format.
     */
    private function createSingleFlatFormatArtifact(array $files): Artifact
    {
        return Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'files' => $files, // NEW format: flat files array
            ],
        ]);
    }
}
