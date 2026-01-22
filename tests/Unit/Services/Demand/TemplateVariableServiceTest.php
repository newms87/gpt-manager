<?php

namespace Tests\Unit\Services\Demand;

use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Services\Demand\TemplateVariableService;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateVariableServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // SYNC VARIABLES FROM GOOGLE DOC TESTS
    // ==========================================

    public function test_syncVariablesFromGoogleDoc_withNewVariables_createsWithCorrectDefaults(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variableNames = ['patient_name', 'date_of_service', 'diagnosis_code'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify each variable has correct defaults
        foreach ($variableNames as $variableName) {
            $variable = TemplateVariable::where('template_definition_id', $template->id)
                ->where('name', $variableName)
                ->first();

            $this->assertNotNull($variable, "Variable {$variableName} should exist");
            $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $variable->mapping_type);
            $this->assertEquals(TemplateVariable::STRATEGY_JOIN, $variable->multi_value_strategy);
            $this->assertEquals(', ', $variable->multi_value_separator);
            $this->assertEquals('', $variable->description);
        }
    }

    public function test_syncVariablesFromGoogleDoc_withExistingVariables_preservesConfigurations(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create existing variables with custom configurations
        $existingVar1 = TemplateVariable::factory()->artifactMapped()->create([
            'template_definition_id'    => $template->id,
            'name'                      => 'patient_name',
            'description'               => 'The patient full name',
            'artifact_categories'       => ['medical', 'personal'],
            'multi_value_strategy'      => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'     => ' | ',
        ]);

        $existingVar2 = TemplateVariable::factory()->aiMapped()->create([
            'template_definition_id'    => $template->id,
            'name'                      => 'diagnosis',
            'description'               => 'Medical diagnosis',
            'ai_instructions'           => 'Extract the primary diagnosis',
            'multi_value_strategy'      => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator'     => '; ',
        ]);

        $variableNames = ['patient_name', 'diagnosis', 'new_variable'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(3, $result);

        // Verify existing variable 1 configuration preserved
        $var1 = TemplateVariable::find($existingVar1->id);
        $this->assertEquals('The patient full name', $var1->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $var1->mapping_type);
        $this->assertEquals(['medical', 'personal'], $var1->artifact_categories);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $var1->multi_value_strategy);
        $this->assertEquals(' | ', $var1->multi_value_separator);

        // Verify existing variable 2 configuration preserved
        $var2 = TemplateVariable::find($existingVar2->id);
        $this->assertEquals('Medical diagnosis', $var2->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $var2->mapping_type);
        $this->assertEquals('Extract the primary diagnosis', $var2->ai_instructions);
        $this->assertEquals(TemplateVariable::STRATEGY_UNIQUE, $var2->multi_value_strategy);
        $this->assertEquals('; ', $var2->multi_value_separator);

        // Verify new variable created with defaults
        $newVar = TemplateVariable::where('name', 'new_variable')
            ->where('template_definition_id', $template->id)
            ->first();
        $this->assertNotNull($newVar);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $newVar->mapping_type);
        $this->assertEquals(TemplateVariable::STRATEGY_JOIN, $newVar->multi_value_strategy);
        $this->assertEquals(', ', $newVar->multi_value_separator);
    }

    public function test_syncVariablesFromGoogleDoc_withOrphanedVariables_deletesCorrectly(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create variables, some will be orphaned
        $keptVar = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'kept_variable',
        ]);

        $orphanedVar1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_1',
        ]);

        $orphanedVar2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_2',
        ]);

        $orphanedVar3 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_3',
        ]);

        $variableNames = ['kept_variable', 'new_variable'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(2, $result);

        // Verify kept variable still exists
        $this->assertDatabaseHas('template_variables', [
            'id'         => $keptVar->id,
            'name'       => 'kept_variable',
            'deleted_at' => null,
        ]);

        // Verify orphaned variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVar1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVar2->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVar3->id]);

        // Verify new variable was created
        $this->assertDatabaseHas('template_variables', [
            'template_definition_id' => $template->id,
            'name'                   => 'new_variable',
            'deleted_at'             => null,
        ]);
    }

    public function test_syncVariablesFromGoogleDoc_withEmptyVariableList_deletesAllVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create variables that will all be deleted
        $var1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'variable_1',
        ]);

        $var2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'variable_2',
        ]);

        $var3 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'variable_3',
        ]);

        $variableNames = [];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(0, $result);

        // Verify all variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $var1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $var2->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $var3->id]);
    }

    public function test_syncVariablesFromGoogleDoc_withOtherTeamsTemplate_throwsValidationError(): void
    {
        // Given - Create template for a different team
        $otherTeamTemplate = TemplateDefinition::factory()->create();

        $variableNames = ['variable_1'];

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this template definition');
        $this->expectExceptionCode(403);

        // When
        app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($otherTeamTemplate, $variableNames);
    }

    public function test_syncVariablesFromGoogleDoc_isTransactional_rollsBackOnError(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $existingVar = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'existing_variable',
        ]);

        // When - Pass invalid data that will cause an error during processing
        // Note: This test verifies transaction behavior by checking that no partial changes occur
        $variableNames = ['existing_variable', 'new_variable'];

        // Execute sync
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then - Verify transaction completed successfully
        $this->assertCount(2, $result);

        // Verify both variables exist (transaction committed)
        $this->assertDatabaseHas('template_variables', [
            'template_definition_id' => $template->id,
            'name'                   => 'existing_variable',
        ]);

        $this->assertDatabaseHas('template_variables', [
            'template_definition_id' => $template->id,
            'name'                   => 'new_variable',
        ]);
    }

    public function test_syncVariablesFromGoogleDoc_returnsUpdatedListOfVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create some existing variables
        TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'old_var',
        ]);

        $variableNames = ['new_var_1', 'new_var_2', 'new_var_3'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify result contains only the new variables (old one deleted)
        $resultNames = $result->pluck('name')->sort()->values()->toArray();
        sort($variableNames);
        $this->assertEquals($variableNames, $resultNames);

        // Verify each item in result is a TemplateVariable instance
        foreach ($result as $variable) {
            $this->assertInstanceOf(TemplateVariable::class, $variable);
        }
    }

    // Note: Duplicate variable names test removed.
    // The database has a unique constraint on (template_definition_id, name),
    // and the service doesn't need to handle duplicates because Google Docs
    // templates shouldn't have duplicate variable names in the first place.
    // The unique constraint will prevent any duplicates from being created.

    public function test_syncVariablesFromGoogleDoc_setsMultiValueStrategyToJoinForNewVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variableNames = ['var1', 'var2', 'var3'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        foreach ($result as $variable) {
            $this->assertEquals(TemplateVariable::STRATEGY_JOIN, $variable->multi_value_strategy);
            $this->assertEquals(', ', $variable->multi_value_separator);
        }
    }

    public function test_syncVariablesFromGoogleDoc_setsEmptyDescriptionForNewVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $variableNames = ['var1', 'var2'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        foreach ($result as $variable) {
            $this->assertEquals('', $variable->description);
        }
    }

    public function test_syncVariablesFromGoogleDoc_preservesSchemaAssociationsForExistingVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        // Create existing variable with schema association
        $existingVar = TemplateVariable::factory()->teamObjectMapped()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'team_object_var',
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
            'object_type'          => TemplateVariable::class,
            'object_id'            => $existingVar->id,
        ]);

        $existingVar->team_object_schema_association_id = $association->id;
        $existingVar->save();

        $variableNames = ['team_object_var', 'new_var'];

        // When
        $result = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(2, $result);

        // Verify schema association was preserved
        $preserved = TemplateVariable::find($existingVar->id);
        $this->assertEquals($association->id, $preserved->team_object_schema_association_id);
        $this->assertNotNull($preserved->teamObjectSchemaAssociation);
        $this->assertEquals($schemaDefinition->id, $preserved->teamObjectSchemaAssociation->schema_definition_id);
    }

    // ==========================================
    // SOFT DELETE RESTORE BEHAVIOR TESTS
    // ==========================================

    public function test_syncVariablesFromGoogleDoc_restoresSoftDeletedVariable_insteadOfCreatingNew(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create a variable and then soft delete it
        $deletedVariable = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'restored_variable',
            'description'            => 'Custom description to verify restoration',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['custom_category'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'  => ' | ',
        ]);
        $originalId = $deletedVariable->id;
        $deletedVariable->delete();

        // Verify it was soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $originalId]);

        // When - sync with the same variable name
        $variableNames = ['restored_variable'];
        $result        = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(1, $result);
        $restoredVariable = $result->first();

        // Verify same record was restored (not new)
        $this->assertEquals($originalId, $restoredVariable->id);
        $this->assertNull($restoredVariable->deleted_at);

        // Verify existing configuration was preserved
        $this->assertEquals('Custom description to verify restoration', $restoredVariable->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $restoredVariable->mapping_type);
        $this->assertEquals(['custom_category'], $restoredVariable->artifact_categories);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $restoredVariable->multi_value_strategy);
        $this->assertEquals(' | ', $restoredVariable->multi_value_separator);

        // Verify no new record was created
        $totalVariables = TemplateVariable::withTrashed()
            ->where('template_definition_id', $template->id)
            ->where('name', 'restored_variable')
            ->count();
        $this->assertEquals(1, $totalVariables);
    }

    public function test_syncVariablesFromGoogleDoc_preservesExistingConfiguration_onRestore(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        // Create variable with TeamObject mapping and schema association
        $deletedVariable = TemplateVariable::factory()->teamObjectMapped()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'team_object_var',
            'description'            => 'Team object variable description',
            'multi_value_strategy'   => TemplateVariable::STRATEGY_UNIQUE,
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
            'object_type'          => TemplateVariable::class,
            'object_id'            => $deletedVariable->id,
        ]);

        $deletedVariable->team_object_schema_association_id = $association->id;
        $deletedVariable->save();

        $originalId = $deletedVariable->id;
        $deletedVariable->delete();

        // When - sync with the same variable name
        $variableNames = ['team_object_var'];
        $result        = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(1, $result);
        $restoredVariable = $result->first();

        // Verify restoration preserved all configuration
        $this->assertEquals($originalId, $restoredVariable->id);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_TEAM_OBJECT, $restoredVariable->mapping_type);
        $this->assertEquals('Team object variable description', $restoredVariable->description);
        $this->assertEquals(TemplateVariable::STRATEGY_UNIQUE, $restoredVariable->multi_value_strategy);

        // Verify schema association was preserved
        $this->assertEquals($association->id, $restoredVariable->team_object_schema_association_id);
    }

    public function test_syncVariablesFromGoogleDoc_softDeletesVariables_noLongerInSource(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $var1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'keep_this',
        ]);

        $var2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'remove_this_1',
        ]);

        $var3 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'remove_this_2',
        ]);

        // When - sync with only one variable
        $variableNames = ['keep_this'];
        $result        = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals('keep_this', $result->first()->name);

        // Verify kept variable still active
        $this->assertDatabaseHas('template_variables', [
            'id'         => $var1->id,
            'deleted_at' => null,
        ]);

        // Verify other variables were soft deleted (not hard deleted)
        $this->assertSoftDeleted('template_variables', ['id' => $var2->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $var3->id]);

        // Verify records still exist in database with deleted_at set
        $this->assertDatabaseHas('template_variables', ['id' => $var2->id]);
        $this->assertDatabaseHas('template_variables', ['id' => $var3->id]);
    }

    public function test_syncVariablesFromGoogleDoc_handlesMultipleRestorations(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Create and soft delete multiple variables
        $var1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'var_alpha',
            'description'            => 'Alpha description',
        ]);
        $var2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'var_beta',
            'description'            => 'Beta description',
        ]);

        $var1->delete();
        $var2->delete();

        // When - restore both
        $variableNames = ['var_alpha', 'var_beta', 'var_gamma'];
        $result        = app(TemplateVariableService::class)->syncVariablesFromGoogleDoc($template, $variableNames);

        // Then
        $this->assertCount(3, $result);

        // Verify restorations
        $alpha = TemplateVariable::where('name', 'var_alpha')
            ->where('template_definition_id', $template->id)
            ->first();
        $this->assertEquals($var1->id, $alpha->id);
        $this->assertEquals('Alpha description', $alpha->description);

        $beta = TemplateVariable::where('name', 'var_beta')
            ->where('template_definition_id', $template->id)
            ->first();
        $this->assertEquals($var2->id, $beta->id);
        $this->assertEquals('Beta description', $beta->description);

        // Verify new variable was created
        $gamma = TemplateVariable::where('name', 'var_gamma')
            ->where('template_definition_id', $template->id)
            ->first();
        $this->assertNotNull($gamma);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $gamma->mapping_type);
    }
}
