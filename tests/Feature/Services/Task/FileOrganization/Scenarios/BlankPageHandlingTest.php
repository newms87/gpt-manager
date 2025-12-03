<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Services\Task\FileOrganization\FileOrganizationMergeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests how the FileOrganization algorithm handles blank/separator pages
 * based on the blank_page_handling configuration.
 *
 * Blank pages are identified by having an empty string group_name ('').
 *
 * Configuration options:
 * - join_previous: Blank pages join the previous group (default)
 * - create_blank_group: Blank pages create their own separate group
 * - discard: Blank pages are removed from the output
 */
class BlankPageHandlingTest extends AuthenticatedTestCase
{
    use FileOrganizationTestHelpers;
    use SetUpTeamTrait;

    private FileOrganizationMergeService $mergeService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->setUpFileOrganization(); // Uses default config with blank_page_handling = 'join_previous'
        $this->mergeService = app(FileOrganizationMergeService::class);
    }

    /**
     * Set up test with specific blank_page_handling configuration.
     */
    private function setUpWithBlankPageHandling(string $handling): void
    {
        $this->setUpFileOrganization([
            'blank_page_handling' => $handling,
        ]);
    }

    // ==================== Tests with blank_page_handling = "join_previous" (default) ====================

    #[Test]
    public function blank_page_between_two_groups_joins_previous(): void
    {
        // Given: Pages 1-3 are "Acme", page 4 is blank, pages 5-6 are "Beta"
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Clear Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeBlankFileEntry(4, 5), // Blank page with high belongs_to_previous
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Different letterhead', 'Clear Beta header'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Page 4 (blank) should join "Acme Corp" group
        $this->assertFileInGroup(1, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(2, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(3, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(4, 'Acme Corp', $fileMapping); // Blank page joins previous
        $this->assertFileInGroup(5, 'Beta Inc', $fileMapping);
        $this->assertFileInGroup(6, 'Beta Inc', $fileMapping);
    }

    #[Test]
    public function blank_page_ignores_adjacency_when_joining_previous(): void
    {
        // Given: Page 3 is "Acme", page 4 is blank with belongs_to_previous: 0 (agent says doesn't belong)
        // Config overrides adjacency signal for blank pages
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 3,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(3, 'Acme Corp', 5, null, null, 'Acme content'),
                    $this->makeBlankFileEntry(4, 0), // Blank with LOW belongs_to_previous (doesn't belong)
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Different company', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Page 4 STILL joins "Acme Corp" despite belongs_to_previous: 0
        // Config takes precedence over adjacency for blank pages
        $this->assertFileInGroup(3, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(4, 'Acme Corp', $fileMapping); // Config overrides adjacency
        $this->assertFileInGroup(5, 'Beta Inc', $fileMapping);
    }

    #[Test]
    public function multiple_consecutive_blank_pages_all_join_previous(): void
    {
        // Given: Pages 1-2 are "Acme", pages 3-4 are blank, pages 5-6 are "Beta"
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeBlankFileEntry(3, 5), // First blank page
                    $this->makeBlankFileEntry(4, 5), // Second blank page
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Different company', 'Beta header'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Both blank pages join "Acme Corp"
        $this->assertFileInGroup(1, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(2, 'Acme Corp', $fileMapping);
        $this->assertFileInGroup(3, 'Acme Corp', $fileMapping); // First blank joins previous
        $this->assertFileInGroup(4, 'Acme Corp', $fileMapping); // Second blank joins previous
        $this->assertFileInGroup(5, 'Beta Inc', $fileMapping);
        $this->assertFileInGroup(6, 'Beta Inc', $fileMapping);
    }

    #[Test]
    public function first_file_is_blank_merges_forward_to_next_group(): void
    {
        // Given: Pages 1-2 are blank (no previous group exists), page 3 is "Acme"
        // Edge Case: No previous group to join, so merge forward
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeBlankFileEntry(1, null), // First file - no previous to reference
                    $this->makeBlankFileEntry(2, 5),     // Second file - also blank
                    $this->makeFileEntry(3, 'Acme Corp', 5, 0, 'New group', 'Acme header'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $fileMapping = $result['file_to_group_mapping'];

        // Then: All three pages should be in "Acme Corp" group (merge forward)
        $this->assertFileInGroup(1, 'Acme Corp', $fileMapping); // Merged forward
        $this->assertFileInGroup(2, 'Acme Corp', $fileMapping); // Merged forward
        $this->assertFileInGroup(3, 'Acme Corp', $fileMapping);
    }

    #[Test]
    public function all_files_are_blank_creates_single_group_with_empty_name(): void
    {
        // Given: All pages have group_name: ''
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 4,
                'files' => [
                    $this->makeBlankFileEntry(1, null),
                    $this->makeBlankFileEntry(2, 5),
                    $this->makeBlankFileEntry(3, 5),
                    $this->makeBlankFileEntry(4, 5),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups = $result['groups'];

        // Then: Single group with empty name containing all files
        $this->assertCount(1, $groups);
        $this->assertEquals('', $groups[0]['name']); // Empty name for blank group
        $this->assertEquals([1, 2, 3, 4], $groups[0]['files']);
    }

    // ==================== Tests with blank_page_handling = "create_blank_group" ====================

    #[Test]
    public function blank_page_creates_separate_group_with_create_blank_group_config(): void
    {
        // Given: Same setup as test 1, but with blank_page_handling = "create_blank_group"
        $this->setUpWithBlankPageHandling('create_blank_group');
        $this->mergeService = app(FileOrganizationMergeService::class);

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeBlankFileEntry(4, 5), // Blank page
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Different letterhead', 'Beta header'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups = $result['groups'];

        // Then: Page 4 should be in its own group with empty name
        $this->assertCount(3, $groups);

        // Find groups by name
        $acmeGroup  = collect($groups)->firstWhere('name', 'Acme Corp');
        $blankGroup = collect($groups)->firstWhere('name', '');
        $betaGroup  = collect($groups)->firstWhere('name', 'Beta Inc');

        $this->assertNotNull($acmeGroup);
        $this->assertNotNull($blankGroup);
        $this->assertNotNull($betaGroup);

        $this->assertEquals([1, 2, 3], $acmeGroup['files']);
        $this->assertEquals([4], $blankGroup['files']); // Blank page in separate group
        $this->assertEquals([5, 6], $betaGroup['files']);
    }

    #[Test]
    public function first_file_is_blank_creates_standalone_group_with_create_blank_group_config(): void
    {
        // Given: Page 1 is blank, pages 2-3 are "Acme"
        $this->setUpWithBlankPageHandling('create_blank_group');
        $this->mergeService = app(FileOrganizationMergeService::class);

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeBlankFileEntry(1, null),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 0, 'New group', 'Acme header'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                ],
            ],
        ]);

        // When
        $result = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups = $result['groups'];

        // Then: Page 1 in separate group, pages 2-3 in "Acme Corp"
        $this->assertCount(2, $groups);

        $blankGroup = collect($groups)->firstWhere('name', '');
        $acmeGroup  = collect($groups)->firstWhere('name', 'Acme Corp');

        $this->assertNotNull($blankGroup);
        $this->assertNotNull($acmeGroup);

        $this->assertEquals([1], $blankGroup['files']);
        $this->assertEquals([2, 3], $acmeGroup['files']);
    }

    // ==================== Tests with blank_page_handling = "discard" ====================

    #[Test]
    public function blank_page_removed_from_output_with_discard_config(): void
    {
        // Given: Pages 1-2 are "Acme", page 3 is blank, pages 4-5 are "Beta"
        $this->setUpWithBlankPageHandling('discard');
        $this->mergeService = app(FileOrganizationMergeService::class);

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeBlankFileEntry(3, 5), // Blank page to be discarded
                    $this->makeFileEntry(4, 'Beta Inc', 5, 0, 'Different company', 'Beta header'),
                    $this->makeFileEntry(5, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups      = $result['groups'];
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Only "Acme" [1,2] and "Beta" [4,5], page 3 not in any group
        $this->assertCount(2, $groups);

        $acmeGroup = collect($groups)->firstWhere('name', 'Acme Corp');
        $betaGroup = collect($groups)->firstWhere('name', 'Beta Inc');

        $this->assertEquals([1, 2], $acmeGroup['files']);
        $this->assertEquals([4, 5], $betaGroup['files']);

        // Page 3 should not exist in file mapping
        $this->assertArrayNotHasKey(3, $fileMapping);
    }

    #[Test]
    public function first_file_is_blank_and_discarded_with_discard_config(): void
    {
        // Given: Page 1 is blank, pages 2-3 are "Acme"
        $this->setUpWithBlankPageHandling('discard');
        $this->mergeService = app(FileOrganizationMergeService::class);

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 3,
                'files' => [
                    $this->makeBlankFileEntry(1, null),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 0, 'New group', 'Acme header'),
                    $this->makeFileEntry(3, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups      = $result['groups'];
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Only "Acme" [2,3], page 1 discarded
        $this->assertCount(1, $groups);
        $this->assertEquals('Acme Corp', $groups[0]['name']);
        $this->assertEquals([2, 3], $groups[0]['files']);

        // Page 1 should not exist in file mapping
        $this->assertArrayNotHasKey(1, $fileMapping);
    }

    #[Test]
    public function multiple_blank_pages_all_discarded_with_discard_config(): void
    {
        // Given: Pages 1-2 are "Acme", pages 3-4 are blank, pages 5-6 are "Beta"
        $this->setUpWithBlankPageHandling('discard');
        $this->mergeService = app(FileOrganizationMergeService::class);

        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 6,
                'files' => [
                    $this->makeFileEntry(1, 'Acme Corp', 5, null, null, 'Acme header'),
                    $this->makeFileEntry(2, 'Acme Corp', 5, 5, 'Same letterhead', 'Acme content'),
                    $this->makeBlankFileEntry(3, 5), // First blank page
                    $this->makeBlankFileEntry(4, 5), // Second blank page
                    $this->makeFileEntry(5, 'Beta Inc', 5, 0, 'Different company', 'Beta header'),
                    $this->makeFileEntry(6, 'Beta Inc', 5, 5, 'Same letterhead', 'Beta content'),
                ],
            ],
        ]);

        // When
        $result      = $this->mergeService->mergeWindowResults($artifacts, $this->testTaskDefinition->task_runner_config);
        $groups      = $result['groups'];
        $fileMapping = $result['file_to_group_mapping'];

        // Then: Only "Acme" [1,2] and "Beta" [5,6], pages 3-4 discarded
        $this->assertCount(2, $groups);

        $acmeGroup = collect($groups)->firstWhere('name', 'Acme Corp');
        $betaGroup = collect($groups)->firstWhere('name', 'Beta Inc');

        $this->assertEquals([1, 2], $acmeGroup['files']);
        $this->assertEquals([5, 6], $betaGroup['files']);

        // Pages 3 and 4 should not exist in file mapping
        $this->assertArrayNotHasKey(3, $fileMapping);
        $this->assertArrayNotHasKey(4, $fileMapping);
    }
}
