<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\Task\Runners\SequentialCategoryMatcherTaskRunner;
use Tests\AuthenticatedTestCase;

class SequentialCategoryMatcherTaskRunnerTest extends AuthenticatedTestCase
{
    /**
     * Base case: One category at the beginning with remaining artifacts having empty categories
     */
    public function test_resolveCategoryGroups_singleCategoryWithEmptyCategories()
    {
        // Given
        $artifacts = Artifact::factory()->count(3)->create();

        $artifactCategoryMap = [
            0 => 'Category A',
        ];

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => $artifactCategoryMap[$index] ?? null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
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
        $artifacts = Artifact::factory()->count(6)->create();

        $artifactCategoryMap = [
            0 => 'Category A',
            3 => 'Category B',
            5 => 'Category A',
        ];

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => $artifactCategoryMap[$index] ?? null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
            ->resolveCategoryGroups($artifacts);


        foreach($categoryGroups as $index => $artifacts) {
            $positions = collect($artifacts)->pluck('position')->implode(',');
            dump("Group $index: $positions");

        }
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
        $artifacts = Artifact::factory()->count(5)->create();

        $artifactCategoryMap = [
            0 => 'Category A',
            2 => '__exclude',
            4 => 'Category B',
        ];

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => $artifactCategoryMap[$index] ?? null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
            ->resolveCategoryGroups($artifacts);

        // Then
        $this->assertCount(2, $categoryGroups);

        // First group (Category A)
        $this->assertCount(2, $categoryGroups[0]);
        $this->assertEquals(0, $categoryGroups[0][0]->position);
        $this->assertEquals(1, $categoryGroups[0][1]->position);

        // Second group (Category B)
        $this->assertCount(1, $categoryGroups[1]);
        $this->assertEquals(4, $categoryGroups[1][0]->position);
    }

    /**
     * Test with empty input
     */
    public function test_resolveCategoryGroups_emptyInput()
    {
        // Given
        $artifacts = [];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
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
        $artifacts = Artifact::factory()->count(4)->create();

        $artifactCategoryMap = [
            0 => 'Category A',
            1 => 'Category A',
            2 => 'Category B',
            3 => 'Category C',
        ];

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => $artifactCategoryMap[$index] ?? null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
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
        $artifacts = Artifact::factory()->count(3)->create();

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
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
        $artifacts = Artifact::factory()->count(4)->create();

        $artifactCategoryMap = [
            0 => 'Category A',
            1 => 'Category B',
            2 => null,
            3 => 'Category C',
        ];

        foreach($artifacts as $index => $artifact) {
            $artifact->position = $index;
            $artifact->meta     = [
                'classification' => [
                    'category' => $artifactCategoryMap[$index] ?? null,
                ],
            ];
            $artifact->save();
        }

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'category' => ['type' => 'string'],
            ],
        ];

        // When
        $categoryGroups = app(SequentialCategoryMatcherTaskRunner::class)
            ->resolveFragmentSelector($fragmentSelector)
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
