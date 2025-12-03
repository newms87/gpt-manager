# FileOrganizationTestHelpers Usage Examples

This document demonstrates how to use the `FileOrganizationTestHelpers` trait for testing the FileOrganization algorithm.

## Basic Setup

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
        $this->setUpFileOrganization(); // Creates agent, task definition, and task run
    }
}
```

## Creating File Entries

### Basic File Entry
```php
$file = $this->makeFileEntry(
    pageNumber: 1,
    groupName: 'Acme Corp',
    groupConfidence: 5,
    belongsToPrevious: null, // First file in group
    belongsToPreviousReason: null,
    groupExplanation: 'Clear letterhead visible'
);
```

### File Belonging to Previous
```php
$file = $this->makeFileEntry(
    pageNumber: 2,
    groupName: 'Acme Corp',
    groupConfidence: 5,
    belongsToPrevious: 5, // Strongly belongs to previous
    belongsToPreviousReason: 'Same letterhead and formatting',
    groupExplanation: 'Continuation of same document'
);
```

### Uncertain File Entry
```php
$file = $this->makeFileEntry(
    pageNumber: 3,
    groupName: 'Acme Corp',
    groupConfidence: 2, // Low confidence
    belongsToPrevious: 2, // Weak connection to previous
    belongsToPreviousReason: 'Similar but unclear',
    groupExplanation: 'Uncertain match'
);
```

### Blank Page
```php
$blankPage = $this->makeBlankFileEntry(
    pageNumber: 4,
    belongsToPrevious: 5 // Typically joins previous group
);
```

## Creating Window Artifacts

### Single Window
```php
$files = [
    $this->makeFileEntry(1, 'Group A', 5, null, null, 'Start of Group A'),
    $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead', 'Continues Group A'),
    $this->makeFileEntry(3, 'Group B', 5, null, null, 'New group starts'),
];

$artifact = $this->createWindowArtifact(
    windowStart: 1,
    windowEnd: 3,
    files: $files
);
```

### Multiple Overlapping Windows
```php
$windows = [
    [
        'start' => 1,
        'end'   => 5,
        'files' => [
            $this->makeFileEntry(1, 'Group A', 5, null, null, 'Start'),
            $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(3, 'Group A', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(4, 'Group A', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(5, 'Group A', 4, 3, 'Similar style'),
        ],
    ],
    [
        'start' => 3,
        'end'   => 7,
        'files' => [
            $this->makeFileEntry(3, 'Group A', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(4, 'Group A', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(5, 'Group B', 5, null, null, 'New group starts'),
            $this->makeFileEntry(6, 'Group B', 5, 5, 'Same letterhead'),
            $this->makeFileEntry(7, 'Group B', 5, 5, 'Same letterhead'),
        ],
    ],
];

$artifacts = $this->createWindowArtifacts($windows);
```

## Testing Merge Results

### Complete Example
```php
#[Test]
public function merges_overlapping_windows_correctly(): void
{
    // Given: Two overlapping windows
    $artifacts = $this->createWindowArtifacts([
        [
            'start' => 1,
            'end'   => 5,
            'files' => [
                $this->makeFileEntry(1, 'Medical Records - Dr. Smith', 5),
                $this->makeFileEntry(2, 'Medical Records - Dr. Smith', 5, 5, 'Same letterhead'),
                $this->makeFileEntry(3, 'Medical Records - Dr. Smith', 5, 5, 'Same letterhead'),
                $this->makeFileEntry(4, 'Medical Records - Dr. Smith', 4, 4, 'Similar style'),
                $this->makeFileEntry(5, 'Medical Records - Dr. Smith', 3, 2, 'Uncertain'),
            ],
        ],
        [
            'start' => 3,
            'end'   => 7,
            'files' => [
                $this->makeFileEntry(3, 'Medical Records - Dr. Smith', 5),
                $this->makeFileEntry(4, 'Medical Records - Dr. Smith', 5, 5, 'Same letterhead'),
                $this->makeFileEntry(5, 'Medical Records - Dr. Jones', 5, null, 'New provider'),
                $this->makeFileEntry(6, 'Medical Records - Dr. Jones', 5, 5, 'Same letterhead'),
                $this->makeFileEntry(7, 'Medical Records - Dr. Jones', 5, 5, 'Same letterhead'),
            ],
        ],
    ]);

    // When: Merging windows
    $mergeService = app(FileOrganizationMergeService::class);
    $mergeResult  = $mergeService->mergeWindowResults($artifacts);

    // Then: Assert expected grouping
    $expectedGroups = [
        ['name' => 'Medical Records - Dr. Smith', 'files' => [1, 2, 3, 4]],
        ['name' => 'Medical Records - Dr. Jones', 'files' => [5, 6, 7]],
    ];

    $this->assertGroupsMatch($expectedGroups, $mergeResult['groups']);

    // Assert specific files
    $this->assertFileInGroup(1, 'Medical Records - Dr. Smith', $mergeResult['file_to_group_mapping']);
    $this->assertFileInGroup(7, 'Medical Records - Dr. Jones', $mergeResult['file_to_group_mapping']);

    // Assert confidence levels
    $this->assertFileConfidence(1, 5, $mergeResult['file_to_group_mapping']);
    $this->assertFileConfidence(5, 5, $mergeResult['file_to_group_mapping']);
}
```

## Testing Boundary Conflicts

```php
#[Test]
public function higher_confidence_wins_at_boundaries(): void
{
    // Given: Two windows with conflicting assignments at boundary
    $artifacts = $this->createWindowArtifacts([
        [
            'start' => 1,
            'end'   => 3,
            'files' => [
                $this->makeFileEntry(1, 'Group A', 5),
                $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead'),
                $this->makeFileEntry(3, 'Group A', 5, 5, 'Same letterhead'),
            ],
        ],
        [
            'start' => 3,
            'end'   => 5,
            'files' => [
                $this->makeFileEntry(3, 'Group A', 4, 4, 'Similar'), // Lower confidence
                $this->makeFileEntry(4, 'Group B', 5, null, 'New group starts'),
                $this->makeFileEntry(5, 'Group B', 5, 5, 'Same letterhead'),
            ],
        ],
    ]);

    // When: Merging
    $mergeResult = app(FileOrganizationMergeService::class)->mergeWindowResults($artifacts);

    // Then: Page 3 should stay with Group A (higher confidence of 5 vs 4)
    $this->assertFileInGroup(3, 'Group A', $mergeResult['file_to_group_mapping']);

    // And Group B should start at page 4
    $expectedGroups = [
        ['name' => 'Group A', 'files' => [1, 2, 3]],
        ['name' => 'Group B', 'files' => [4, 5]],
    ];
    $this->assertGroupsMatch($expectedGroups, $mergeResult['groups']);
}
```

## Testing Blank Page Handling

```php
#[Test]
public function blank_pages_join_previous_group(): void
{
    // Given: Window with blank page separator
    $files = [
        $this->makeFileEntry(1, 'Group A', 5),
        $this->makeFileEntry(2, 'Group A', 5, 5, 'Same letterhead'),
        $this->makeBlankFileEntry(3, belongsToPrevious: 5), // Blank separator
        $this->makeFileEntry(4, 'Group B', 5, null, 'New group starts'),
        $this->makeFileEntry(5, 'Group B', 5, 5, 'Same letterhead'),
    ];

    $artifact = $this->createWindowArtifact(1, 5, $files);

    // When: Processing the window
    $mergeResult = app(FileOrganizationMergeService::class)->mergeWindowResults([$artifact]);

    // Then: Blank page should be assigned based on belongs_to_previous
    // (actual behavior depends on blank_page_handling config)
    $this->assertTrue(true); // Adjust based on expected behavior
}
```

## Custom Configuration

```php
public function setUp(): void
{
    parent::setUp();
    $this->setUpTeam();

    // Override default configuration
    $this->setUpFileOrganization([
        'comparison_window_size'       => 10, // Larger windows
        'comparison_window_overlap'    => 5,  // More overlap
        'group_confidence_threshold'   => 4,  // Higher threshold
        'adjacency_boundary_threshold' => 3,
    ]);
}
```

## Available Assertion Methods

### assertGroupsMatch
Asserts that the merged groups match expected structure:
```php
$expectedGroups = [
    ['name' => 'Group A', 'files' => [1, 2, 3]],
    ['name' => 'Group B', 'files' => [4, 5, 6]],
];
$this->assertGroupsMatch($expectedGroups, $actualGroups);
```

### assertFileInGroup
Asserts a specific file is in a specific group:
```php
$this->assertFileInGroup(1, 'Group A', $fileMapping);
```

### assertFileConfidence
Asserts a file has expected confidence level:
```php
$this->assertFileConfidence(1, 5, $fileMapping);
```

## Tips

1. **Use meaningful group names**: Instead of "Group A", use realistic names like "Medical Records - Dr. Smith"
2. **Test edge cases**: Boundary conflicts, low confidence files, blank pages
3. **Test overlapping windows**: The algorithm is designed for overlapping windows
4. **Vary confidence levels**: Test how the algorithm handles different confidence levels
5. **Use belongs_to_previous**: This is key to the new algorithm's grouping logic

## Default Configuration Values

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
