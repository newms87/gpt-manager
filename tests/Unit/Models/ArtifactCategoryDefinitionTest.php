<?php

namespace Tests\Unit\Models;

use App\Models\Schema\ArtifactCategoryDefinition;
use App\Models\Schema\SchemaDefinition;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class ArtifactCategoryDefinitionTest extends AuthenticatedTestCase
{
    #[Test]
    public function it_belongs_to_schema_definition(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'test-category',
            'label'                => 'Test Category',
            'prompt'               => 'Generate test content',
        ]);

        $this->assertInstanceOf(SchemaDefinition::class, $categoryDefinition->schemaDefinition);
        $this->assertEquals($schemaDefinition->id, $categoryDefinition->schemaDefinition->id);
    }

    #[Test]
    public function it_casts_fragment_selector_to_array(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $fragmentSelector = ['providers', 'contacts'];

        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'test-category',
            'label'                => 'Test Category',
            'prompt'               => 'Generate test content',
            'fragment_selector'    => $fragmentSelector,
        ]);

        $categoryDefinition->refresh();

        $this->assertIsArray($categoryDefinition->fragment_selector);
        $this->assertEquals($fragmentSelector, $categoryDefinition->fragment_selector);
    }

    #[Test]
    public function it_allows_same_name_in_different_schemas(): void
    {
        $firstSchema = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $secondSchema = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $firstCategory = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $firstSchema->id,
            'name'                 => 'shared-name',
            'label'                => 'First Category',
            'prompt'               => 'First prompt',
        ]);
        $firstCategory->validate();

        $secondCategory = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $secondSchema->id,
            'name'                 => 'shared-name',
            'label'                => 'Second Category',
            'prompt'               => 'Second prompt',
        ]);
        $secondCategory->validate();

        $this->assertEquals('shared-name', $firstCategory->name);
        $this->assertEquals('shared-name', $secondCategory->name);
        $this->assertNotEquals($firstCategory->id, $secondCategory->id);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $category = new ArtifactCategoryDefinition([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'valid-name',
            // Missing label and prompt
        ]);

        $this->expectException(ValidationException::class);
        $category->validate();
    }

    #[Test]
    public function it_casts_boolean_fields_correctly(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'test-category',
            'label'                => 'Test Category',
            'prompt'               => 'Generate test content',
            'editable'             => true,
            'deletable'            => false,
        ]);

        $categoryDefinition->refresh();

        $this->assertTrue($categoryDefinition->editable);
        $this->assertFalse($categoryDefinition->deletable);
    }

    #[Test]
    public function it_returns_string_representation(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'summary',
            'label'                => 'Summary',
            'prompt'               => 'Generate summary',
        ]);

        $stringRepresentation = (string) $categoryDefinition;

        $this->assertStringContainsString('ArtifactCategoryDefinition', $stringRepresentation);
        $this->assertStringContainsString((string) $categoryDefinition->id, $stringRepresentation);
        $this->assertStringContainsString('summary', $stringRepresentation);
    }

    #[Test]
    public function it_allows_null_fragment_selector(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'root-category',
            'label'                => 'Root Category',
            'prompt'               => 'Generate content for root object',
            'fragment_selector'    => null,
        ]);

        $categoryDefinition->refresh();

        $this->assertNull($categoryDefinition->fragment_selector);
    }
}
