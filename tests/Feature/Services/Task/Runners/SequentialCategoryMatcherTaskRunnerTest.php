<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\Task\Runners\SequentialCategoryMatcherTaskRunner;
use Tests\AuthenticatedTestCase;

class SequentialCategoryMatcherTaskRunnerTest extends AuthenticatedTestCase
{
    static array $fragmentSelector = [
        'type'     => 'object',
        'children' => [
            'category' => ['type' => 'string'],
        ],
    ];

    private function printGroups($categoryGroups)
    {
        foreach($categoryGroups as $index => $artifacts) {
            $positions = collect($artifacts)->pluck('position')->implode(',');
            \Log::debug("Group $index: $positions");
        }
    }

    private function makeArtifactsWithCategories(array $categoryMap): array
    {
        $artifacts = [];
        foreach($categoryMap as $position => $category) {
            $artifact    = Artifact::factory()->create([
                'position' => $position,
                'meta'     => [
                    'classification' => [
                        'category' => $category,
                    ],
                ],
            ]);
            $artifacts[] = $artifact;
        }

        return $artifacts;
    }

    /**
     * Base case: One category at the beginning with remaining artifacts having empty categories
     */
    public function test_resolveCategoryGroups_singleCategoryWithEmptyCategories()
    {
        // Given
        $artifactCategoryMap = [
            0 => 'Category A',
            1 => null,
            2 => null,
        ];
        $artifacts           = $this->makeArtifactsWithCategories($artifactCategoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(1, $categoryGroups);
        $this->assertCount(3, $categoryGroups[0]);
        $this->assertEquals(0, $categoryGroups[0][0]->position);
        $this->assertEquals(1, $categoryGroups[0][1]->position);
        $this->assertEquals(2, $categoryGroups[0][2]->position);
    }

    /**
     * Test with multiple category transitions
     */
    public function test_resolveCategoryGroups_multipleCategoryTransitions()
    {
        // Given
        $artifactCategoryMap = [
            0 => 'Category A',
            1 => null,
            2 => null,
            3 => 'Category B',
            4 => null,
            5 => 'Category A',
        ];

        $artifacts = $this->makeArtifactsWithCategories($artifactCategoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(2, $categoryGroups);

        // First group (Category A to Category B)
        $this->assertCount(4, $categoryGroups[0]);
        $this->assertEquals(0, $categoryGroups[0][0]->position);
        $this->assertEquals(3, $categoryGroups[0][3]->position);

        // Second group (Category B to Category A)
        $this->assertCount(3, $categoryGroups[1]);
        $this->assertEquals(3, $categoryGroups[1][0]->position);
        $this->assertEquals(5, $categoryGroups[1][2]->position);
    }

    /**
     * Test with excluded categories
     */
    public function test_resolveCategoryGroups_excludedCategories()
    {
        // Given
        $artifactCategoryMap = [
            0 => 'Category A',
            1 => null,
            2 => '__exclude',
            3 => null,
            4 => 'Category B',
        ];

        $artifacts = $this->makeArtifactsWithCategories($artifactCategoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(1, $categoryGroups);

        // First group (Category A)
        $this->assertCount(4, $categoryGroups[0]);
        $this->assertEquals(0, $categoryGroups[0][0]->position);
        $this->assertEquals(1, $categoryGroups[0][1]->position);
        $this->assertEquals(3, $categoryGroups[0][2]->position);
        $this->assertEquals(4, $categoryGroups[0][3]->position);
    }

    /**
     * Test with empty input
     */
    public function test_resolveCategoryGroups_emptyInput()
    {
        // Given
        $artifacts = [];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertEmpty($categoryGroups);
    }

    /**
     * Test when all artifacts have categories (no empty categories)
     */
    public function test_resolveCategoryGroups_allArtifactsHaveCategories()
    {
        // Given
        $artifactCategoryMap = [
            0 => 'Category A',
            1 => 'Category A',
            2 => 'Category B',
            3 => 'Category C',
        ];

        $artifacts = $this->makeArtifactsWithCategories($artifactCategoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(0, $categoryGroups, "There should be no category groups when all artifacts have categories");
    }

    /**
     * Test when all artifacts have empty categories
     */
    public function test_resolveCategoryGroups_allEmptyCategories()
    {
        // Given
        $categoryMap = [
            0 => null,
            1 => null,
            2 => null,
            3 => null,
        ];

        $artifacts = $this->makeArtifactsWithCategories($categoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(0, $categoryGroups, "There should be no category groups when all artifacts have empty categories");
    }

    /**
     * Test with consecutive categories (no empty categories between transitions)
     */
    public function test_resolveCategoryGroups_consecutiveCategories()
    {
        // Given
        $artifactCategoryMap = [
            0 => 'Category A',
            1 => 'Category B',
            2 => null,
            3 => 'Category C',
        ];

        $artifacts = $this->makeArtifactsWithCategories($artifactCategoryMap);

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector(static::$fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(1, $categoryGroups);

        // Group (Category B to Category C with empty in between)
        $this->assertCount(3, $categoryGroups[0]);
        $this->assertEquals(1, $categoryGroups[0][0]->position);
        $this->assertEquals(2, $categoryGroups[0][1]->position);
        $this->assertEquals(3, $categoryGroups[0][2]->position);
    }
}
