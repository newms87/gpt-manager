<?php

namespace Tests\Feature\Services\Task\FileOrganization\Traits;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use Illuminate\Support\Collection;

/**
 * Reusable helpers for testing the new FileOrganization algorithm with sliding windows.
 *
 * This trait provides helper methods to:
 * - Set up FileOrganization testing infrastructure (agent, task definition, task run)
 * - Create window artifacts with the new algorithm's data structure
 * - Create file entries with belongs_to_previous and group_name_confidence
 * - Assert expected group merging outcomes
 *
 * Usage:
 * 1. Use this trait in your test class
 * 2. Call setUpFileOrganization() in setUp() after setUpTeam()
 * 3. Use helper methods to create window artifacts and make assertions
 */
trait FileOrganizationTestHelpers
{
    /**
     * Test agent instance for FileOrganization tasks.
     */
    protected Agent $testAgent;

    /**
     * Test task definition configured for FileOrganization.
     */
    protected TaskDefinition $testTaskDefinition;

    /**
     * Test task run instance.
     */
    protected TaskRun $testTaskRun;

    /**
     * Set up the basic FileOrganization testing infrastructure.
     *
     * Call this in setUp() after setUpTeam().
     *
     * @param  array  $taskRunnerConfig  Override default task runner configuration
     */
    protected function setUpFileOrganization(array $taskRunnerConfig = []): void
    {
        // Create an agent for testing with a valid model
        $this->testAgent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'gpt-5-mini', // Use valid model from config
        ]);

        // Merge provided config with defaults
        $finalConfig = array_merge($this->getDefaultTaskRunnerConfig(), $taskRunnerConfig);

        // Create task definition with FileOrganizationTaskRunner
        $this->testTaskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'File Organization Test',
            'task_runner_name'   => FileOrganizationTaskRunner::RUNNER_NAME,
            'task_runner_config' => $finalConfig,
            'agent_id'           => $this->testAgent->id,
        ]);

        // Create a task run for testing
        $this->testTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->testTaskDefinition->id,
        ]);
    }

    /**
     * Create a mock window artifact with the new flat format.
     *
     * The new format expects:
     * - Flat files array with group_name on each file
     * - group_name_confidence (1-5)
     * - belongs_to_previous (0-5 or null)
     * - group_explanation and belongs_to_previous_reason
     *
     * @param  int  $windowStart  Starting page number
     * @param  int  $windowEnd  Ending page number
     * @param  array  $files  Array of file data with structure:
     *                        [
     *                        'page_number' => 1,
     *                        'group_name' => 'Acme Corp',
     *                        'group_name_confidence' => 5,
     *                        'belongs_to_previous' => null, // null for first, 0-5 for others
     *                        'belongs_to_previous_reason' => 'Same letterhead',
     *                        'group_explanation' => 'Clear header visible'
     *                        ]
     */
    protected function createWindowArtifact(int $windowStart, int $windowEnd, array $files): Artifact
    {
        // Create window_files metadata
        $windowFiles = array_map(function ($file) {
            return [
                'file_id'     => $file['page_number'], // Use page_number as file_id for simplicity
                'page_number' => $file['page_number'],
            ];
        }, $files);

        return Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'files' => $files, // NEW flat format: files array directly
            ],
            'meta' => [
                'window_start' => $windowStart,
                'window_end'   => $windowEnd,
                'window_files' => $windowFiles,
            ],
        ]);
    }

    /**
     * Create multiple window artifacts from a simplified definition.
     *
     * Useful for setting up overlapping windows quickly.
     *
     * @param  array  $windows  Array of window definitions:
     *                          [
     *                          ['start' => 1, 'end' => 5, 'files' => [...]],
     *                          ['start' => 3, 'end' => 7, 'files' => [...]],
     *                          ]
     */
    protected function createWindowArtifacts(array $windows): Collection
    {
        $artifacts = [];

        foreach ($windows as $window) {
            $artifacts[] = $this->createWindowArtifact(
                $window['start'],
                $window['end'],
                $window['files']
            );
        }

        return new Collection($artifacts);
    }

    /**
     * Create a file entry for use in window artifacts.
     *
     * Provides defaults for optional fields.
     */
    protected function makeFileEntry(
        int $pageNumber,
        string $groupName,
        int $groupConfidence = 5,
        ?int $belongsToPrevious = null,
        ?string $belongsToPreviousReason = null,
        ?string $groupExplanation = null
    ): array {
        $file = [
            'page_number'           => $pageNumber,
            'group_name'            => $groupName,
            'group_name_confidence' => $groupConfidence,
            'belongs_to_previous'   => $belongsToPrevious,
        ];

        // Add optional fields if provided
        if ($belongsToPreviousReason !== null) {
            $file['belongs_to_previous_reason'] = $belongsToPreviousReason;
        }

        if ($groupExplanation !== null) {
            $file['group_explanation'] = $groupExplanation;
        }

        return $file;
    }

    /**
     * Create a blank/separator page entry.
     *
     * Blank pages are identified by having an empty string group_name ('').
     * They typically have low confidence and high belongs_to_previous.
     */
    protected function makeBlankFileEntry(
        int $pageNumber,
        ?int $belongsToPrevious = 5
    ): array {
        return [
            'page_number'                => $pageNumber,
            'group_name'                 => '', // Empty string identifies blank pages
            'group_name_confidence'      => 1,
            'belongs_to_previous'        => $belongsToPrevious,
            'belongs_to_previous_reason' => 'Blank separator page',
            'group_explanation'          => 'Blank page',
        ];
    }

    /**
     * Assert that the final groups match expected structure.
     *
     * @param  array  $expectedGroups  Array of ['name' => 'Group Name', 'files' => [1,2,3]]
     * @param  array  $actualGroups  The groups returned from merge
     */
    protected function assertGroupsMatch(array $expectedGroups, array $actualGroups): void
    {
        $this->assertCount(
            count($expectedGroups),
            $actualGroups,
            'Expected ' . count($expectedGroups) . ' groups, got ' . count($actualGroups)
        );

        foreach ($expectedGroups as $expected) {
            $actual = collect($actualGroups)->firstWhere('name', $expected['name']);

            $this->assertNotNull(
                $actual,
                "Expected group '{$expected['name']}' not found in actual groups"
            );

            $this->assertEquals(
                $expected['files'],
                $actual['files'],
                "Group '{$expected['name']}' has incorrect files"
            );
        }
    }

    /**
     * Assert a specific file is in a specific group.
     */
    protected function assertFileInGroup(int $pageNumber, string $groupName, array $fileMapping): void
    {
        $this->assertArrayHasKey(
            $pageNumber,
            $fileMapping,
            "Page $pageNumber not found in file mapping"
        );

        $this->assertEquals(
            $groupName,
            $fileMapping[$pageNumber]['group_name'],
            "Page $pageNumber expected in group '$groupName', found in '{$fileMapping[$pageNumber]['group_name']}'"
        );
    }

    /**
     * Assert file has expected confidence.
     */
    protected function assertFileConfidence(int $pageNumber, int $expectedConfidence, array $fileMapping): void
    {
        $this->assertArrayHasKey(
            $pageNumber,
            $fileMapping,
            "Page $pageNumber not found in file mapping"
        );

        $this->assertEquals(
            $expectedConfidence,
            $fileMapping[$pageNumber]['confidence'],
            "Page $pageNumber expected confidence $expectedConfidence, got {$fileMapping[$pageNumber]['confidence']}"
        );
    }

    /**
     * Get default task runner config with new algorithm settings.
     */
    protected function getDefaultTaskRunnerConfig(): array
    {
        return [
            'comparison_window_size'       => 5,
            'comparison_window_overlap'    => 2,
            'group_confidence_threshold'   => 3,
            'adjacency_boundary_threshold' => 2,
            'max_sliding_iterations'       => 3,
            'name_similarity_threshold'    => 0.7,
            'blank_page_handling'          => 'join_previous',
        ];
    }
}
