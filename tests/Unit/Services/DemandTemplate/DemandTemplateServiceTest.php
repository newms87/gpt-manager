<?php

namespace Tests\Unit\Services\DemandTemplate;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Demand\DemandTemplate;
use App\Models\Demand\TemplateVariable;
use App\Services\DemandTemplate\DemandTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateServiceTest extends AuthenticatedTestCase
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
        $docId = 'test-google-doc-id-123';
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Mock GoogleDocsApi to return template variables
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['patient_name', 'date_of_service', 'diagnosis']);

        // When
        $result = app(DemandTemplateService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify all variables were created with correct defaults
        $this->assertDatabaseHas('template_variables', [
            'demand_template_id' => $template->id,
            'name' => 'patient_name',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        $this->assertDatabaseHas('template_variables', [
            'demand_template_id' => $template->id,
            'name' => 'date_of_service',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
        ]);

        $this->assertDatabaseHas('template_variables', [
            'demand_template_id' => $template->id,
            'name' => 'diagnosis',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
        ]);
    }

    public function test_fetchTemplateVariables_withExistingVariables_preservesExistingConfigurations(): void
    {
        // Given
        $docId = 'test-google-doc-id-456';
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create existing variable with custom configuration
        $existingVariable = TemplateVariable::factory()->artifactMapped()->create([
            'demand_template_id' => $template->id,
            'name' => 'patient_name',
            'description' => 'Custom description',
            'artifact_categories' => ['medical'],
            'multi_value_strategy' => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator' => ' | ',
        ]);

        // Mock GoogleDocsApi to return same variable plus new one
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['patient_name', 'new_variable']);

        // When
        $result = app(DemandTemplateService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(2, $result);

        // Verify existing variable configuration was preserved
        $preserved = TemplateVariable::where('name', 'patient_name')
            ->where('demand_template_id', $template->id)
            ->first();

        $this->assertEquals($existingVariable->id, $preserved->id);
        $this->assertEquals('Custom description', $preserved->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $preserved->mapping_type);
        $this->assertEquals(['medical'], $preserved->artifact_categories);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $preserved->multi_value_strategy);
        $this->assertEquals(' | ', $preserved->multi_value_separator);

        // Verify new variable was created with defaults
        $this->assertDatabaseHas('template_variables', [
            'demand_template_id' => $template->id,
            'name' => 'new_variable',
            'mapping_type' => TemplateVariable::MAPPING_TYPE_AI,
            'multi_value_strategy' => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);
    }

    public function test_fetchTemplateVariables_withOrphanedVariables_deletesRemovedVariables(): void
    {
        // Given
        $docId = 'test-google-doc-id-789';
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create variables that will be orphaned
        $keptVariable = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'kept_variable',
        ]);

        $orphanedVariable1 = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'orphaned_variable_1',
        ]);

        $orphanedVariable2 = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'orphaned_variable_2',
        ]);

        // Mock GoogleDocsApi to return only one variable (the kept one)
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn(['kept_variable']);

        // When
        $result = app(DemandTemplateService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals('kept_variable', $result->first()->name);

        // Verify orphaned variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVariable1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $orphanedVariable2->id]);

        // Verify kept variable is still present
        $this->assertDatabaseHas('template_variables', [
            'id' => $keptVariable->id,
            'name' => 'kept_variable',
            'deleted_at' => null,
        ]);
    }

    public function test_fetchTemplateVariables_withEmptyVariableList_deletesAllVariables(): void
    {
        // Given
        $docId = 'test-google-doc-id-empty';
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Create variables that will be deleted
        $variable1 = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'variable_1',
        ]);

        $variable2 = TemplateVariable::factory()->create([
            'demand_template_id' => $template->id,
            'name' => 'variable_2',
        ]);

        // Mock GoogleDocsApi to return empty array
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class);
        $mockGoogleDocsApi->shouldReceive('extractTemplateVariables')
            ->once()
            ->with($docId)
            ->andReturn([]);

        // When
        $result = app(DemandTemplateService::class)->fetchTemplateVariables($template);

        // Then
        $this->assertCount(0, $result);

        // Verify all variables were soft deleted
        $this->assertSoftDeleted('template_variables', ['id' => $variable1->id]);
        $this->assertSoftDeleted('template_variables', ['id' => $variable2->id]);
    }

    public function test_fetchTemplateVariables_withNoStoredFile_throwsValidationError(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => null,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Demand template does not have a stored file');
        $this->expectExceptionCode(400);

        // When
        app(DemandTemplateService::class)->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withStoredFileWithoutDocumentId_throwsValidationError(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => '', // Empty filepath - cannot extract document ID
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Stored file does not have a valid Google Docs document ID');
        $this->expectExceptionCode(400);

        // When
        app(DemandTemplateService::class)->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withOtherTeamsTemplate_throwsValidationError(): void
    {
        // Given - Create template for a different team
        $otherTeamStoredFile = StoredFile::factory()->create([
            'filepath' => 'https://docs.google.com/document/d/other-team-doc-id/edit',
        ]);

        $otherTeamTemplate = DemandTemplate::factory()->create([
            'stored_file_id' => $otherTeamStoredFile->id,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this demand template');
        $this->expectExceptionCode(403);

        // When
        app(DemandTemplateService::class)->fetchTemplateVariables($otherTeamTemplate);
    }

    public function test_fetchTemplateVariables_callsTemplateVariableServiceSyncMethod(): void
    {
        // Given
        $docId = 'test-google-doc-sync';
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'filepath' => "https://docs.google.com/document/d/{$docId}/edit",
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
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
        $result = app(DemandTemplateService::class)->fetchTemplateVariables($template);

        // Then - Verify that syncVariablesFromGoogleDoc was called (indirectly via created variables)
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify variables match what was passed to sync
        $variableNames = $result->pluck('name')->toArray();
        sort($variableNames);
        sort($expectedVariables);
        $this->assertEquals($expectedVariables, $variableNames);
    }
}
