<?php

namespace Tests\Unit\Models\Demand;

use App\Models\Demand\DemandTemplate;
use App\Models\Demand\TemplateVariable;
use App\Models\Schema\SchemaAssociation;
use Illuminate\Validation\ValidationException;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateVariableTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // =====================================================
    // RELATIONSHIP TESTS
    // =====================================================

    public function test_belongsTo_demandTemplate_relationship_exists(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
        ]);

        // When
        $relatedTemplate = $variable->demandTemplate;

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $relatedTemplate);
        $this->assertEquals($template->id, $relatedTemplate->id);
    }

    public function test_belongsTo_teamObjectSchemaAssociation_relationship_exists(): void
    {
        // Given
        $schemaAssociation = SchemaAssociation::factory()->create();
        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'team_object_schema_association_id' => $schemaAssociation->id,
        ]);

        // When
        $relatedAssociation = $variable->teamObjectSchemaAssociation;

        // Then
        $this->assertInstanceOf(SchemaAssociation::class, $relatedAssociation);
        $this->assertEquals($schemaAssociation->id, $relatedAssociation->id);
    }

    public function test_belongsTo_teamObjectSchemaAssociation_can_be_null(): void
    {
        // Given
        $variable = TemplateVariable::factory()->aiMapped()->create([
            'team_object_schema_association_id' => null,
        ]);

        // When
        $relatedAssociation = $variable->teamObjectSchemaAssociation;

        // Then
        $this->assertNull($relatedAssociation);
    }

    // =====================================================
    // HELPER METHOD TESTS
    // =====================================================

    public function test_isAiMapped_returnsTrue_when_mapping_type_is_ai(): void
    {
        // Given
        $variable = TemplateVariable::factory()->aiMapped()->create();

        // When & Then
        $this->assertTrue($variable->isAiMapped());
        $this->assertFalse($variable->isArtifactMapped());
        $this->assertFalse($variable->isTeamObjectMapped());
    }

    public function test_isArtifactMapped_returnsTrue_when_mapping_type_is_artifact(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create();

        // When & Then
        $this->assertTrue($variable->isArtifactMapped());
        $this->assertFalse($variable->isAiMapped());
        $this->assertFalse($variable->isTeamObjectMapped());
    }

    public function test_isTeamObjectMapped_returnsTrue_when_mapping_type_is_team_object(): void
    {
        // Given
        $variable = TemplateVariable::factory()->teamObjectMapped()->create();

        // When & Then
        $this->assertTrue($variable->isTeamObjectMapped());
        $this->assertFalse($variable->isAiMapped());
        $this->assertFalse($variable->isArtifactMapped());
    }

    // =====================================================
    // CAST TESTS
    // =====================================================

    public function test_artifact_categories_casts_to_array(): void
    {
        // Given
        $categories = ['medical', 'legal', 'financial'];
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories' => $categories,
        ]);

        // When
        $variable->refresh();

        // Then
        $this->assertIsArray($variable->artifact_categories);
        $this->assertEquals($categories, $variable->artifact_categories);
    }

    public function test_artifact_fragment_selector_casts_to_array(): void
    {
        // Given
        $selector = ['type' => 'css_selector', 'selector' => '.content'];
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_fragment_selector' => $selector,
        ]);

        // When
        $variable->refresh();

        // Then
        $this->assertIsArray($variable->artifact_fragment_selector);
        $this->assertEquals($selector, $variable->artifact_fragment_selector);
    }

    public function test_artifact_categories_can_be_null(): void
    {
        // Given
        $variable = TemplateVariable::factory()->aiMapped()->create([
            'artifact_categories' => null,
        ]);

        // When
        $variable->refresh();

        // Then
        $this->assertNull($variable->artifact_categories);
    }

    public function test_artifact_fragment_selector_can_be_null(): void
    {
        // Given
        $variable = TemplateVariable::factory()->aiMapped()->create([
            'artifact_fragment_selector' => null,
        ]);

        // When
        $variable->refresh();

        // Then
        $this->assertNull($variable->artifact_fragment_selector);
    }

    // =====================================================
    // DEFAULT ORDERING TESTS
    // =====================================================

    public function test_variables_automatically_ordered_by_name_alphabetically(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $varZ = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'Zebra',
        ]);
        $varA = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'Apple',
        ]);
        $varM = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'Mango',
        ]);

        // When
        $variables = TemplateVariable::all();

        // Then
        $this->assertCount(3, $variables);
        $this->assertEquals('Apple', $variables->first()->name);
        $this->assertEquals('Mango', $variables->get(1)->name);
        $this->assertEquals('Zebra', $variables->last()->name);
    }

    // =====================================================
    // VALIDATION TESTS - SUCCESS CASES
    // =====================================================

    public function test_validate_withValidAiMapping_passesValidation(): void
    {
        // Given
        $variable = TemplateVariable::factory()->aiMapped()->create();

        // When & Then
        $result = $variable->validate();
        $this->assertInstanceOf(TemplateVariable::class, $result);
    }

    public function test_validate_withValidArtifactMapping_withCategories_passesValidation(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories' => ['medical', 'legal'],
            'artifact_fragment_selector' => null,
        ]);

        // When & Then
        $result = $variable->validate();
        $this->assertInstanceOf(TemplateVariable::class, $result);
    }

    public function test_validate_withValidArtifactMapping_withSelector_passesValidation(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories' => null,
            'artifact_fragment_selector' => ['type' => 'css', 'selector' => '.test'],
        ]);

        // When & Then
        $result = $variable->validate();
        $this->assertInstanceOf(TemplateVariable::class, $result);
    }

    public function test_validate_withValidTeamObjectMapping_passesValidation(): void
    {
        // Given
        $variable = TemplateVariable::factory()->teamObjectMapped()->create();

        // When & Then
        $result = $variable->validate();
        $this->assertInstanceOf(TemplateVariable::class, $result);
    }

    // =====================================================
    // VALIDATION TESTS - FAILURE CASES
    // =====================================================

    public function test_validate_withoutName_throwsValidationException(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => 'Test instructions',
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $variable->validate();
    }

    public function test_validate_withoutMappingType_throwsValidationException(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $variable->validate();
    }

    public function test_validate_withInvalidMappingType_throwsValidationException(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'mapping_type' => 'invalid_type',
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $variable->validate();
    }

    public function test_validate_artifactMapping_withoutCategoriesOrSelector_isValid(): void
    {
        // Given - Artifact mapping without categories or selector (selects all artifacts)
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories' => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // When
        $result = $variable->validate();

        // Then - Should pass validation (user can select all artifacts)
        $this->assertInstanceOf(TemplateVariable::class, $result);
    }

    public function test_validate_teamObjectMapping_withoutSchemaAssociation_throwsValidationError(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            'team_object_schema_association_id' => null,
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team object mapping requires team_object_schema_association_id');

        // When
        $variable->validate();
    }


    public function test_validate_withInvalidMultiValueStrategy_throwsValidationException(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable = TemplateVariable::make([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => 'Test instructions',
            'multi_value_strategy' => 'invalid_strategy',
            'multi_value_separator' => ', ',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $variable->validate();
    }

    // =====================================================
    // SOFT DELETE TESTS
    // =====================================================

    public function test_variables_can_be_soft_deleted(): void
    {
        // Given
        $variable = TemplateVariable::factory()->create();

        // When
        $variable->delete();

        // Then
        $this->assertSoftDeleted($variable);
        $this->assertNotNull($variable->deleted_at);
    }

    public function test_soft_deleted_variables_excluded_from_queries_by_default(): void
    {
        // Given
        $variable1 = TemplateVariable::factory()->create(['name' => 'Active Variable']);
        $variable2 = TemplateVariable::factory()->create(['name' => 'Deleted Variable']);
        $variable2->delete();

        // When
        $variables = TemplateVariable::all();

        // Then
        $this->assertCount(1, $variables);
        $this->assertEquals($variable1->id, $variables->first()->id);
    }

    public function test_soft_deleted_variables_included_with_trashed_scope(): void
    {
        // Given
        $variable1 = TemplateVariable::factory()->create(['name' => 'Active Variable']);
        $variable2 = TemplateVariable::factory()->create(['name' => 'Deleted Variable']);
        $variable2->delete();

        // When
        $variables = TemplateVariable::withTrashed()->get();

        // Then
        $this->assertCount(2, $variables);
    }

    // =====================================================
    // CONSTANT TESTS
    // =====================================================

    public function test_mapping_type_constants_are_correct(): void
    {
        // Then
        $this->assertEquals('ai', TemplateVariable::MAPPING_TYPE_AI);
        $this->assertEquals('artifact', TemplateVariable::MAPPING_TYPE_ARTIFACT);
        $this->assertEquals('team_object', TemplateVariable::MAPPING_TYPE_TEAM_OBJECT);
    }

    public function test_strategy_constants_are_correct(): void
    {
        // Then
        $this->assertEquals('join', TemplateVariable::STRATEGY_JOIN);
        $this->assertEquals('first', TemplateVariable::STRATEGY_FIRST);
        $this->assertEquals('unique', TemplateVariable::STRATEGY_UNIQUE);
    }

    // =====================================================
    // MULTI-VALUE STRATEGY TESTS
    // =====================================================

    public function test_variable_can_use_join_strategy(): void
    {
        // Given
        $variable = TemplateVariable::factory()->create([
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => '; ',
        ]);

        // Then
        $this->assertEquals(TemplateVariable::STRATEGY_JOIN, $variable->multi_value_strategy);
        $this->assertEquals('; ', $variable->multi_value_separator);
    }

    public function test_variable_can_use_first_strategy(): void
    {
        // Given
        $variable = TemplateVariable::factory()->firstStrategy()->create();

        // Then
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $variable->multi_value_strategy);
    }

    public function test_variable_can_use_unique_strategy(): void
    {
        // Given
        $variable = TemplateVariable::factory()->uniqueStrategy()->create();

        // Then
        $this->assertEquals(TemplateVariable::STRATEGY_UNIQUE, $variable->multi_value_strategy);
    }
}
