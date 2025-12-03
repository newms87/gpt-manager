# FileOrganizationTestHelpers Trait

A comprehensive testing utility trait for the FileOrganization algorithm with sliding windows.

## Overview

The `FileOrganizationTestHelpers` trait provides reusable helper methods for testing the new FileOrganization algorithm. It simplifies the creation of test data, window artifacts, and assertions for merge result validation.

## Key Features

- **Infrastructure Setup**: Automatically creates Agent, TaskDefinition, and TaskRun for testing
- **File Entry Creation**: Helper methods for creating file entries with the new algorithm's data structure
- **Window Artifact Creation**: Simplified creation of window artifacts with overlapping ranges
- **Assertion Helpers**: Specialized assertions for validating merge results
- **Configurable Defaults**: Override default configuration values per test

## Files

- `FileOrganizationTestHelpers.php` - The main trait with all helper methods
- `USAGE_EXAMPLE.md` - Comprehensive usage examples and patterns
- `README.md` - This file

## Quick Start

```php
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;
use Tests\Feature\Services\Task\FileOrganization\Traits\FileOrganizationTestHelpers;

class MyFileOrganizationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;
    use FileOrganizationTestHelpers;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->setUpFileOrganization();
    }

    #[Test]
    public function my_test(): void
    {
        // Create window artifacts
        $artifacts = $this->createWindowArtifacts([
            [
                'start' => 1,
                'end'   => 5,
                'files' => [
                    $this->makeFileEntry(1, 'Group A', 5),
                    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead'),
                    // ...
                ],
            ],
        ]);

        // Test merge results
        $mergeResult = app(FileOrganizationMergeService::class)->mergeWindowResults($artifacts);

        // Assert expected grouping
        $this->assertGroupsMatch([
            ['name' => 'Group A', 'files' => [1, 2]],
        ], $mergeResult['groups']);
    }
}
```

## Available Methods

### Setup Methods

- `setUpFileOrganization(array $taskRunnerConfig = [])` - Set up testing infrastructure
- `getDefaultTaskRunnerConfig()` - Get default configuration values

### File Entry Helpers

- `makeFileEntry(...)` - Create a file entry with all new algorithm fields
- `makeBlankFileEntry(...)` - Create a blank/separator page entry

### Artifact Creation

- `createWindowArtifact(int $windowStart, int $windowEnd, array $files)` - Create a single window artifact
- `createWindowArtifacts(array $windows)` - Create multiple overlapping window artifacts

### Assertion Helpers

- `assertGroupsMatch(array $expectedGroups, array $actualGroups)` - Assert merged groups match expected structure
- `assertFileInGroup(int $pageNumber, string $groupName, array $fileMapping)` - Assert a file is in a specific group
- `assertFileConfidence(int $pageNumber, int $expectedConfidence, array $fileMapping)` - Assert a file has expected confidence

## Properties

After calling `setUpFileOrganization()`, these properties are available:

- `$this->testAgent` - Agent instance for FileOrganization tasks
- `$this->testTaskDefinition` - TaskDefinition configured for FileOrganization
- `$this->testTaskRun` - TaskRun instance for testing

## New Algorithm Data Structure

The trait creates artifacts with the new algorithm's expected structure:

```php
[
    'groups' => [
        [
            'name' => 'Group Name',
            'description' => 'Group description',
            'files' => [
                [
                    'page_number' => 1,
                    'confidence' => 5,
                    'explanation' => 'Why this file belongs here',
                    'belongs_to_previous' => null, // null for first, 0-5 for others
                    'belongs_to_previous_reason' => 'Why it belongs to previous',
                ],
                // ...
            ],
        ],
    ],
]
```

## Testing Patterns

### Test Overlapping Windows
```php
$artifacts = $this->createWindowArtifacts([
    ['start' => 1, 'end' => 5, 'files' => [...]],
    ['start' => 3, 'end' => 7, 'files' => [...]],
]);
```

### Test Boundary Conflicts
```php
// Window 1: Page 3 has confidence 5
// Window 2: Page 3 has confidence 4
// Result: Page 3 should stay with higher confidence group
```

### Test Blank Pages
```php
$this->makeBlankFileEntry(pageNumber: 5, belongsToPrevious: 5);
```

### Test Low Confidence Files
```php
$this->makeFileEntry(
    pageNumber: 3,
    groupName: 'Uncertain Group',
    groupConfidence: 2, // Low confidence
    belongsToPrevious: 2
);
```

## Default Configuration

```php
[
    'comparison_window_size'       => 5,
    'comparison_window_overlap'    => 2,
    'group_confidence_threshold'   => 3,
    'adjacency_boundary_threshold' => 2,
    'max_sliding_iterations'       => 3,
    'name_similarity_threshold'    => 0.7,
    'blank_page_handling'          => 'join_previous',
]
```

## Examples

See `USAGE_EXAMPLE.md` for comprehensive usage examples including:
- Basic file entry creation
- Window artifact creation
- Testing merge results
- Boundary conflict testing
- Blank page handling
- Custom configuration

## Testing the Trait Itself

Run the meta-test to verify the trait works correctly:

```bash
./vendor/bin/sail test --filter=FileOrganizationTestHelpersTest
```

## Related Files

- `app/Services/Task/FileOrganizationMergeService.php` - The merge service being tested
- `app/Services/Task/Runners/FileOrganizationTaskRunner.php` - The task runner
- `tests/Feature/Services/Task/FileOrganizationTaskRunnerTest.php` - Example usage patterns
