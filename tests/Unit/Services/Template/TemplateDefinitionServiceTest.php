<?php

namespace Tests\Unit\Services\Template;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Services\Template\TemplateDefinitionService;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateDefinitionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // FETCH TEMPLATE VARIABLES TESTS
    // ==========================================

    public function test_fetchTemplateVariables_withValidTemplate_createsNewVariablesWithDefaultAiMapping(): void
    {
        // Given
        $docId      = 'test-google-doc-id-123';
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Mock GoogleDocsApi to return template variables
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['patient_name', 'date_of_service', 'diagnosis']);

        // When
        $result = app(TemplateDefinitionService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify all variables were created with correct defaults
        $this->assertDatabaseHas('template_variables', [
            'template_definition_id'    => $template->id,
            'name'                      => 'patient_name',
            'mapping_type'              => TemplateVariable::MAPPING_TYPE_AI,
            'multi_value_strategy'      => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'     => ', ',
        ]);

        $this->assertDatabaseHas('template_variables', [
            'template_definition_id' => $template->id,
            'name'                   => 'date_of_service',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_AI,
        ]);

        $this->assertDatabaseHas('template_variables', [
            'template_definition_id' => $template->id,
            'name'                   => 'diagnosis',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_AI,
        ]);
    }

    public function test_fetchTemplateVariables_withExistingVariables_preservesExistingConfigurations(): void
    {
        // Given
        $docId      = 'test-google-doc-id-456';
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create existing variable with custom configuration
        $existingVariable = TemplateVariable::factory()->artifactMapped()->create([
            'template_definition_id'    => $template->id,
            'name'                      => 'patient_name',
            'description'               => 'Custom description',
            'artifact_categories'       => ['medical'],
            'multi_value_strategy'      => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'     => ' | ',
        ]);

        // Mock GoogleDocsApi to return same variable plus new one
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['patient_name', 'new_variable']);

        // When
        $result = app(TemplateDefinitionService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(2, $result);

        // Verify existing variable configuration was preserved
        $preserved = TemplateVariable::where('name', 'patient_name')
            ->where('template_definition_id', $template->id)
            ->first();

        $this->assertEquals($existingVariable->id, $preserved->id);
        $this->assertEquals('Custom description', $preserved->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $preserved->mapping_type);
        $this->assertEquals(['medical'], $preserved->artifact_categories);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $preserved->multi_value_strategy);
        $this->assertEquals(' | ', $preserved->multi_value_separator);

        // Verify new variable was created with defaults
        $this->assertDatabaseHas('template_variables', [
            'template_definition_id'    => $template->id,
            'name'                      => 'new_variable',
            'mapping_type'              => TemplateVariable::MAPPING_TYPE_AI,
            'multi_value_strategy'      => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'     => ', ',
        ]);
    }

    public function test_fetchTemplateVariables_withOrphanedVariables_deletesRemovedVariables(): void
    {
        // Given
        $docId      = 'test-google-doc-id-789';
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create variables that will be orphaned
        $keptVariable = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'kept_variable',
        ]);

        $orphanedVariable1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_variable_1',
        ]);

        $orphanedVariable2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_variable_2',
        ]);

        // Mock GoogleDocsApi to return only one variable (the kept one)
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['kept_variable']);

        // When
        $result = app(TemplateDefinitionService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals('kept_variable', $result->first()->name);

        // Verify orphaned variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVariable1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVariable2->id]);

        // Verify kept variable is still present
        $this->assertDatabaseHas('template_variables', [
            'id'         => $keptVariable->id,
            'name'       => 'kept_variable',
            'deleted_at' => null,
        ]);
    }

    public function test_fetchTemplateVariables_withEmptyVariableList_deletesAllVariables(): void
    {
        // Given
        $docId      = 'test-google-doc-id-empty';
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create variables that will be deleted
        $variable1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'variable_1',
        ]);

        $variable2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'variable_2',
        ]);

        // Mock GoogleDocsApi to return empty array
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn([]);

        // When
        $result = app(TemplateDefinitionService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(0, $result);

        // Verify all variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $variable1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $variable2->id]);
    }

    public function test_fetchTemplateVariables_withNoStoredFile_throwsValidationError(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => null,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Template definition does not have a stored file');
        $this->expectExceptionCode(400);

        // When
        app(TemplateDefinitionService::class)->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withStoredFileWithoutDocumentId_throwsValidationError(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => '', // Empty filepath - cannot extract document ID
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Stored file does not have a valid Google Docs document ID');
        $this->expectExceptionCode(400);

        // When
        app(TemplateDefinitionService::class)->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withOtherTeamsTemplate_throwsValidationError(): void
    {
        // Given - Create template for a different team
        $otherTeamStoredFile = StoredFile::factory()->create([
            'filepath' => 'https://docs.google.com/document/d/other-team-doc-id/edit',
        ]);

        $otherTeamTemplate = TemplateDefinition::factory()->create([
            'stored_file_id' => $otherTeamStoredFile->id,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this template definition');
        $this->expectExceptionCode(403);

        // When
        app(TemplateDefinitionService::class)->fetchTemplateVariables($otherTeamTemplate);
    }

    public function test_fetchTemplateVariables_callsTemplateVariableServiceSyncMethod(): void
    {
        // Given
        $docId      = 'test-google-doc-sync';
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $expectedVariables = ['var1', 'var2', 'var3'];

        // Mock GoogleDocsApi
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn($expectedVariables);

        // When
        $result = app(TemplateDefinitionService::class)->fetchTemplateVariables($template);

        // Then - Verify that syncVariablesFromGoogleDoc was called (indirectly via created variables)
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify variables match what was passed to sync
        $variableNames = $result->pluck('name')->toArray();
        sort($variableNames);
        sort($expectedVariables);
        $this->assertEquals($expectedVariables, $variableNames);
    }

    // ==========================================
    // SET SCHEMA DEFINITION TESTS
    // ==========================================

    public function test_setSchemaDefinition_setsSchemaOnTemplate(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'user_id'              => $this->user->id,
            'schema_definition_id' => null,
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->setSchemaDefinition($template, $schemaDefinition->id);

        // Then
        $this->assertInstanceOf(TemplateDefinition::class, $result);
        $this->assertEquals($schemaDefinition->id, $result->schema_definition_id);
        $this->assertDatabaseHas('template_definitions', [
            'id'                   => $template->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);
    }

    public function test_setSchemaDefinition_clearsSchemaWhenNull(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'user_id'              => $this->user->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->setSchemaDefinition($template, null);

        // Then
        $this->assertInstanceOf(TemplateDefinition::class, $result);
        $this->assertNull($result->schema_definition_id);
        $this->assertDatabaseHas('template_definitions', [
            'id'                   => $template->id,
            'schema_definition_id' => null,
        ]);
    }

    public function test_setSchemaDefinition_clearsInvalidVariableAssociations(): void
    {
        // Given
        $schemaDefinition1 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaDefinition2 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment1 = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition1->id,
        ]);

        $schemaFragment2 = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition2->id,
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'user_id'              => $this->user->id,
            'schema_definition_id' => $schemaDefinition1->id,
        ]);

        // Create variable with association to schema 1
        $variable1 = TemplateVariable::factory()->teamObjectMapped()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'var_with_schema1',
        ]);

        $association1 = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition1->id,
            'schema_fragment_id'   => $schemaFragment1->id,
            'object_type'          => TemplateVariable::class,
            'object_id'            => $variable1->id,
        ]);

        $variable1->team_object_schema_association_id = $association1->id;
        $variable1->save();

        // When - change schema to a different one
        $result = app(TemplateDefinitionService::class)->setSchemaDefinition($template, $schemaDefinition2->id);

        // Then
        $this->assertEquals($schemaDefinition2->id, $result->schema_definition_id);

        // Variable association should be cleared since it references a different schema
        $variable1->refresh();
        $this->assertNull($variable1->team_object_schema_association_id);

        // The orphaned association should be deleted
        $this->assertDatabaseMissing('schema_associations', ['id' => $association1->id]);
    }

    public function test_setSchemaDefinition_preservesValidVariableAssociations(): void
    {
        // Given
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'user_id'              => $this->user->id,
            'schema_definition_id' => null,
        ]);

        // Create variable with association to the same schema we're setting
        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'var_with_valid_schema',
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
            'object_type'          => TemplateVariable::class,
            'object_id'            => $variable->id,
        ]);

        $variable->team_object_schema_association_id = $association->id;
        $variable->save();

        // When - set schema to the same one the variable references
        $result = app(TemplateDefinitionService::class)->setSchemaDefinition($template, $schemaDefinition->id);

        // Then
        $this->assertEquals($schemaDefinition->id, $result->schema_definition_id);

        // Variable association should NOT be cleared (setting same schema, no previous schema)
        $variable->refresh();
        $this->assertEquals($association->id, $variable->team_object_schema_association_id);
        $this->assertDatabaseHas('schema_associations', ['id' => $association->id]);
    }

    public function test_setSchemaDefinition_withOtherTeamsTemplate_throwsValidationError(): void
    {
        // Given
        $otherTeamTemplate = TemplateDefinition::factory()->create();
        $schemaDefinition  = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this template definition');
        $this->expectExceptionCode(403);

        // When
        app(TemplateDefinitionService::class)->setSchemaDefinition($otherTeamTemplate, $schemaDefinition->id);
    }

    public function test_setSchemaDefinition_isTransactional(): void
    {
        // Given
        $schemaDefinition1 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaDefinition2 = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition1->id,
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'user_id'              => $this->user->id,
            'schema_definition_id' => $schemaDefinition1->id,
        ]);

        $variable = TemplateVariable::factory()->teamObjectMapped()->create([
            'template_definition_id' => $template->id,
        ]);

        $association = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition1->id,
            'schema_fragment_id'   => $schemaFragment->id,
            'object_type'          => TemplateVariable::class,
            'object_id'            => $variable->id,
        ]);

        $variable->team_object_schema_association_id = $association->id;
        $variable->save();

        // When
        $result = app(TemplateDefinitionService::class)->setSchemaDefinition($template, $schemaDefinition2->id);

        // Then - verify both template and variable were updated atomically
        $this->assertEquals($schemaDefinition2->id, $result->schema_definition_id);
        $variable->refresh();
        $this->assertNull($variable->team_object_schema_association_id);
    }

    // ==========================================
    // SYNC VARIABLES FROM HTML TESTS
    // ==========================================

    public function test_syncVariablesFromHtml_createsNewVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div data-var-customer_name>Name</div><span data-var-invoice_total>Total</span><p data-var-due_date>Date</p>',
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $this->assertInstanceOf(TemplateDefinition::class, $result);

        // Verify variables were created
        $variables = $template->templateVariables()->get();
        $this->assertCount(3, $variables);

        $variableNames = $variables->pluck('name')->sort()->values()->toArray();
        $this->assertEquals(['customer_name', 'due_date', 'invoice_total'], $variableNames);

        // Verify each variable has correct defaults
        foreach ($variables as $variable) {
            $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $variable->mapping_type);
            $this->assertEquals(TemplateVariable::STRATEGY_JOIN, $variable->multi_value_strategy);
        }
    }

    public function test_syncVariablesFromHtml_restoresSoftDeletedVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div data-var-restored_var>Value</div>',
        ]);

        // Create and soft delete a variable
        $deletedVariable = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'restored_var',
            'description'            => 'Should be preserved',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
        ]);
        $originalId = $deletedVariable->id;
        $deletedVariable->delete();

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $variables = $template->templateVariables()->get();
        $this->assertCount(1, $variables);

        $restoredVariable = $variables->first();
        $this->assertEquals($originalId, $restoredVariable->id);
        $this->assertEquals('Should be preserved', $restoredVariable->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $restoredVariable->mapping_type);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $restoredVariable->multi_value_strategy);
    }

    public function test_syncVariablesFromHtml_withNoVariablesInHtml_deletesAllVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div>No variables here</div>',
        ]);

        $variable1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'old_var_1',
        ]);

        $variable2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'old_var_2',
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $activeVariables = $template->templateVariables()->get();
        $this->assertCount(0, $activeVariables);

        $this->assertSoftDeleted('template_variables', ['id' => $variable1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $variable2->id]);
    }

    public function test_syncVariablesFromHtml_withNullHtmlContent_deletesAllVariables(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
        ]);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'orphaned_var',
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $activeVariables = $template->templateVariables()->get();
        $this->assertCount(0, $activeVariables);

        $this->assertSoftDeleted('template_variables', ['id' => $variable->id]);
    }

    public function test_syncVariablesFromHtml_extractsVariablesWithDifferentFormats(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div data-var-snake_case>A</div><div data-var-camelCase>B</div><div data-var-with-hyphen>C</div><div data-var-MixedCase123>D</div>',
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $variables = $template->templateVariables()->get();
        $this->assertCount(4, $variables);

        $variableNames = $variables->pluck('name')->sort()->values()->toArray();
        $this->assertEquals(['MixedCase123', 'camelCase', 'snake_case', 'with-hyphen'], $variableNames);
    }

    public function test_syncVariablesFromHtml_withDuplicateVariableNames_createsOnce(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div data-var-repeated>First</div><span data-var-repeated>Second</span><p data-var-repeated>Third</p>',
        ]);

        // When
        $result = app(TemplateDefinitionService::class)->syncVariablesFromHtml($template);

        // Then
        $variables = $template->templateVariables()->get();
        $this->assertCount(1, $variables);
        $this->assertEquals('repeated', $variables->first()->name);
    }

    public function test_syncVariablesFromHtml_withOtherTeamsTemplate_throwsValidationError(): void
    {
        // Given
        $otherTeamTemplate = TemplateDefinition::factory()->create([
            'html_content' => '<div data-var-test_var>Test</div>',
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this template definition');
        $this->expectExceptionCode(403);

        // When
        app(TemplateDefinitionService::class)->syncVariablesFromHtml($otherTeamTemplate);
    }
}
