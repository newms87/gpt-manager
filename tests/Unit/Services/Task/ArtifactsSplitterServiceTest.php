<?php

namespace Tests\Unit\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Services\Task\ArtifactsSplitterService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ArtifactsSplitterServiceTest extends TestCase
{
    /**
     * Test splitting artifacts with no levels specified
     */
    public function testSplitWithNoLevels()
    {
        // Create test artifacts
        $artifacts = $this->createArtifactHierarchy();

        // Test splitting with default behavior (no levels)
        $result = ArtifactsSplitterService::split('default', $artifacts);

        // Should return all artifacts together in a single group
        $this->assertCount(1, $result);
        $this->assertCount(3, $result->first());
    }

    /**
     * Test splitting artifacts with only level 0 specified
     */
    public function testSplitWithOnlyLevel0()
    {
        // Create test artifacts
        $artifacts = $this->createArtifactHierarchy();

        // Test splitting with only level 0
        $result = ArtifactsSplitterService::split('default', $artifacts, [0]);

        // Should return only top-level artifacts in a single group
        $this->assertCount(1, $result);
        $this->assertCount(3, $result->first());

        // Check that all artifacts are top-level (have no parent)
        foreach($result->first() as $artifact) {
            $this->assertNull($artifact->parent_artifact_id);
        }
    }

    /**
     * Test splitting artifacts with only level 1 specified
     */
    public function testSplitWithOnlyLevel1()
    {
        // Create test artifacts with nested structure
        $artifacts = $this->createArtifactHierarchy();

        // Test splitting with only level 1
        $result = ArtifactsSplitterService::split('default', $artifacts, [1]);

        // Should return only level 1 artifacts in a single group
        $this->assertCount(1, $result);

        // Check that we have the correct number of level 1 artifacts
        // There should be 3 level 1 artifacts (2 children of artifact A, 1 child of artifact C)
        $this->assertCount(3, $result->first());

        // Check that all artifacts are level 1 (have a parent)
        foreach($result->first() as $artifact) {
            $this->assertNotNull($artifact->parent_artifact_id);
        }
    }

    /**
     * Test splitting artifacts with multiple levels specified
     */
    public function testSplitWithMultipleLevels()
    {
        // Create test artifacts with nested structure
        $artifacts = $this->createArtifactHierarchy();

        // Test splitting with levels 0 and 2
        $result = ArtifactsSplitterService::split('default', $artifacts, [0, 2]);

        // Should return top level artifacts and level 2 artifacts in a single group
        $this->assertCount(1, $result);

        // Check that we have the correct number of artifacts
        // 3 from level 0 + 2 from level 2 = 5 total
        $this->assertCount(5, $result->first());

        // Count artifacts by level
        $level0Count = 0;
        $level2Count = 0;

        foreach($result->first() as $artifact) {
            if ($artifact->parent_artifact_id === null) {
                $level0Count++;
            } else {
                // Check if this is a level 2 artifact by looking at parent's parent
                $parent = Artifact::find($artifact->parent_artifact_id);
                if ($parent && $parent->parent_artifact_id !== null) {
                    $level2Count++;
                }
            }
        }

        $this->assertEquals(3, $level0Count, 'Should have 3 artifacts from level 0');
        $this->assertEquals(2, $level2Count, 'Should have 2 artifacts from level 2');
    }

    /**
     * Test splitting artifacts by node with levels
     */
    public function testSplitByNodeWithLevels()
    {
        // Create TaskDefinition records for our artifacts
        $taskDef1 = TaskDefinition::factory()->create(['name' => 'Task Definition 1']);
        $taskDef2 = TaskDefinition::factory()->create(['name' => 'Task Definition 2']);
        $taskDef3 = TaskDefinition::factory()->create(['name' => 'Task Definition 3']);

        // Create test artifacts with nested structure and assign task definitions
        $artifacts = $this->createArtifactHierarchy($taskDef1->id, $taskDef2->id, $taskDef3->id);

        // Test splitting by node with level 1 only
        $result = ArtifactsSplitterService::split(
            ArtifactsSplitterService::ARTIFACT_SPLIT_BY_NODE,
            $artifacts,
            [1]
        );

        // Should have 2 groups (artifacts from level 1 grouped by node)
        // Note: We only have level 1 artifacts from nodes 1 and 3
        $this->assertCount(2, $result);
    }

    /**
     * Test splitting artifacts by artifact with levels
     */
    public function testSplitByArtifactWithLevels()
    {
        // Create test artifacts with nested structure
        $artifacts = $this->createArtifactHierarchy();

        // Test splitting by artifact with level 1 only
        $result = ArtifactsSplitterService::split(
            ArtifactsSplitterService::ARTIFACT_SPLIT_BY_ARTIFACT,
            $artifacts,
            [1]
        );

        // Should have 3 groups (one per level 1 artifact)
        $this->assertCount(3, $result);

        // Each group should contain exactly 1 artifact
        foreach($result as $group) {
            $this->assertCount(1, $group);
        }
    }

    /**
     * Helper method to create a hierarchy of artifacts for testing
     *
     * Creates the following structure:
     * - Artifact A (top level)
     *   - Child A1 (level 1)
     *     - Grandchild A1.1 (level 2)
     *     - Grandchild A1.2 (level 2)
     *   - Child A2 (level 1)
     * - Artifact B (top level)
     * - Artifact C (top level)
     *   - Child C1 (level 1)
     *
     * @param int|null $taskDef1Id Task definition ID for Artifact A and its children
     * @param int|null $taskDef2Id Task definition ID for Artifact B
     * @param int|null $taskDef3Id Task definition ID for Artifact C and its children
     * @return Collection
     */
    private function createArtifactHierarchy($taskDef1Id = null, $taskDef2Id = null, $taskDef3Id = null)
    {
        // Create top-level artifacts
        $artifactA = Artifact::factory()->create([
            'name'               => 'Artifact A',
            'parent_artifact_id' => null,
            'task_definition_id' => $taskDef1Id,
        ]);

        $artifactB = Artifact::factory()->create([
            'name'               => 'Artifact B',
            'parent_artifact_id' => null,
            'task_definition_id' => $taskDef2Id,
        ]);

        $artifactC = Artifact::factory()->create([
            'name'               => 'Artifact C',
            'parent_artifact_id' => null,
            'task_definition_id' => $taskDef3Id,
        ]);

        // Create level 1 children for Artifact A
        $childA1 = Artifact::factory()->create([
            'name'               => 'Child A1',
            'parent_artifact_id' => $artifactA->id,
            'task_definition_id' => $taskDef1Id,
        ]);

        $childA2 = Artifact::factory()->create([
            'name'               => 'Child A2',
            'parent_artifact_id' => $artifactA->id,
            'task_definition_id' => $taskDef1Id,
        ]);

        // Create level 1 child for Artifact C
        $childC1 = Artifact::factory()->create([
            'name'               => 'Child C1',
            'parent_artifact_id' => $artifactC->id,
            'task_definition_id' => $taskDef3Id,
        ]);

        // Create level 2 grandchildren for Child A1
        $grandchildA11 = Artifact::factory()->create([
            'name'               => 'Grandchild A1.1',
            'parent_artifact_id' => $childA1->id,
            'task_definition_id' => $taskDef1Id,
        ]);

        $grandchildA12 = Artifact::factory()->create([
            'name'               => 'Grandchild A1.2',
            'parent_artifact_id' => $childA1->id,
            'task_definition_id' => $taskDef1Id,
        ]);

        // Return collection of top-level artifacts
        return collect([$artifactA, $artifactB, $artifactC]);
    }
}
