<?php

namespace Tests\Unit\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Services\Task\DataExtraction\ContextWindowService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContextWindowServiceTest extends TestCase
{
    protected ContextWindowService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextWindowService;
    }

    /**
     * Create a collection of mock artifacts with positions.
     *
     * @param  array<int>  $positions  Array of position values
     * @return \Illuminate\Support\Collection<Artifact>
     */
    protected function createArtifacts(array $positions): \Illuminate\Support\Collection
    {
        $artifacts = [];

        foreach ($positions as $position) {
            $artifact           = new Artifact;
            $artifact->id       = $position + 100; // Use position + 100 as ID
            $artifact->position = $position;
            $artifacts[]        = $artifact;
        }

        return collect($artifacts);
    }

    #[Test]
    public function expand_with_context_returns_targets_unchanged_when_no_context_requested(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([2]); // Only page 3 (position 2)

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            0,  // No context before
            0   // No context after
        );

        $this->assertCount(1, $result);
        $this->assertEquals(102, $result->first()->id);
        $this->assertFalse($result->first()->is_context_page);
    }

    #[Test]
    public function expand_with_context_adds_pages_before_target(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([2]); // Only page 3 (position 2)

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            2,  // 2 pages before
            0   // No context after
        );

        $this->assertCount(3, $result);

        // Should have pages 1, 2, 3 (positions 0, 1, 2)
        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([0, 1, 2], $resultPositions);

        // First two should be context, last should be target
        $this->assertTrue($result[0]->is_context_page);
        $this->assertTrue($result[1]->is_context_page);
        $this->assertFalse($result[2]->is_context_page);
    }

    #[Test]
    public function expand_with_context_adds_pages_after_target(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([2]); // Only page 3 (position 2)

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            0,  // No context before
            2   // 2 pages after
        );

        $this->assertCount(3, $result);

        // Should have pages 3, 4, 5 (positions 2, 3, 4)
        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([2, 3, 4], $resultPositions);

        // First should be target, last two should be context
        $this->assertFalse($result[0]->is_context_page);
        $this->assertTrue($result[1]->is_context_page);
        $this->assertTrue($result[2]->is_context_page);
    }

    #[Test]
    public function expand_with_context_handles_edge_case_at_beginning(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([0]); // First page

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            2,  // Request 2 pages before (but only 0 available)
            1   // 1 page after
        );

        $this->assertCount(2, $result);

        // Should have pages 1, 2 (positions 0, 1)
        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([0, 1], $resultPositions);

        // First should be target, second should be context
        $this->assertFalse($result[0]->is_context_page);
        $this->assertTrue($result[1]->is_context_page);
    }

    #[Test]
    public function expand_with_context_handles_edge_case_at_end(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([4]); // Last page

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            1,  // 1 page before
            2   // Request 2 pages after (but only 0 available)
        );

        $this->assertCount(2, $result);

        // Should have pages 4, 5 (positions 3, 4)
        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([3, 4], $resultPositions);

        // First should be context, second should be target
        $this->assertTrue($result[0]->is_context_page);
        $this->assertFalse($result[1]->is_context_page);
    }

    #[Test]
    public function expand_with_context_handles_multiple_targets(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4, 5, 6]);
        $targetArtifacts = $this->createArtifacts([1, 5]); // Pages 2 and 6

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            1,  // 1 page before
            1   // 1 page after
        );

        // Target positions: 1, 5
        // Context for 1: before=0, after=2
        // Context for 5: before=4, after=6
        // Result: 0, 1, 2, 4, 5, 6
        $this->assertCount(6, $result);

        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([0, 1, 2, 4, 5, 6], $resultPositions);

        // Check is_context_page flags
        $contextFlags = $result->pluck('is_context_page')->toArray();
        // Position 0: context, 1: target, 2: context, 4: context, 5: target, 6: context
        $this->assertEquals([true, false, true, true, false, true], $contextFlags);
    }

    #[Test]
    public function expand_with_context_handles_overlapping_context_windows(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([1, 3]); // Adjacent with gap

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            1,  // 1 page before
            1   // 1 page after
        );

        // Target positions: 1, 3
        // Context for 1: before=0, after=2
        // Context for 3: before=2, after=4
        // Position 2 is context for both - should only appear once
        $this->assertCount(5, $result);

        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([0, 1, 2, 3, 4], $resultPositions);

        // Check is_context_page flags
        $contextFlags = $result->pluck('is_context_page')->toArray();
        // Position 0: context, 1: target, 2: context (not target), 3: target, 4: context
        $this->assertEquals([true, false, true, false, true], $contextFlags);
    }

    #[Test]
    public function expand_with_context_target_takes_precedence_over_context(): void
    {
        $allArtifacts    = $this->createArtifacts([0, 1, 2, 3, 4]);
        $targetArtifacts = $this->createArtifacts([1, 2]); // Adjacent targets

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            1,  // 1 page before
            1   // 1 page after
        );

        // Target positions: 1, 2
        // Context for 1: before=0, after=2 (but 2 is also a target)
        // Context for 2: before=1 (but 1 is also a target), after=3
        $this->assertCount(4, $result);

        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([0, 1, 2, 3], $resultPositions);

        // Check is_context_page flags
        $contextFlags = $result->pluck('is_context_page')->toArray();
        // Position 0: context, 1: target (not context), 2: target (not context), 3: context
        $this->assertEquals([true, false, false, true], $contextFlags);
    }

    #[Test]
    public function build_context_prompt_instructions_returns_empty_string_when_no_context(): void
    {
        $artifacts = $this->createArtifacts([0, 1, 2]);

        // Set all as targets (no context pages)
        foreach ($artifacts as $artifact) {
            $artifact->is_context_page = false;
        }

        $result = $this->service->buildContextPromptInstructions($artifacts);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function build_context_prompt_instructions_returns_instructions_with_context(): void
    {
        $artifacts = $this->createArtifacts([0, 1, 2, 3, 4]);

        // Set positions 0 and 4 as context, 1, 2, 3 as targets
        $artifacts[0]->is_context_page = true;
        $artifacts[1]->is_context_page = false;
        $artifacts[2]->is_context_page = false;
        $artifacts[3]->is_context_page = false;
        $artifacts[4]->is_context_page = true;

        $result = $this->service->buildContextPromptInstructions($artifacts);

        // Page numbers are 1-indexed for display
        $this->assertStringContainsString('CONTEXT PAGES:', $result);
        $this->assertStringContainsString('1, 5', $result); // Positions 0 and 4 are pages 1 and 5
        $this->assertStringContainsString('TARGET pages', $result);
        $this->assertStringContainsString('2, 3, 4', $result); // Positions 1, 2, 3 are pages 2, 3, 4
    }

    #[Test]
    public function get_target_count_returns_correct_count(): void
    {
        $artifacts = $this->createArtifacts([0, 1, 2, 3, 4]);

        $artifacts[0]->is_context_page = true;
        $artifacts[1]->is_context_page = false;
        $artifacts[2]->is_context_page = false;
        $artifacts[3]->is_context_page = true;
        $artifacts[4]->is_context_page = false;

        $targetCount = $this->service->getTargetCount($artifacts);

        $this->assertEquals(3, $targetCount);
    }

    #[Test]
    public function get_context_count_returns_correct_count(): void
    {
        $artifacts = $this->createArtifacts([0, 1, 2, 3, 4]);

        $artifacts[0]->is_context_page = true;
        $artifacts[1]->is_context_page = false;
        $artifacts[2]->is_context_page = false;
        $artifacts[3]->is_context_page = true;
        $artifacts[4]->is_context_page = false;

        $contextCount = $this->service->getContextCount($artifacts);

        $this->assertEquals(2, $contextCount);
    }

    #[Test]
    public function expand_with_context_maintains_position_order(): void
    {
        // Create artifacts out of order
        $allArtifacts = collect([
            $this->createSingleArtifact(3, 103),
            $this->createSingleArtifact(1, 101),
            $this->createSingleArtifact(4, 104),
            $this->createSingleArtifact(0, 100),
            $this->createSingleArtifact(2, 102),
        ]);

        $targetArtifacts = collect([$this->createSingleArtifact(2, 102)]);

        $result = $this->service->expandWithContext(
            $targetArtifacts,
            $allArtifacts,
            1,
            1
        );

        // Should be sorted by position: 1, 2, 3
        $resultPositions = $result->pluck('position')->toArray();
        $this->assertEquals([1, 2, 3], $resultPositions);
    }

    /**
     * Create a single artifact with specified position and ID.
     */
    protected function createSingleArtifact(int $position, int $id): Artifact
    {
        $artifact           = new Artifact;
        $artifact->id       = $id;
        $artifact->position = $position;

        return $artifact;
    }

    /**
     * Create an artifact with a stored file that has specific meta.
     *
     * @param  array|null  $meta  The meta to set on the stored file
     */
    protected function createArtifactWithStoredFile(?array $meta): Artifact
    {
        $artifact     = new Artifact;
        $artifact->id = 1;

        $storedFile       = new StoredFile;
        $storedFile->id   = 1;
        $storedFile->meta = $meta;

        $artifact->setRelation('storedFiles', collect([$storedFile]));

        return $artifact;
    }

    #[Test]
    public function validate_context_pages_available_passes_when_meta_has_belongs_to_previous_key(): void
    {
        // GIVEN: An artifact with a stored file that has belongs_to_previous key (even if null)
        $artifact  = $this->createArtifactWithStoredFile(['belongs_to_previous' => null]);
        $artifacts = collect([$artifact]);

        // WHEN/THEN: No exception should be thrown
        $this->service->validateContextPagesAvailable($artifacts);

        // If we got here without exception, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function validate_context_pages_available_throws_when_meta_missing_belongs_to_previous_key(): void
    {
        // GIVEN: An artifact with a stored file that does NOT have belongs_to_previous key
        $artifact  = $this->createArtifactWithStoredFile(['some_other_key' => 'value']);
        $artifacts = collect([$artifact]);

        // THEN: ValidationError should be thrown
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Context pages feature requires File Organization to be run first');

        // WHEN: Validate is called
        $this->service->validateContextPagesAvailable($artifacts);
    }

    #[Test]
    public function validate_context_pages_available_passes_with_empty_collection(): void
    {
        // GIVEN: An empty collection
        $artifacts = collect([]);

        // WHEN/THEN: No exception should be thrown
        $this->service->validateContextPagesAvailable($artifacts);

        // If we got here without exception, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function validate_context_pages_available_passes_when_no_stored_file(): void
    {
        // GIVEN: An artifact with no stored files
        $artifact     = new Artifact;
        $artifact->id = 1;
        $artifact->setRelation('storedFiles', collect([]));
        $artifacts = collect([$artifact]);

        // WHEN/THEN: No exception should be thrown
        $this->service->validateContextPagesAvailable($artifacts);

        // If we got here without exception, the test passes
        $this->assertTrue(true);
    }
}
