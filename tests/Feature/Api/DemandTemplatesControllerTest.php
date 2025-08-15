<?php

namespace Tests\Feature\Api;

use App\Models\DemandTemplate;
use App\Models\Team\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplatesControllerTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_index_returnsTeamTemplates(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        
        $currentTeamTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Current Team Template',
        ]);
        $otherTeamTemplate = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Other Team Template',
        ]);

        // When
        $response = $this->postJson('/api/demand-templates/list');

        // Then
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Current Team Template');
    }

    public function test_index_withFilters_filtersCorrectly(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        $activeTemplate = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Active Template',
        ]);
        $inactiveTemplate = DemandTemplate::factory()->inactive()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Inactive Template',
        ]);

        // When - Filter for active templates
        $response = $this->postJson('/api/demand-templates/list', [
            'filter' => ['is_active' => true]
        ]);

        // Then
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Active Template');
    }

    public function test_store_withValidData_createsTemplate(): void
    {
        // Given
        $templateData = [
            'action' => 'create',
            'data' => [
                'name' => 'New Template',
                'description' => 'New Description',
                'category' => 'Legal',
                'template_url' => 'https://docs.google.com/document/d/test123/edit',
                'metadata' => ['key' => 'value'],
                'is_active' => true,
            ],
        ];

        // When
        $response = $this->postJson('/api/demand-templates/apply-action', $templateData);

        // Then
        $response->assertOk();
        $response->assertJsonPath('item.name', 'New Template');
        $response->assertJsonPath('item.description', 'New Description');
        $response->assertJsonPath('item.category', 'Legal');
        $response->assertJsonPath('item.is_active', true);
        
        $this->assertDatabaseHas('demand_templates', [
            'name' => 'New Template',
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_withoutUrl_createsTemplateWithoutStoredFile(): void
    {
        // Given
        $templateData = [
            'action' => 'create',
            'data' => [
                'name' => 'Template Without URL',
                'description' => 'Test Description',
                'is_active' => true,
            ],
        ];

        // When
        $response = $this->postJson('/api/demand-templates/apply-action', $templateData);

        // Then
        $response->assertOk();
        $response->assertJsonPath('item.name', 'Template Without URL');
        $response->assertJsonPath('item.description', 'Test Description');
        $response->assertJsonPath('item.is_active', true);
        $response->assertJsonPath('item.stored_file_id', null);
        
        $this->assertDatabaseHas('demand_templates', [
            'name' => 'Template Without URL',
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => null,
        ]);
    }

    public function test_store_withInvalidData_returnsValidationErrors(): void
    {
        // Given
        $invalidData = [
            'action' => 'create',
            'data' => [
                'description' => 'Description without required fields',
            ],
        ];

        // When
        $response = $this->postJson('/api/demand-templates/apply-action', $invalidData);

        // Then
        $response->assertStatus(400);
        $response->assertJson([
            'error' => true,
            'message' => 'The name field is required.'
        ]);
    }

    public function test_store_withDuplicateName_returnsValidationError(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Duplicate Name',
        ]);

        $templateData = [
            'action' => 'create',
            'data' => [
                'name' => 'Duplicate Name',
                'template_url' => 'https://docs.google.com/document/d/test123/edit',
            ],
        ];

        // When
        $response = $this->postJson('/api/demand-templates/apply-action', $templateData);

        // Then
        $response->assertStatus(400);
        $response->assertJson([
            'error' => true,
            'message' => 'The name has already been taken for this team.'
        ]);
    }

    public function test_show_withValidId_returnsTemplate(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Test Template',
        ]);

        // When
        $response = $this->getJson("/api/demand-templates/{$template->id}/details");

        // Then
        $response->assertOk();
        $response->assertJsonPath('name', 'Test Template');
        $response->assertJsonPath('id', $template->id);
    }

    public function test_show_withOtherTeamTemplate_returns404(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $response = $this->getJson("/api/demand-templates/{$template->id}/details");

        // Then
        $response->assertNotFound();
    }

    public function test_update_withValidData_updatesTemplate(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'action' => 'update',
            'item' => $template->toArray(),
            'data' => [
                'name' => 'Updated Name',
                'description' => 'Updated Description',
                'is_active' => false,
            ],
        ];

        // When
        $response = $this->postJson("/api/demand-templates/apply-action", $updateData);

        // Then
        $response->assertOk();
        $response->assertJsonPath('item.name', 'Updated Name');
        $response->assertJsonPath('item.description', 'Updated Description');
        $response->assertJsonPath('item.is_active', false);
        
        $this->assertDatabaseHas('demand_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'is_active' => false,
        ]);
    }

    public function test_update_withOtherTeamTemplate_returns404(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $updateData = [
            'action' => 'update',
            'item' => $template->toArray(),
            'data' => ['name' => 'Updated Name'],
        ];

        // When
        $response = $this->postJson("/api/demand-templates/apply-action", $updateData);

        // Then
        $response->assertNotFound();
    }

    public function test_destroy_deletesTemplate(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $response = $this->postJson("/api/demand-templates/apply-action", [
            'action' => 'delete',
            'item' => $template->toArray(),
        ]);

        // Then
        $response->assertOk();
        $this->assertSoftDeleted('demand_templates', ['id' => $template->id]);
    }

    public function test_destroy_withOtherTeamTemplate_returns404(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $response = $this->postJson("/api/demand-templates/apply-action", [
            'action' => 'delete',
            'item' => $template->toArray(),
        ]);

        // Then
        $response->assertNotFound();
    }

    public function test_listActive_returnsOnlyActiveTemplates(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        $activeTemplate = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Active Template',
        ]);
        $inactiveTemplate = DemandTemplate::factory()->inactive()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Inactive Template',
        ]);

        // When
        $response = $this->getJson('/api/demand-templates/active');

        // Then
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Active Template');
        $response->assertJsonPath('0.is_active', true);
    }

    public function test_listActive_scopesToCurrentTeam(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        
        $currentTeamTemplate = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Current Team Template',
        ]);
        $otherTeamTemplate = DemandTemplate::factory()->active()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Other Team Template',
        ]);

        // When
        $response = $this->getJson('/api/demand-templates/active');

        // Then
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Current Team Template');
    }

    public function test_toggleActive_togglesTemplateStatus(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->active()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        $this->assertTrue($template->is_active);

        // When
        $response = $this->postJson("/api/demand-templates/{$template->id}/toggle-active");

        // Then
        $response->assertOk();
        $response->assertJsonPath('is_active', false);
        
        $this->assertDatabaseHas('demand_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);
    }

    public function test_toggleActive_withOtherTeamTemplate_returns404(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $response = $this->postJson("/api/demand-templates/{$template->id}/toggle-active");

        // Then
        $response->assertNotFound();
    }

    public function test_unauthenticated_requests_return401(): void
    {
        // Given - No authentication
        auth()->logout();

        // When & Then
        $this->postJson('/api/demand-templates/list')->assertUnauthorized();
        $this->postJson('/api/demand-templates/apply-action', [])->assertUnauthorized();
        $this->getJson('/api/demand-templates/1/details')->assertUnauthorized();
        $this->getJson('/api/demand-templates/active')->assertUnauthorized();
        $this->postJson('/api/demand-templates/1/toggle-active')->assertUnauthorized();
    }
}