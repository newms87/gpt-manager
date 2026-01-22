<?php

namespace Tests\Unit\Services\Task\DataExtraction;

use App\Services\Task\DataExtraction\ConflictResolutionService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tests for ConflictResolutionService.
 *
 * Tests the core logic of conflict detection and resolution prompt building.
 * Integration with LLM is tested in feature tests.
 */
class ConflictResolutionServiceTest extends TestCase
{
    protected ConflictResolutionService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConflictResolutionService::class);
    }

    /**
     * Helper to call protected methods.
     */
    protected function callProtectedMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($this->service);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $args);
    }

    #[Test]
    public function extract_page_numbers_returns_unique_pages(): void
    {
        $conflicts = [
            ['existing_page' => 1, 'new_page' => 3],
            ['existing_page' => 3, 'new_page' => 5], // 3 is duplicate
            ['existing_page' => null, 'new_page' => 7],
        ];

        $result = $this->callProtectedMethod('extractPageNumbers', [$conflicts]);

        $this->assertCount(4, $result);
        $this->assertContains(1, $result);
        $this->assertContains(3, $result);
        $this->assertContains(5, $result);
        $this->assertContains(7, $result);
    }

    #[Test]
    public function extract_page_numbers_handles_empty_conflicts(): void
    {
        $result = $this->callProtectedMethod('extractPageNumbers', [[]]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function build_prompt_contains_conflict_details(): void
    {
        $conflicts = [
            [
                'field_name'     => 'name',
                'existing_value' => 'Treatment for headaches',
                'existing_page'  => 1,
                'new_value'      => 'Cervical sprains',
                'new_page'       => 3,
            ],
        ];

        $schemaDefinition = [
            'properties' => [
                'name' => ['description' => 'The name of the treatment'],
            ],
        ];

        $result = $this->callProtectedMethod('buildPrompt', [$conflicts, $schemaDefinition]);

        // Should contain the conflict YAML structure
        $this->assertStringContainsString('field: name', $result);
        $this->assertStringContainsString('Treatment for headaches', $result);
        $this->assertStringContainsString('Cervical sprains', $result);
        $this->assertStringContainsString('source_page: 1', $result);
        $this->assertStringContainsString('source_page: 3', $result);
        $this->assertStringContainsString('The name of the treatment', $result);
    }

    #[Test]
    public function build_response_schema_creates_correct_structure(): void
    {
        $conflicts = [
            ['field_name' => 'name'],
            ['field_name' => 'description'],
        ];

        $result = $this->callProtectedMethod('buildResponseSchema', [$conflicts]);

        // Should be an object type
        $this->assertEquals('object', $result['type']);

        // Should have properties for each conflicting field
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('description', $result['properties']);

        // Each field should have resolved_value and source_page
        $nameSchema = $result['properties']['name'];
        $this->assertEquals('object', $nameSchema['type']);
        $this->assertArrayHasKey('resolved_value', $nameSchema['properties']);
        $this->assertArrayHasKey('source_page', $nameSchema['properties']);
        $this->assertEquals(['resolved_value', 'source_page'], $nameSchema['required']);

        // Required should list both fields
        $this->assertContains('name', $result['required']);
        $this->assertContains('description', $result['required']);
    }

    #[Test]
    public function get_field_description_finds_direct_properties(): void
    {
        $schemaDefinition = [
            'properties' => [
                'name' => ['description' => 'Direct field description'],
            ],
        ];

        $result = $this->callProtectedMethod('getFieldDescription', ['name', $schemaDefinition]);

        $this->assertEquals('Direct field description', $result);
    }

    #[Test]
    public function get_field_description_finds_nested_properties(): void
    {
        $schemaDefinition = [
            'properties' => [
                'care_summary' => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => ['description' => 'Nested field description'],
                    ],
                ],
            ],
        ];

        $result = $this->callProtectedMethod('getFieldDescription', ['name', $schemaDefinition]);

        $this->assertEquals('Nested field description', $result);
    }

    #[Test]
    public function get_field_description_finds_array_item_properties(): void
    {
        $schemaDefinition = [
            'properties' => [
                'diagnoses' => [
                    'type'  => 'array',
                    'items' => [
                        'properties' => [
                            'name' => ['description' => 'Array item field description'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->callProtectedMethod('getFieldDescription', ['name', $schemaDefinition]);

        $this->assertEquals('Array item field description', $result);
    }

    #[Test]
    public function get_field_description_returns_default_when_not_found(): void
    {
        $schemaDefinition = [
            'properties' => [],
        ];

        $result = $this->callProtectedMethod('getFieldDescription', ['unknown_field', $schemaDefinition]);

        $this->assertEquals('No description available', $result);
    }

    #[Test]
    public function format_value_for_prompt_escapes_quotes(): void
    {
        $value = 'Treatment with "special" characters';

        $result = $this->callProtectedMethod('formatValueForPrompt', [$value]);

        $this->assertEquals('Treatment with \\"special\\" characters', $result);
    }

    #[Test]
    public function format_value_for_prompt_escapes_newlines(): void
    {
        $value = "Line 1\nLine 2";

        $result = $this->callProtectedMethod('formatValueForPrompt', [$value]);

        $this->assertEquals('Line 1\\nLine 2', $result);
    }

    #[Test]
    public function format_value_for_prompt_handles_non_strings(): void
    {
        $value = ['key' => 'value'];

        $result = $this->callProtectedMethod('formatValueForPrompt', [$value]);

        // Should JSON encode arrays
        $this->assertStringContainsString('key', $result);
        $this->assertStringContainsString('value', $result);
    }

    #[Test]
    public function parse_result_extracts_resolved_values(): void
    {
        $result = [
            'name' => [
                'resolved_value' => 'Correct treatment name',
                'source_page'    => 3,
            ],
            'description' => [
                'resolved_value' => 'Correct description',
                'source_page'    => 1,
            ],
        ];

        $conflicts = [
            ['field_name' => 'name'],
            ['field_name' => 'description'],
        ];

        $parsed = $this->callProtectedMethod('parseResult', [$result, $conflicts]);

        $this->assertEquals('Correct treatment name', $parsed['resolved_data']['name']);
        $this->assertEquals('Correct description', $parsed['resolved_data']['description']);
        $this->assertEquals(3, $parsed['resolved_page_sources']['name']);
        $this->assertEquals(1, $parsed['resolved_page_sources']['description']);
    }

    #[Test]
    public function parse_result_handles_missing_fields(): void
    {
        $result = [
            'name' => [
                'resolved_value' => 'Correct treatment name',
                'source_page'    => 3,
            ],
            // description is missing from response
        ];

        $conflicts = [
            ['field_name' => 'name'],
            ['field_name' => 'description'],
        ];

        $parsed = $this->callProtectedMethod('parseResult', [$result, $conflicts]);

        // Should have name but not description
        $this->assertArrayHasKey('name', $parsed['resolved_data']);
        $this->assertArrayNotHasKey('description', $parsed['resolved_data']);
    }

    #[Test]
    public function resolve_conflicts_returns_empty_when_no_conflicts(): void
    {
        // We need to mock dependencies for this test, so we'll just test the edge case
        // by calling the public method with empty conflicts

        $result = $this->service->resolveConflicts(
            $this->createMock(\App\Models\Task\TaskRun::class),
            $this->createMock(\App\Models\Task\TaskProcess::class),
            [], // Empty conflicts
            collect(),
            []
        );

        $this->assertEmpty($result['resolved_data']);
        $this->assertEmpty($result['resolved_page_sources']);
    }
}
