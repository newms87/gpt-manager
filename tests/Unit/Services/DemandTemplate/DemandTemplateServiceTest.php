<?php

namespace Tests\Unit\Services\DemandTemplate;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\DemandTemplate;
use App\Services\DemandTemplate\DemandTemplateService;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private DemandTemplateService $service;
    private GoogleDocsApi         $mockGoogleDocsApi;
    private GoogleDocsFileService $mockGoogleDocsFileService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Mock the dependencies
        $this->mockGoogleDocsApi         = $this->mock(GoogleDocsApi::class);
        $this->mockGoogleDocsFileService = $this->mock(GoogleDocsFileService::class);

        // Create service instance with mocked dependencies
        $this->service = new DemandTemplateService(
            $this->mockGoogleDocsFileService,
            $this->mockGoogleDocsApi
        );
    }

    public function test_fetchTemplateVariables_withNoExistingVariables_createsNewVariables(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'user_id'            => $this->user->id,
            'stored_file_id'     => $storedFile->id,
            'template_variables' => null,
        ]);

        // Mock the Google Doc ID extraction
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://docs.google.com/document/d/test123/edit')
            ->once()
            ->andReturn('test123');

        // Mock the API call to extract variables
        $this->mockGoogleDocsApi
            ->shouldReceive('extractTemplateVariables')
            ->with('test123')
            ->once()
            ->andReturn(['name', 'email', 'date']);

        // The syncVariablesToStoredFile method will be tested with database interactions

        // When
        $result = $this->service->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $result);
        $expected = [
            'name'  => '',
            'email' => '',
            'date'  => '',
        ];
        $this->assertEquals($expected, $result->template_variables);
    }

    public function test_fetchTemplateVariables_withExistingVariables_mergesWithNewVariables(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $existingVariables = [
            'name'    => 'Person full name',
            'company' => 'Company name',
        ];

        $template = DemandTemplate::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'user_id'            => $this->user->id,
            'stored_file_id'     => $storedFile->id,
            'template_variables' => $existingVariables,
        ]);

        // Mock the Google Doc ID extraction
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://docs.google.com/document/d/test123/edit')
            ->once()
            ->andReturn('test123');

        // Mock the API call to extract variables (includes one existing and two new)
        $this->mockGoogleDocsApi
            ->shouldReceive('extractTemplateVariables')
            ->with('test123')
            ->once()
            ->andReturn(['name', 'email', 'date']);

        // When
        $result = $this->service->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $result);
        $expected = [
            'name'    => 'Person full name', // Existing description preserved
            'company' => 'Company name',   // Existing variable preserved even if not in Google Doc
            'email'   => '',                 // New variable added with empty description
            'date'    => '',                  // New variable added with empty description
        ];
        $this->assertEquals($expected, $result->template_variables);
    }

    public function test_fetchTemplateVariables_withNoNewVariables_preservesExistingVariables(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $existingVariables = [
            'name'    => 'Person full name',
            'company' => 'Company name',
        ];

        $template = DemandTemplate::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'user_id'            => $this->user->id,
            'stored_file_id'     => $storedFile->id,
            'template_variables' => $existingVariables,
        ]);

        // Mock the Google Doc ID extraction
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://docs.google.com/document/d/test123/edit')
            ->once()
            ->andReturn('test123');

        // Mock the API call to extract variables (same as existing)
        $this->mockGoogleDocsApi
            ->shouldReceive('extractTemplateVariables')
            ->with('test123')
            ->once()
            ->andReturn(['name', 'company']);

        // When
        $result = $this->service->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $result);
        $this->assertEquals($existingVariables, $result->template_variables);
    }

    public function test_fetchTemplateVariables_withEmptyGoogleDocVariables_preservesExistingVariables(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $existingVariables = [
            'name'    => 'Person full name',
            'company' => 'Company name',
        ];

        $template = DemandTemplate::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'user_id'            => $this->user->id,
            'stored_file_id'     => $storedFile->id,
            'template_variables' => $existingVariables,
        ]);

        // Mock the Google Doc ID extraction
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://docs.google.com/document/d/test123/edit')
            ->once()
            ->andReturn('test123');

        // Mock the API call to extract variables (returns empty array)
        $this->mockGoogleDocsApi
            ->shouldReceive('extractTemplateVariables')
            ->with('test123')
            ->once()
            ->andReturn([]);

        // When
        $result = $this->service->fetchTemplateVariables($template);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $result);
        $this->assertEquals($existingVariables, $result->template_variables);
    }

    public function test_fetchTemplateVariables_withInvalidGoogleDocUrl_throwsValidationError(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://example.com/invalid-url',
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Mock the Google Doc ID extraction to return null (invalid URL)
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://example.com/invalid-url')
            ->once()
            ->andReturn(null);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Template does not have a valid Google Docs URL');
        $this->expectExceptionCode(400);

        // When
        $this->service->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withNoStoredFile_throwsValidationError(): void
    {
        // Given
        $template = DemandTemplate::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => null,
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Template does not have a valid Google Docs URL');
        $this->expectExceptionCode(400);

        // When
        $this->service->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_withUnauthorizedAccess_throwsValidationError(): void
    {
        // Given
        $otherUser = \App\Models\User::factory()->create();
        $otherTeam = \App\Models\Team\Team::factory()->create();

        $storedFile = StoredFile::factory()->create([
            'team_id' => $otherTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'        => $otherTeam->id,
            'user_id'        => $otherUser->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this demand template');
        $this->expectExceptionCode(403);

        // When
        $this->service->fetchTemplateVariables($template);
    }

    public function test_fetchTemplateVariables_syncsVariablesToStoredFile(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
            'meta'    => ['existing_key' => 'existing_value'],
        ]);

        $template = DemandTemplate::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'user_id'            => $this->user->id,
            'stored_file_id'     => $storedFile->id,
            'template_variables' => ['name' => 'Person name'],
        ]);

        // Mock the Google Doc ID extraction
        $this->mockGoogleDocsFileService
            ->shouldReceive('extractDocumentId')
            ->with('https://docs.google.com/document/d/test123/edit')
            ->once()
            ->andReturn('test123');

        // Mock the API call to extract variables
        $this->mockGoogleDocsApi
            ->shouldReceive('extractTemplateVariables')
            ->with('test123')
            ->once()
            ->andReturn(['name', 'email']);

        // When
        $result = $this->service->fetchTemplateVariables($template);

        // Then
        $storedFile->refresh();
        $expectedMeta = [
            'existing_key'       => 'existing_value',
            'template_variables' => [
                'name'  => 'Person name',
                'email' => '',
            ],
        ];
        $this->assertEquals($expectedMeta, $storedFile->meta);
    }
}
