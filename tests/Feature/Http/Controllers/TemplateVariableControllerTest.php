<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Demand\DemandTemplate;
use App\Models\Demand\TemplateVariable;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateVariableControllerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // INDEX ENDPOINT TESTS - REMOVED
    // ==========================================
    // Note: Index endpoint has been removed. Variables are now loaded via the
    // template_variables relationship on DemandTemplate. The frontend uses:
    // dxDemandTemplate.routes.list({ fields: { template_variables: true } })

    // ==========================================
    // STORE ENDPOINT TESTS - REMOVED
    // ==========================================
    // Note: Store endpoint has been removed. Variables are now fetched from
    // Google Docs templates using DemandTemplateService::fetchTemplateVariables()
    // Users cannot create variables manually anymore.

    // ==========================================
    // SHOW ENDPOINT TESTS (using /details endpoint)
    // ==========================================

    public function test_show_getSingleVariableWithAllDetails(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'name' => 'Test Variable',
            'description' => 'Test Description',
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'demand_template_id',
            'name',
            'description',
            'mapping_type',
            'artifact_categories',
            'artifact_fragment_selector',
            'team_object_schema_association_id',
            'ai_instructions',
            'multi_value_strategy',
            'multi_value_separator',
            'created_at',
            'updated_at',
            'schema_association',
        ]);

        $response->assertJsonFragment([
            'name' => 'Test Variable',
            'description' => 'Test Description',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
        ]);
    }

    public function test_show_includesSchemaAssociationRelationshipWhenExists(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'object_type' => TemplateVariable::class,
            'object_id' => $variable->id,
        ]);

        $variable->team_object_schema_association_id = $association->id;
        $variable->save();

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotNull($data['schema_association']);
        $this->assertEquals($association->id, $data['schema_association']['id']);
    }

    public function test_show_returns404WhenVariableDoesNotExist(): void
    {
        // When
        $response = $this->getJson("/api/template-variables/99999");

        // Then
        $response->assertStatus(404);
    }

    public function skip_test_show_teamScopingPreventsViewingOtherTeamsVariableWith403Status(): void
    {
        // Given - Create variable for a different team
        $otherTemplate = DemandTemplate::factory()->create();

        $variable = TemplateVariable::factory()->create([
            'demand_template_id' => $otherTemplate->id,
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'You do not have permission to access this template variable',
        ]);
    }

    // ==========================================
    // UPDATE ENDPOINT TESTS (using /apply-action with action='update')
    // ==========================================

    public function test_update_variableDescription(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $data = [
            'description' => 'Updated Description',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => $variable->ai_instructions,
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Original Name', // Name should remain unchanged
            'description' => 'Updated Description',
        ]);

        $this->assertDatabaseHas('template_variables', [
            'id' => $variable->id,
            'name' => 'Original Name', // Name should remain unchanged
            'description' => 'Updated Description',
        ]);
    }

    public function test_update_mappingTypeChangesConfiguration(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories' => ['medical', 'legal'],
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'mapping_type' => TemplateVariable::MAPPING_TYPE_ARTIFACT,
        ]);

        $updated = $variable->fresh();
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $updated->mapping_type);
        $this->assertEquals(['medical', 'legal'], $updated->artifact_categories);
        // Note: ai_instructions is not automatically cleared when changing mapping type
    }

    public function test_update_artifactConfigurationWithCategoriesAndFragmentSelector(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories' => ['updated', 'categories'],
            'artifact_fragment_selector' => [
                'type' => 'xpath',
                'selector' => '//div[@class="content"]',
            ],
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);

        $updated = $variable->fresh();
        $this->assertEquals(['updated', 'categories'], $updated->artifact_categories);
        $this->assertEquals('xpath', $updated->artifact_fragment_selector['type']);
    }

    public function test_update_teamObjectConfigurationWithSchemaDefinitionId(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            'schema_definition_id' => $schemaDefinition->id,
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);

        $updated = $variable->fresh();
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_TEAM_OBJECT, $updated->mapping_type);
        $this->assertNotNull($updated->team_object_schema_association_id);
    }

    public function test_update_aiConfigurationWithAiInstructions(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'ai_instructions' => 'Original instructions',
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => 'Updated AI instructions for extraction',
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);

        $updated = $variable->fresh();
        $this->assertEquals('Updated AI instructions for extraction', $updated->ai_instructions);
    }

    public function test_update_multiValueStrategyAndSeparator(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => $variable->ai_instructions,
            'multi_value_strategy' => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator' => '|',
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);

        $updated = $variable->fresh();
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $updated->multi_value_strategy);
        $this->assertEquals('|', $updated->multi_value_separator);
    }

    public function test_update_createsOrUpdatesSchemaAssociationWhenSchemaDefinitionIdProvided(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $oldAssociation = SchemaAssociation::find($variable->team_object_schema_association_id);

        $newSchemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            'schema_definition_id' => $newSchemaDefinition->id,
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(200);

        $updated = $variable->fresh();
        $association = SchemaAssociation::find($updated->team_object_schema_association_id);
        $this->assertNotNull($association);
        $this->assertEquals($newSchemaDefinition->id, $association->schema_definition_id);
    }

    public function test_update_validationFailsForInvalidDataWith400Status(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $data = [
            'mapping_type' => 'invalid_type', // Invalid mapping type
            'description' => '', // Empty description is allowed, but invalid mapping type is not
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        // Note: ActionController returns 400 for all validation errors
        $response->assertStatus(400);
        $response->assertJson([
            'error' => true,
        ]);
        // Validation error message will mention the invalid mapping type
        $message = $response->json('message');
        $this->assertTrue(
            str_contains($message, 'mapping_type') || str_contains($message, 'mapping type') || str_contains($message, 'invalid'),
            "Expected validation error message to mention mapping type issue, got: {$message}"
        );
    }

    public function test_update_nameFieldProhibited_returns400ValidationError(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'name' => 'Original Name',
        ]);

        $data = [
            'name' => 'Attempting to Change Name',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => $variable->ai_instructions,
            'multi_value_strategy' => $variable->multi_value_strategy,
            'multi_value_separator' => $variable->multi_value_separator,
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        // Note: ActionController returns 400 for all validation errors
        $response->assertStatus(400);
        $response->assertJson([
            'error' => true,
        ]);
        $this->assertStringContainsString('name', $response->json('message'));
        $this->assertStringContainsString('prohibited', $response->json('message'));

        // Verify name was not changed
        $this->assertDatabaseHas('template_variables', [
            'id' => $variable->id,
            'name' => 'Original Name',
        ]);
    }

    public function skip_test_update_teamScopingPreventsUpdatingOtherTeamsVariableWith403Status(): void
    {
        // Given - Create variable for a different team
        $otherTemplate = DemandTemplate::factory()->create();

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $otherTemplate->id,
        ]);

        $data = [
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions' => 'Updated instructions',
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ];

        // When
        $response = $this->postJson("/api/template-variables/{$variable->id}/apply-action", [
            'action' => 'update',
            'data' => $data
        ]);

        // Then
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'You do not have permission to access this template variable',
        ]);
    }

    // ==========================================
    // DESTROY ENDPOINT TESTS - REMOVED
    // ==========================================
    // Note: Destroy endpoint has been removed. Variables are automatically deleted
    // when they no longer exist in the Google Docs template during sync.
    // Users cannot delete variables manually anymore.

    // ==========================================
    // RESOURCE TRANSFORMATION TESTS (using /details endpoint)
    // ==========================================

    public function test_resource_includesAllFields(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
            'name' => 'Complete Variable',
            'description' => 'Full description',
            'ai_instructions' => 'AI instructions here',
            'multi_value_strategy' => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator' => ' :: ',
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('demand_template_id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('mapping_type', $data);
        $this->assertArrayHasKey('artifact_categories', $data);
        $this->assertArrayHasKey('artifact_fragment_selector', $data);
        $this->assertArrayHasKey('team_object_schema_association_id', $data);
        $this->assertArrayHasKey('ai_instructions', $data);
        $this->assertArrayHasKey('multi_value_strategy', $data);
        $this->assertArrayHasKey('multi_value_separator', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('schema_association', $data);
    }

    public function test_resource_schemaAssociationRelationshipProperlyNested(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Test Schema',
        ]);

        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'object_type' => TemplateVariable::class,
            'object_id' => $variable->id,
        ]);

        $variable->team_object_schema_association_id = $association->id;
        $variable->save();

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data['schema_association']);
        $this->assertArrayHasKey('id', $data['schema_association']);
        $this->assertEquals($association->id, $data['schema_association']['id']);
    }

    public function test_resource_nullSchemaAssociationHandledCorrectly(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->aiMapped()->create([
            'demand_template_id' => $template->id,
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNull($data['schema_association']);
    }

    public function test_resource_arrayFieldsProperlyCastForCategories(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'demand_template_id' => $template->id,
            'artifact_categories' => ['medical', 'legal', 'financial'],
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data['artifact_categories']);
        $this->assertEquals(['medical', 'legal', 'financial'], $data['artifact_categories']);
    }

    public function test_resource_arrayFieldsProperlyCastForFragmentSelector(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $fragmentSelector = [
            'type' => 'css_selector',
            'selector' => '.content .section',
            'options' => ['trim' => true],
        ];

        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'demand_template_id' => $template->id,
            'artifact_fragment_selector' => $fragmentSelector,
        ]);

        // When
        $response = $this->getJson("/api/template-variables/{$variable->id}/details");

        // Then
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data['artifact_fragment_selector']);
        $this->assertEquals($fragmentSelector, $data['artifact_fragment_selector']);
    }
}
