<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Services\Task\DataExtraction\IdentityPlanningPromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityPlanningPromptBuilderTest extends TestCase
{
    protected IdentityPlanningPromptBuilder $builder;

    public function setUp(): void
    {
        parent::setUp();
        $this->builder = new IdentityPlanningPromptBuilder();
    }

    #[Test]
    public function buildIdentityPrompt_includes_object_type_info(): void
    {
        $objectTypeInfo = [
            'name'          => 'Care Summary',
            'path'          => 'care_summaries',
            'level'         => 1,
            'parent_type'   => 'Demand',
            'is_array'      => true,
            'simple_fields' => [],
        ];

        $config = [
            'group_max_points'   => 10,
            'global_search_mode' => 'intelligent',
        ];

        $prompt = $this->builder->buildIdentityPrompt($objectTypeInfo, $config);

        $this->assertStringContainsString('Care Summary', $prompt);
        $this->assertStringContainsString('care_summaries', $prompt);
        $this->assertStringContainsString('**Level:** 1', $prompt);
        $this->assertStringContainsString('Demand', $prompt);
        $this->assertStringContainsString('**Is Array:** Yes', $prompt);
    }

    #[Test]
    public function buildIdentityPrompt_includes_all_simple_fields_as_yaml(): void
    {
        $objectTypeInfo = [
            'name'          => 'Provider',
            'path'          => 'providers',
            'level'         => 0,
            'is_array'      => false,
            'simple_fields' => [
                'name' => [
                    'title'       => 'Provider Name',
                    'description' => 'The name of the provider',
                ],
                'start_date' => [
                    'title'       => 'Start Date',
                    'description' => 'When services began',
                ],
                'id_number' => [
                    'title' => 'ID Number',
                ],
            ],
        ];

        $config = [
            'group_max_points'   => 10,
            'global_search_mode' => 'intelligent',
        ];

        $prompt = $this->builder->buildIdentityPrompt($objectTypeInfo, $config);

        $this->assertStringContainsString('properties:', $prompt);
        $this->assertStringContainsString('name:', $prompt);
        $this->assertStringContainsString('Provider Name', $prompt);
        $this->assertStringContainsString('The name of the provider', $prompt);
        $this->assertStringContainsString('start_date:', $prompt);
        $this->assertStringContainsString('Start Date', $prompt);
        $this->assertStringContainsString('id_number:', $prompt);
        $this->assertStringContainsString('ID Number', $prompt);
    }

    #[Test]
    public function buildIdentityPrompt_includes_config_values(): void
    {
        $objectTypeInfo = [
            'name'          => 'Test Object',
            'path'          => 'test',
            'level'         => 0,
            'is_array'      => false,
            'simple_fields' => [],
        ];

        $config = [
            'group_max_points' => 15,
        ];

        $prompt = $this->builder->buildIdentityPrompt($objectTypeInfo, $config);

        $this->assertStringContainsString('15', $prompt);
        $this->assertStringContainsString('maximum fields per group', $prompt);
    }

    #[Test]
    public function buildRemainingPrompt_includes_remaining_fields(): void
    {
        $objectTypeInfo = [
            'name'  => 'Care Summary',
            'path'  => 'care_summaries',
            'level' => 1,
        ];

        $remainingFields = [
            'diagnosis' => [
                'title'       => 'Diagnosis',
                'description' => 'Medical diagnosis',
            ],
            'medications' => [
                'title'       => 'Medications',
                'description' => 'List of medications',
            ],
        ];

        $config = [
            'group_max_points' => 10,
        ];

        $prompt = $this->builder->buildRemainingPrompt($objectTypeInfo, $remainingFields, $config);

        $this->assertStringContainsString('Care Summary', $prompt);
        $this->assertStringContainsString('diagnosis:', $prompt);
        $this->assertStringContainsString('Diagnosis', $prompt);
        $this->assertStringContainsString('medications:', $prompt);
        $this->assertStringContainsString('Medications', $prompt);
        $this->assertStringContainsString('10', $prompt);
    }

    #[Test]
    public function buildRemainingPrompt_includes_search_mode_instructions(): void
    {
        $objectTypeInfo = [
            'name'  => 'Test Object',
            'path'  => 'test',
            'level' => 0,
        ];

        $remainingFields = [
            'field1' => ['title' => 'Field 1'],
        ];

        $config = [
            'group_max_points' => 10,
        ];

        $prompt = $this->builder->buildRemainingPrompt($objectTypeInfo, $remainingFields, $config);

        $this->assertStringContainsString('skim', $prompt);
        $this->assertStringContainsString('exhaustive', $prompt);
        $this->assertStringContainsString('search_mode', $prompt);
    }

    #[Test]
    public function createIdentityResponseSchema_has_required_structure(): void
    {
        $schemaDefinition = $this->builder->createIdentityResponseSchema();

        $this->assertInstanceOf(SchemaDefinition::class, $schemaDefinition);
        $this->assertEquals('Identity Planning Response', $schemaDefinition->name);
        $this->assertEquals(SchemaDefinition::TYPE_AGENT_RESPONSE, $schemaDefinition->type);
        $this->assertEquals(SchemaDefinition::FORMAT_JSON, $schemaDefinition->schema_format);

        $schema = $schemaDefinition->schema;
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('identity_fields', $schema['properties']);
        $this->assertArrayHasKey('skim_fields', $schema['properties']);
        $this->assertArrayHasKey('reasoning', $schema['properties']);

        $this->assertEquals('array', $schema['properties']['identity_fields']['type']);
        $this->assertEquals('string', $schema['properties']['identity_fields']['items']['type']);

        $this->assertEquals('array', $schema['properties']['skim_fields']['type']);
        $this->assertEquals('string', $schema['properties']['skim_fields']['items']['type']);

        $this->assertEquals('string', $schema['properties']['reasoning']['type']);

        $this->assertContains('identity_fields', $schema['required']);
        $this->assertContains('skim_fields', $schema['required']);
    }

    #[Test]
    public function createRemainingResponseSchema_has_required_structure(): void
    {
        $schemaDefinition = $this->builder->createRemainingResponseSchema();

        $this->assertInstanceOf(SchemaDefinition::class, $schemaDefinition);
        $this->assertEquals('Remaining Fields Grouping Response', $schemaDefinition->name);
        $this->assertEquals(SchemaDefinition::TYPE_AGENT_RESPONSE, $schemaDefinition->type);
        $this->assertEquals(SchemaDefinition::FORMAT_JSON, $schemaDefinition->schema_format);

        $schema = $schemaDefinition->schema;
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('extraction_groups', $schema['properties']);

        $extractionGroups = $schema['properties']['extraction_groups'];
        $this->assertEquals('array', $extractionGroups['type']);
        $this->assertEquals('object', $extractionGroups['items']['type']);

        $groupProperties = $extractionGroups['items']['properties'];
        $this->assertArrayHasKey('name', $groupProperties);
        $this->assertArrayHasKey('fields', $groupProperties);
        $this->assertArrayHasKey('search_mode', $groupProperties);

        $this->assertEquals('string', $groupProperties['name']['type']);
        $this->assertEquals('array', $groupProperties['fields']['type']);
        $this->assertEquals('string', $groupProperties['fields']['items']['type']);
        $this->assertEquals('string', $groupProperties['search_mode']['type']);
        $this->assertEquals(['skim', 'exhaustive'], $groupProperties['search_mode']['enum']);

        $this->assertContains('name', $extractionGroups['items']['required']);
        $this->assertContains('fields', $extractionGroups['items']['required']);
        $this->assertContains('search_mode', $extractionGroups['items']['required']);

        $this->assertContains('extraction_groups', $schema['required']);
    }

    #[Test]
    public function buildIdentityPrompt_handles_empty_simple_fields(): void
    {
        $objectTypeInfo = [
            'name'          => 'Empty Object',
            'path'          => 'empty',
            'level'         => 0,
            'is_array'      => false,
            'simple_fields' => [],
        ];

        $config = [
            'group_max_points'   => 10,
            'global_search_mode' => 'intelligent',
        ];

        $prompt = $this->builder->buildIdentityPrompt($objectTypeInfo, $config);

        $this->assertStringContainsString('Empty Object', $prompt);
        $this->assertStringContainsString('Identity Field Selection Task', $prompt);
    }

    #[Test]
    public function buildRemainingPrompt_handles_empty_remaining_fields(): void
    {
        $objectTypeInfo = [
            'name'  => 'Empty Object',
            'path'  => 'empty',
            'level' => 0,
        ];

        $remainingFields = [];

        $config = [
            'group_max_points' => 10,
        ];

        $prompt = $this->builder->buildRemainingPrompt($objectTypeInfo, $remainingFields, $config);

        $this->assertStringContainsString('Empty Object', $prompt);
        $this->assertStringContainsString('Remaining Fields Grouping Task', $prompt);
    }
}
