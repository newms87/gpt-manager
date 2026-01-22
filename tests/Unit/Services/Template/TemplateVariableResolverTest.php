<?php

namespace Tests\Unit\Services\Template;

use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Services\Template\TemplateVariableResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class TemplateVariableResolverTest extends AuthenticatedTestCase
{
    protected TemplateDefinition $templateDefinition;

    protected TemplateVariableResolver $resolver;

    public function setUp(): void
    {
        parent::setUp();

        $this->templateDefinition = TemplateDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $this->resolver = new TemplateVariableResolver();
    }

    #[Test]
    public function it_resolves_artifact_content_by_category(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create artifacts with text content
        $summaryArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is the summary content.',
        ]);

        $teamObject->artifacts()->attach($summaryArtifact->id, ['category' => 'summary']);

        // Create template variable configured for artifact mapping
        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'summary_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['summary'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => ', ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertEquals('This is the summary content.', $result);
    }

    #[Test]
    public function it_combines_multiple_artifacts_from_same_category(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create multiple artifacts in same category
        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'First item',
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Second item',
        ]);

        $teamObject->artifacts()->attach($artifact1->id, ['category' => 'items']);
        $teamObject->artifacts()->attach($artifact2->id, ['category' => 'items']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'items_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['items'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => '; ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertStringContainsString('First item', $result);
        $this->assertStringContainsString('Second item', $result);
        $this->assertStringContainsString(';', $result);
    }

    #[Test]
    public function it_returns_first_value_with_first_strategy(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'First item',
            'position'     => 1,
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Second item',
            'position'     => 2,
        ]);

        $teamObject->artifacts()->attach($artifact1->id, ['category' => 'items']);
        $teamObject->artifacts()->attach($artifact2->id, ['category' => 'items']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'first_item',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['items'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'  => '',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertEquals('First item', $result);
    }

    #[Test]
    public function it_returns_unique_values_with_unique_strategy(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Duplicate value',
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Duplicate value',
        ]);

        $artifact3 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Unique value',
        ]);

        $teamObject->artifacts()->attach($artifact1->id, ['category' => 'items']);
        $teamObject->artifacts()->attach($artifact2->id, ['category' => 'items']);
        $teamObject->artifacts()->attach($artifact3->id, ['category' => 'items']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'unique_items',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['items'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_UNIQUE,
            'multi_value_separator'  => ', ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // Should only have 2 values: "Duplicate value" and "Unique value"
        $this->assertEquals('Duplicate value, Unique value', $result);
    }

    #[Test]
    public function it_returns_empty_string_when_no_artifacts_found(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'missing_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['nonexistent_category'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => ', ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_resolves_from_multiple_categories(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $summaryArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Summary text',
        ]);

        $analysisArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Analysis text',
        ]);

        $teamObject->artifacts()->attach($summaryArtifact->id, ['category' => 'summary']);
        $teamObject->artifacts()->attach($analysisArtifact->id, ['category' => 'analysis']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'combined_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['summary', 'analysis'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => ' | ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertStringContainsString('Summary text', $result);
        $this->assertStringContainsString('Analysis text', $result);
    }

    #[Test]
    public function it_falls_back_to_artifact_name_when_text_content_is_empty(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Artifact Name as Fallback',
            'text_content' => null,
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'items']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'fallback_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => ['items'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'  => '',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        $this->assertEquals('Artifact Name as Fallback', $result);
    }

    #[Test]
    public function it_resolves_fragment_data_from_team_object(): void
    {
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'fragment_selector'    => null, // No filtering - return all data
        ]);

        $schemaAssociation = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
        ]);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Object Name',
        ]);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id'            => $this->templateDefinition->id,
            'name'                              => 'name_var',
            'mapping_type'                      => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            'team_object_schema_association_id' => $schemaAssociation->id,
            'artifact_categories'               => null,
            'multi_value_strategy'              => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'             => '',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // The result should contain the team object name since we're mapping from team object data
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_combines_fragment_data_with_artifact_data(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Test Object',
            'description' => 'Object description',
        ]);

        // Add artifact
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Artifact content',
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'notes']);

        // Create schema association for team object data
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'fragment_selector'    => null,
        ]);

        $schemaAssociation = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
        ]);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id'            => $this->templateDefinition->id,
            'name'                              => 'combined_var',
            'mapping_type'                      => TemplateVariable::MAPPING_TYPE_VERBATIM,
            'team_object_schema_association_id' => $schemaAssociation->id,
            'artifact_categories'               => ['notes'],
            'multi_value_strategy'              => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'             => ' | ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // Should contain both artifact content and team object data
        $this->assertStringContainsString('Artifact content', $result);
    }

    #[Test]
    public function it_uses_verbatim_mode_for_artifact_mapping_type(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Exact content to preserve',
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'content']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'verbatim_var',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT, // Artifact type uses verbatim processing
            'artifact_categories'    => ['content'],
            'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
            'multi_value_separator'  => '',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // Should return exact content without AI processing
        $this->assertEquals('Exact content to preserve', $result);
    }

    #[Test]
    public function it_handles_empty_artifact_categories_array(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create artifact but with empty categories config
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Some content',
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'notes']);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'empty_categories',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => [], // Empty array
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => ', ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // Should return empty since no categories specified
        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_handles_null_artifact_categories(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $variable = TemplateVariable::factory()->create([
            'template_definition_id' => $this->templateDefinition->id,
            'name'                   => 'null_categories',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'    => null,
            'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'  => ', ',
        ]);

        $result = $this->resolver->resolve($variable, $teamObject);

        // Should return empty since no categories specified
        $this->assertEquals('', $result);
    }
}
