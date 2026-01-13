<?php

namespace Tests\Unit\Models;

use App\Models\Team\Team;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use Illuminate\Validation\ValidationException;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateDefinitionTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_scopeActive_filtersActiveTemplatesOnly(): void
    {
        // Given
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $activeTemplate   = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'is_active'      => true,
        ]);
        $inactiveTemplate = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile2->id,
            'is_active'      => false,
        ]);

        // When
        $activeTemplates = TemplateDefinition::active()->get();

        // Then
        $this->assertCount(1, $activeTemplates);
        $this->assertEquals($activeTemplate->id, $activeTemplates->first()->id);
    }

    public function test_scopeForTeam_filtersTemplatesByTeam(): void
    {
        // Given
        $otherTeam   = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);

        $currentTeamTemplate = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
        ]);
        $otherTeamTemplate   = TemplateDefinition::factory()->create([
            'team_id'        => $otherTeam->id,
            'stored_file_id' => $storedFile2->id,
        ]);

        // When
        $teamTemplates = TemplateDefinition::forTeam($this->user->currentTeam->id)->get();

        // Then
        $this->assertCount(1, $teamTemplates);
        $this->assertEquals($currentTeamTemplate->id, $teamTemplates->first()->id);
    }

    public function test_validate_withValidData_passesValidation(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $template   = TemplateDefinition::create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'type'           => TemplateDefinition::TYPE_GOOGLE_DOCS,
            'stored_file_id' => $storedFile->id,
            'name'           => 'Test Template',
            'description'    => 'Test Description',
            'category'       => 'Legal',
            'metadata'       => ['key' => 'value'],
            'is_active'      => true,
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(TemplateDefinition::class, $result);
    }

    public function test_validate_withoutStoredFile_passesValidation(): void
    {
        // Given
        $template = TemplateDefinition::create([
            'team_id'     => $this->user->currentTeam->id,
            'user_id'     => $this->user->id,
            'type'        => TemplateDefinition::TYPE_HTML,
            'name'        => 'Test Template Without File',
            'description' => 'Test Description',
            'is_active'   => true,
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(TemplateDefinition::class, $result);
    }

    public function test_validate_withMissingRequiredFields_throwsValidationException(): void
    {
        // Given
        $template = TemplateDefinition::make([]);

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

        TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name'           => 'Duplicate Name',
        ]);

        $template = TemplateDefinition::make([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'type'           => TemplateDefinition::TYPE_GOOGLE_DOCS,
            'stored_file_id' => $storedFile2->id,
            'name'           => 'Duplicate Name',
        ]);

        // Then
        $this->expectException(ValidationException::class);

        // When
        $template->validate();
    }

    public function test_validate_withDuplicateNameInDifferentTeam_passesValidation(): void
    {
        // Given
        $otherTeam   = Team::factory()->create();
        $storedFile1 = StoredFile::factory()->create(['team_id' => $otherTeam->id]);
        $storedFile2 = StoredFile::factory()->create(['team_id' => $this->user->currentTeam->id]);

        TemplateDefinition::factory()->create([
            'team_id'        => $otherTeam->id,
            'stored_file_id' => $storedFile1->id,
            'name'           => 'Same Name',
        ]);

        $template = TemplateDefinition::make([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'type'           => TemplateDefinition::TYPE_GOOGLE_DOCS,
            'stored_file_id' => $storedFile2->id,
            'name'           => 'Same Name',
        ]);

        // When & Then
        $result = $template->validate();
        $this->assertInstanceOf(TemplateDefinition::class, $result);
    }

    public function test_getTemplateUrl_returnsStoredFileUrl(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/test123/edit',
        ]);
        $template   = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
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
        $template = TemplateDefinition::make([
            'team_id'        => $this->user->currentTeam->id,
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
            'url'     => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
        ]);
        $template   = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
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
            'url'     => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        ]);
        $template   = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
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
            'url'     => 'https://example.com/invalid-url',
        ]);
        $template   = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
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
        $template = TemplateDefinition::make([
            'team_id'        => $this->user->currentTeam->id,
            'stored_file_id' => 'non-existent-id',
        ]);

        // When
        $docId = $template->extractGoogleDocId();

        // Then
        $this->assertNull($docId);
    }

    // =====================================================
    // TEMPLATE VARIABLES RELATIONSHIP TESTS
    // =====================================================

    public function test_templateVariables_relationship_exists(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $variable1 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'Variable 1',
        ]);
        $variable2 = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'Variable 2',
        ]);

        // When
        $variables = $template->templateVariables;

        // Then
        $this->assertCount(2, $variables);
        $this->assertTrue($variables->contains($variable1));
        $this->assertTrue($variables->contains($variable2));
    }

    public function test_templateVariables_ordered_by_name(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $varZ = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'Zebra',
        ]);
        $varA = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'Apple',
        ]);
        $varM = TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'Mango',
        ]);

        // When
        $variables = $template->templateVariables;

        // Then
        $this->assertCount(3, $variables);
        $this->assertEquals('Apple', $variables->get(0)->name);
        $this->assertEquals('Mango', $variables->get(1)->name);
        $this->assertEquals('Zebra', $variables->get(2)->name);
    }

    public function test_template_variables_json_property_not_in_fillable(): void
    {
        // Given
        $fillable = (new TemplateDefinition())->getFillable();

        // Then
        $this->assertNotContains('template_variables', $fillable);
    }

    public function test_template_variables_not_in_casts(): void
    {
        // Given
        $template = new TemplateDefinition();
        $casts    = $template->getCasts();

        // Then
        $this->assertArrayNotHasKey('template_variables', $casts);
    }
}
