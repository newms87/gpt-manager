<?php

namespace Tests\Feature\DemandTemplates;

use App\Models\DemandTemplate;
use App\Models\Team\Team;
use App\Models\User;
use App\Repositories\DemandTemplateRepository;
use App\Services\DemandTemplate\DemandTemplateService;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateRepositoryTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected DemandTemplateRepository $repository;
    protected DemandTemplateService $service;
    protected GoogleDocsFileService $googleDocsService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->repository = app(DemandTemplateRepository::class);
        $this->service = app(DemandTemplateService::class);
        $this->googleDocsService = app(GoogleDocsFileService::class);
    }

    public function test_query_scopesToCurrentTeam(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        
        $currentTeamTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
        ]);
        $otherTeamTemplate = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile2->id,
        ]);

        // When
        $templates = $this->repository->query()->get();

        // Then
        $this->assertCount(1, $templates);
        $this->assertEquals($currentTeamTemplate->id, $templates->first()->id);
    }

    public function test_query_eagerLoadsRelationships(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $templates = $this->repository->query()->get();

        // Then
        $template = $templates->first();
        $this->assertTrue($template->relationLoaded('storedFile'));
        $this->assertTrue($template->relationLoaded('user'));
    }




    public function test_getActiveTemplates_returnsOnlyActiveTemplatesSortedByName(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile3 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        $template1 = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Z Template',
        ]);
        $template2 = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'A Template',
        ]);
        $inactiveTemplate = DemandTemplate::factory()->inactive()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile3->id,
            'name' => 'B Template',
        ]);

        // When
        $activeTemplates = $this->repository->getActiveTemplates();

        // Then
        $this->assertCount(2, $activeTemplates);
        $this->assertEquals('A Template', $activeTemplates[0]->name);
        $this->assertEquals('Z Template', $activeTemplates[1]->name);
    }

    public function test_service_createTemplate_withValidData_createsTemplate(): void
    {
        // Given
        $templateData = [
            'name' => 'Test Template',
            'description' => 'Test Description',
            'category' => 'Legal',
            'template_url' => 'https://docs.google.com/document/d/test123/edit',
            'metadata' => ['key' => 'value'],
            'is_active' => true,
        ];

        // When
        $template = $this->service->createTemplate($templateData);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $template);
        $this->assertEquals($this->user->currentTeam->id, $template->team_id);
        $this->assertEquals($this->user->id, $template->user_id);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals('Test Description', $template->description);
        $this->assertEquals('Legal', $template->category);
        $this->assertTrue($template->is_active);
        $this->assertNotNull($template->stored_file_id);

        // Verify StoredFile was created
        $storedFile = $template->storedFile;
        $this->assertNotNull($storedFile);
        $this->assertEquals('https://docs.google.com/document/d/test123/edit', $storedFile->url);
        $this->assertEquals('external', $storedFile->disk);
        $this->assertEquals('application/vnd.google-apps.document', $storedFile->mime);
        $this->assertEquals('google_docs_template', $storedFile->meta['type']);
    }

    public function test_service_createTemplate_withoutUserId_usesCurrentUser(): void
    {
        // Given
        $templateData = [
            'name' => 'Test Template',
            'template_url' => 'https://docs.google.com/document/d/test123/edit',
        ];

        // When
        $template = $this->service->createTemplate($templateData);

        // Then
        $this->assertEquals($this->user->id, $template->user_id);
    }

    public function test_service_createTemplate_withExplicitUserId_usesProvidedUserId(): void
    {
        // Given
        $otherUser = User::factory()->create();
        $this->user->currentTeam->users()->attach($otherUser->id);
        
        $templateData = [
            'name' => 'Test Template',
            'user_id' => $otherUser->id,
            'template_url' => 'https://docs.google.com/document/d/test123/edit',
        ];

        // When
        $template = $this->service->createTemplate($templateData);

        // Then
        $this->assertEquals($otherUser->id, $template->user_id);
    }

    public function test_service_createTemplate_withoutUrl_createsTemplateWithoutStoredFile(): void
    {
        // Given
        $templateData = [
            'name' => 'Template Without URL',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        // When
        $template = $this->service->createTemplate($templateData);

        // Then
        $this->assertInstanceOf(DemandTemplate::class, $template);
        $this->assertEquals($this->user->currentTeam->id, $template->team_id);
        $this->assertEquals($this->user->id, $template->user_id);
        $this->assertEquals('Template Without URL', $template->name);
        $this->assertEquals('Test Description', $template->description);
        $this->assertTrue($template->is_active);
        $this->assertNull($template->stored_file_id);
    }

    public function test_service_updateTemplate_withValidData_updatesTemplate(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'category' => 'Updated Category',
            'is_active' => false,
        ];

        // When
        $updatedTemplate = $this->service->updateTemplate($template, $updateData);

        // Then
        $this->assertEquals('Updated Name', $updatedTemplate->name);
        $this->assertEquals('Updated Description', $updatedTemplate->description);
        $this->assertEquals('Updated Category', $updatedTemplate->category);
        $this->assertFalse($updatedTemplate->is_active);
    }

    public function test_service_updateTemplate_withNewTemplateUrl_createsNewStoredFile(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $originalStoredFileId = $template->stored_file_id;
        $updateData = [
            'name' => 'Updated Template',
            'template_url' => 'https://docs.google.com/document/d/new123/edit',
        ];

        // When
        $updatedTemplate = $this->service->updateTemplate($template, $updateData);

        // Then
        $this->assertNotEquals($originalStoredFileId, $updatedTemplate->stored_file_id);
        $this->assertEquals('https://docs.google.com/document/d/new123/edit', $updatedTemplate->storedFile->url);
    }

    public function test_model_delete_deletesTemplate(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $result = $template->delete();

        // Then
        $this->assertTrue($result);
        $this->assertSoftDeleted('demand_templates', ['id' => $template->id]);
    }

    public function test_toggleActive_togglesIsActiveStatus(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $this->assertTrue($template->is_active);

        // When
        $updatedTemplate = $this->repository->toggleActive($template);

        // Then
        $this->assertFalse($updatedTemplate->is_active);

        // When - Toggle again
        $updatedTemplate = $this->repository->toggleActive($updatedTemplate);

        // Then
        $this->assertTrue($updatedTemplate->is_active);
    }

    public function test_googleDocsService_createFromUrl_createsStoredFileWithCorrectAttributes(): void
    {
        // Given
        $url = 'https://docs.google.com/document/d/test123/edit';
        $name = 'Test Template';

        // When
        $storedFileId = $this->googleDocsService->createFromUrl($url, $name);
        $storedFile = StoredFile::find($storedFileId);

        // Then
        $this->assertInstanceOf(StoredFile::class, $storedFile);
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertEquals($this->user->id, $storedFile->user_id);
        $this->assertEquals('external', $storedFile->disk);
        $this->assertEquals($url, $storedFile->filepath);
        $this->assertEquals($url, $storedFile->url);
        $this->assertEquals('Test Template.gdoc', $storedFile->filename);
        $this->assertEquals('application/vnd.google-apps.document', $storedFile->mime);
        $this->assertEquals(0, $storedFile->size);
        $this->assertEquals('google_docs_template', $storedFile->meta['type']);
    }
}