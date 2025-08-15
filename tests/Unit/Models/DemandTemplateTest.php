<?php

namespace Tests\Unit\Models;

use App\Models\DemandTemplate;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class DemandTemplateTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_fillableAttributes_areCorrect(): void
    {
        // Given
        $expectedFillable = [
            'team_id',
            'user_id',
            'stored_file_id',
            'name',
            'description',
            'category',
            'metadata',
            'is_active',
        ];

        // When
        $fillable = (new DemandTemplate())->getFillable();

        // Then
        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_casts_areCorrect(): void
    {
        // Given
        $template = new DemandTemplate();

        // When
        $casts = $template->getCasts();

        // Then
        $this->assertEquals('array', $casts['metadata']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_teamRelationship_isConfiguredCorrectly(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $team = $template->team;

        // Then
        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals($this->user->currentTeam->id, $team->id);
    }

    public function test_userRelationship_isConfiguredCorrectly(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $user = $template->user;

        // Then
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);
    }

    public function test_storedFileRelationship_isConfiguredCorrectly(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $file = $template->storedFile;

        // Then
        $this->assertInstanceOf(StoredFile::class, $file);
        $this->assertEquals($storedFile->id, $file->id);
    }

    public function test_scopeActive_filtersActiveTemplatesOnly(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        $activeTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'is_active' => true,
        ]);
        $inactiveTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile2->id,
            'is_active' => false,
        ]);

        // When
        $activeTemplates = DemandTemplate::active()->get();

        // Then
        $this->assertCount(1, $activeTemplates);
        $this->assertEquals($activeTemplate->id, $activeTemplates->first()->id);
    }

    public function test_scopeForTeam_filtersTemplatesByTeam(): void
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
        $teamTemplates = DemandTemplate::forTeam($this->user->currentTeam->id)->get();

        // Then
        $this->assertCount(1, $teamTemplates);
        $this->assertEquals($currentTeamTemplate->id, $teamTemplates->first()->id);
    }

    public function test_validate_withValidData_passesValidation(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template = DemandTemplate::create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile->id,
            'name' => 'Test Template',
            'description' => 'Test Description',
            'category' => 'Legal',
            'metadata' => ['key' => 'value'],
            'is_active' => true,
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(DemandTemplate::class, $result);
    }

    public function test_validate_withoutStoredFile_passesValidation(): void
    {
        // Given
        $template = DemandTemplate::create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'name' => 'Test Template Without File',
            'description' => 'Test Description',
            'is_active' => true,
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(DemandTemplate::class, $result);
    }

    public function test_validate_withMissingRequiredFields_throwsValidationException(): void
    {
        // Given
        $template = DemandTemplate::make([]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $template->validate();
    }

    public function test_validate_withDuplicateName_throwsValidationException(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Duplicate Name',
        ]);

        $template = DemandTemplate::make([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Duplicate Name',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $template->validate();
    }

    public function test_validate_withDuplicateNameInDifferentTeam_passesValidation(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        
        DemandTemplate::factory()->create([
            'team_id' => $otherTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name' => 'Same Name',
        ]);

        $template = DemandTemplate::make([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'stored_file_id' => $storedFile2->id,
            'name' => 'Same Name',
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(DemandTemplate::class, $result);
    }

    public function test_getTemplateUrl_returnsStoredFileUrl(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => 'https://docs.google.com/document/d/test123/edit',
        ]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $url = $template->getTemplateUrl();

        // Then
        $this->assertEquals('https://docs.google.com/document/d/test123/edit', $url);
    }

    public function test_getTemplateUrl_withNoStoredFile_returnsNull(): void
    {
        // Given
        $template = DemandTemplate::make([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => 'non-existent-id',
        ]);

        // When
        $url = $template->getTemplateUrl();

        // Then
        $this->assertNull($url);
    }

    public function test_extractGoogleDocId_fromFullUrl_returnsId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
        ]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $docId = $template->extractGoogleDocId();

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $docId);
    }

    public function test_extractGoogleDocId_fromIdOnly_returnsId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        ]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $docId = $template->extractGoogleDocId();

        // Then
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $docId);
    }

    public function test_extractGoogleDocId_withInvalidUrl_returnsNull(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url' => 'https://example.com/invalid-url',
        ]);
        $template = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile->id,
        ]);

        // When
        $docId = $template->extractGoogleDocId();

        // Then
        $this->assertNull($docId);
    }

    public function test_extractGoogleDocId_withNoUrl_returnsNull(): void
    {
        // Given
        $template = DemandTemplate::make([
            'team_id' => $this->user->currentTeam->id,
            'stored_file_id' => 'non-existent-id',
        ]);

        // When
        $docId = $template->extractGoogleDocId();

        // Then
        $this->assertNull($docId);
    }
}