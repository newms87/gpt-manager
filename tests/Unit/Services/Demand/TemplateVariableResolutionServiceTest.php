<?php

namespace Tests\Unit\Services\Demand;

use App\Models\Demand\TemplateVariable;
use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Services\Demand\TemplateVariableResolutionService;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateVariableResolutionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // ARTIFACT CATEGORY FILTERING TESTS
    // ==========================================

    public function test_resolveVariable_withMatchingArtifactableType_includesArtifact(): void
    {
        // Given - Create artifact with category in meta.__category
        $artifact = Artifact::factory()->create([
            'name'         => 'Medical Report',
            'text_content' => 'Patient diagnosis details',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Use category string, not class name
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ', ',
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('Patient diagnosis details', $result);
    }

    public function test_resolveVariable_withMultipleArtifactableTypesFilter_includesMatchingArtifacts(): void
    {
        // Given - Create artifacts with different categories in meta.__category
        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskRun Artifact',
            'text_content' => 'TaskRun content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskProcess Artifact',
            'text_content' => 'TaskProcess content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input', 'output'], // Use category strings
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' | ',
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should include both
        $this->assertEquals('TaskRun content | TaskProcess content', $result);
    }

    public function test_resolveVariable_withNonMatchingArtifactableType_excludesArtifact(): void
    {
        // Given - Create artifact with 'output' category but filter by 'input'
        $artifact = Artifact::factory()->create([
            'name'         => 'TaskProcess Artifact',
            'text_content' => 'TaskProcess content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Filter by 'input' category only
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ', ',
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should exclude artifact with non-matching category
        $this->assertEquals('', $result);
    }

    public function test_resolveVariable_withoutCategories_includesAllArtifacts(): void
    {
        // Given - No category filter specified
        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'Content 1',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Content 2',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Artifact 3',
            'text_content' => 'Content 3',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null, // No filter - should include all
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' | ',
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should include all artifacts regardless of category
        $this->assertEquals('Content 1 | Content 2 | Content 3', $result);
    }

    public function test_resolveVariable_withEmptyCategoriesArray_includesAllArtifacts(): void
    {
        // Given - Empty array means no filter
        $artifact1 = Artifact::factory()->create([
            'name'         => 'Report 1',
            'text_content' => 'Content 1',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Report 2',
            'text_content' => 'Content 2',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => [], // Empty array - should include all
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => '; ',
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('Content 1; Content 2', $result);
    }

    public function test_resolveVariable_withMultipleTypesFilter_filtersCorrectly(): void
    {
        // Given - Create artifacts with different categories
        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskRun Artifact',
            'text_content' => 'TaskRun content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskProcess Artifact 1',
            'text_content' => 'TaskProcess content 1',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'TaskProcess Artifact 2',
            'text_content' => 'TaskProcess content 2',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifact4 = Artifact::factory()->create([
            'name'         => 'Other Artifact',
            'text_content' => 'Other content',
            'team_id'      => $this->user->currentTeam->id,
            // No category - should be excluded
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input', 'output'], // Filter by categories
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' | ',
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3, $artifact4]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should only include input and output category artifacts
        $this->assertEquals('TaskRun content | TaskProcess content 1 | TaskProcess content 2', $result);
    }

    public function test_resolveVariable_withArtifactNotAssociatedWithAnyArtifactable_excludesArtifact(): void
    {
        // Given
        $taskRun = TaskRun::factory()->create();

        $artifact = Artifact::factory()->create([
            'name'         => 'Unassociated Artifact',
            'text_content' => 'Some content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        // Do NOT associate artifact with anything - it stands alone

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Filter by category
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ', ',
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should exclude artifact with no artifactable associations
        $this->assertEquals('', $result);
    }

    // ==========================================
    // CONTENT EXTRACTION PRIORITY TESTS
    // ==========================================

    public function test_resolveVariable_usesTextContentWhenAvailable(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact = Artifact::factory()->create([
            'name'         => 'Artifact Name',
            'text_content' => 'This is the text content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should use text_content, not name
        $this->assertEquals('This is the text content', $result);
    }

    public function test_resolveVariable_usesNameAsFallbackWhenTextContentIsNull(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact = Artifact::factory()->create([
            'name'         => 'Fallback Artifact Name',
            'text_content' => null,
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should fall back to name
        $this->assertEquals('Fallback Artifact Name', $result);
    }

    public function test_resolveVariable_usesNameAsFallbackWhenTextContentIsEmpty(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact = Artifact::factory()->create([
            'name'         => 'Empty Text Artifact',
            'text_content' => '',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should fall back to name when text_content is empty string
        $this->assertEquals('Empty Text Artifact', $result);
    }

    public function test_resolveVariable_withMultipleArtifacts_usesCorrectPriorityForEach(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' | ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Name 1',
            'text_content' => 'Text Content 1',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Name 2',
            'text_content' => null,
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Name 3',
            'text_content' => 'Text Content 3',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - First uses text_content, second falls back to name, third uses text_content
        $this->assertEquals('Text Content 1 | Name 2 | Text Content 3', $result);
    }

    public function test_resolveVariable_withWhitespaceOnlyTextContent_usesWhitespace(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact = Artifact::factory()->create([
            'name'         => 'Whitespace Artifact',
            'text_content' => '   ',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Whitespace is considered truthy (non-empty string), so it's used as-is
        $this->assertEquals('   ', $result);
    }

    // ==========================================
    // MULTI-VALUE STRATEGY TESTS
    // ==========================================

    public function test_resolveVariable_withJoinStrategy_joinsAllValues(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' | ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'Content 1',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Content 2',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Artifact 3',
            'text_content' => 'Content 3',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('Content 1 | Content 2 | Content 3', $result);
    }

    public function test_resolveVariable_withJoinStrategy_usesCustomSeparator(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ' >> ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'Value A',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Value B',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('Value A >> Value B', $result);
    }

    public function test_resolveVariable_withFirstStrategy_returnsOnlyFirstValue(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'First Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Second Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Artifact 3',
            'text_content' => 'Third Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('First Content', $result);
    }

    public function test_resolveVariable_withUniqueStrategy_removesDuplicates(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator'      => ', ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'Duplicate Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Unique Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Artifact 3',
            'text_content' => 'Duplicate Content',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact4 = Artifact::factory()->create([
            'name'         => 'Artifact 4',
            'text_content' => 'Another Unique',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3, $artifact4]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should remove duplicate "Duplicate Content"
        $this->assertEquals('Duplicate Content, Unique Content, Another Unique', $result);
    }

    public function test_resolveVariable_withUniqueStrategy_preservesOrderOfFirstOccurrence(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator'      => ' | ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'Artifact 1',
            'text_content' => 'Alpha',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'Artifact 2',
            'text_content' => 'Beta',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'Artifact 3',
            'text_content' => 'Alpha',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact4 = Artifact::factory()->create([
            'name'         => 'Artifact 4',
            'text_content' => 'Gamma',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact5 = Artifact::factory()->create([
            'name'         => 'Artifact 5',
            'text_content' => 'Beta',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3, $artifact4, $artifact5]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should preserve order: Alpha, Beta, Gamma (first occurrence)
        $this->assertEquals('Alpha | Beta | Gamma', $result);
    }

    public function test_resolveVariable_withEmptyArtifacts_returnsEmptyString(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ', ',
        ]);

        $artifacts = collect([]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('', $result);
    }

    public function test_resolveVariable_withAllArtifactsFilteredOut_returnsEmptyString(): void
    {
        // Given - Create artifacts with 'output' category but filter by 'input'
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Filter by 'input' category
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => ', ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskProcess Doc 1',
            'text_content' => 'TaskProcess content 1',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskProcess Doc 2',
            'text_content' => 'TaskProcess content 2',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('', $result);
    }

    // ==========================================
    // COMBINED TESTS (Category + Strategy)
    // ==========================================

    public function test_resolveVariable_withCategoryFilterAndJoinStrategy_worksCorrectly(): void
    {
        // Given - Create artifacts with categories in meta.__category
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input', 'output'], // Filter by input and output categories
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'      => '; ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 1',
            'text_content' => 'TaskRun diagnosis A',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskProcess Doc',
            'text_content' => 'TaskProcess terms',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 2',
            'text_content' => 'TaskRun observation B',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifact4 = Artifact::factory()->create([
            'name'         => 'Other Artifact',
            'text_content' => 'Other data',
            'team_id'      => $this->user->currentTeam->id,
            // No category - should be excluded
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3, $artifact4]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should only include input and output artifacts, joined with separator
        $this->assertEquals('TaskRun diagnosis A; TaskProcess terms; TaskRun observation B', $result);
    }

    public function test_resolveVariable_withCategoryFilterAndFirstStrategy_returnsFirstMatch(): void
    {
        // Given - Create artifacts with categories in meta.__category
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Filter by 'input' category
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'      => ', ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskProcess Doc',
            'text_content' => 'TaskProcess content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 1',
            'text_content' => 'First TaskRun content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 2',
            'text_content' => 'Second TaskRun content',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should return first input category artifact only
        $this->assertEquals('First TaskRun content', $result);
    }

    public function test_resolveVariable_withCategoryFilterAndUniqueStrategy_removeDuplicatesFromFilteredResults(): void
    {
        // Given - Create artifacts with categories in meta.__category
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => ['input'], // Filter by 'input' category
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator'      => ', ',
        ]);

        $artifact1 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 1',
            'text_content' => 'Diagnosis: Condition X',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'name'         => 'TaskProcess Doc',
            'text_content' => 'Diagnosis: Condition X',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'output'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 2',
            'text_content' => 'Diagnosis: Condition Y',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifact4 = Artifact::factory()->create([
            'name'         => 'TaskRun Report 3',
            'text_content' => 'Diagnosis: Condition X',
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['__category' => 'input'],
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3, $artifact4]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then - Should filter to input category only, then remove duplicates
        $this->assertEquals('Diagnosis: Condition X, Diagnosis: Condition Y', $result);
    }
}
