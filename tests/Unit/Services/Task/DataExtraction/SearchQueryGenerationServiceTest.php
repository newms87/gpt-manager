<?php

namespace Tests\Unit\Services\Task\DataExtraction;

use App\Services\Task\DataExtraction\SearchQueryGenerationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchQueryGenerationServiceTest extends TestCase
{
    protected SearchQueryGenerationService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(SearchQueryGenerationService::class);
    }

    // ========================================
    // buildIndexedSchema() Tests
    // ========================================

    #[Test]
    public function build_indexed_schema_returns_schema_and_hash_mapping_keys(): void
    {
        $items = [
            ['name' => 'Item 1', 'date' => '2024-01-01'],
        ];
        $identityFields = ['name', 'date'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        $this->assertArrayHasKey('schema', $result);
        $this->assertArrayHasKey('hashMapping', $result);
    }

    #[Test]
    public function build_indexed_schema_uses_hash_keys_not_numeric_indices(): void
    {
        $items = [
            ['name' => 'Item 1', 'date' => '2024-01-01'],
            ['name' => 'Item 2', 'date' => '2024-01-02'],
        ];
        $identityFields = ['name', 'date'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        // Hash keys should be 8-character strings
        $hashKeys = array_keys($result['schema']['properties']);
        foreach ($hashKeys as $key) {
            $this->assertIsString($key);
            $this->assertEquals(8, strlen($key));
            // Should be hexadecimal characters
            $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $key);
        }
    }

    #[Test]
    public function build_indexed_schema_produces_json_object_not_array(): void
    {
        $items = [
            ['name' => 'Item 1', 'date' => '2024-01-01'],
            ['name' => 'Item 2', 'date' => '2024-01-02'],
        ];
        $identityFields = ['name', 'date'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);
        $json   = json_encode($result['schema']);

        // Should NOT contain array-style properties like "properties":[
        $this->assertStringNotContainsString('"properties":[', $json);
        // Should contain object-style properties like "properties":{
        $this->assertStringContainsString('"properties":{', $json);
    }

    #[Test]
    public function build_indexed_schema_has_correct_structure(): void
    {
        $items = [
            ['name' => 'Item 1'],
        ];
        $identityFields = ['name'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        $schema = $result['schema'];

        // Check top-level structure
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('$defs', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }

    #[Test]
    public function build_indexed_schema_required_array_contains_hash_keys(): void
    {
        $items = [
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
            ['name' => 'Item 3'],
        ];
        $identityFields = ['name'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        $hashKeys = array_keys($result['schema']['properties']);
        $required = $result['schema']['required'];

        $this->assertCount(3, $required);
        $this->assertEquals($hashKeys, $required);
    }

    #[Test]
    public function build_indexed_schema_hash_mapping_maps_hash_to_index(): void
    {
        $items = [
            ['name' => 'Item 0'],
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ];
        $identityFields = ['name'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        $hashMapping = $result['hashMapping'];

        // Should have 3 mappings
        $this->assertCount(3, $hashMapping);

        // Values should be 0, 1, 2 (original indices)
        $values = array_values($hashMapping);
        sort($values);
        $this->assertEquals([0, 1, 2], $values);
    }

    #[Test]
    public function build_indexed_schema_with_empty_array_returns_empty_structure(): void
    {
        $items          = [];
        $identityFields = ['name'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        $this->assertEmpty($result['schema']['properties']);
        $this->assertEmpty($result['schema']['required']);
        $this->assertEmpty($result['hashMapping']);
    }

    #[Test]
    public function build_indexed_schema_normalizes_non_sequential_input_keys(): void
    {
        // Test with non-sequential array keys
        $items = [
            5  => ['name' => 'Item A'],
            10 => ['name' => 'Item B'],
            15 => ['name' => 'Item C'],
        ];
        $identityFields = ['name'];

        $result = $this->service->buildIndexedSchema($items, $identityFields);

        // Hash mapping values should be 0, 1, 2 (sequential) not 5, 10, 15
        $values = array_values($result['hashMapping']);
        sort($values);
        $this->assertEquals([0, 1, 2], $values);
    }

    // ========================================
    // buildYamlContext() Tests
    // ========================================

    #[Test]
    public function build_yaml_context_produces_valid_yaml(): void
    {
        $items = [
            ['name' => 'Chiropractic Adjustment', 'date' => '2024-10-22'],
            ['name' => 'Traction', 'date' => '2024-10-23'],
        ];

        $yaml = $this->service->buildYamlContext($items);

        $this->assertIsString($yaml);
        $this->assertStringContainsString('items:', $yaml);
        $this->assertStringContainsString('Chiropractic Adjustment', $yaml);
        $this->assertStringContainsString('Traction', $yaml);
    }

    #[Test]
    public function build_yaml_context_with_empty_array(): void
    {
        $items = [];

        $yaml = $this->service->buildYamlContext($items);

        $this->assertIsString($yaml);
        $this->assertStringContainsString('items:', $yaml);
    }

    #[Test]
    public function build_yaml_context_preserves_values_from_array(): void
    {
        $items = [
            ['name' => 'Test Name', 'value' => 123],
        ];

        $yaml = $this->service->buildYamlContext($items);

        $this->assertStringContainsString('Test Name', $yaml);
        $this->assertStringContainsString('123', $yaml);
    }

    // ========================================
    // buildSearchQueryItemSchema() Tests
    // ========================================

    #[Test]
    public function build_search_query_item_schema_creates_array_type(): void
    {
        $identityFields = ['name'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $this->assertEquals('array', $schema['type']);
    }

    #[Test]
    public function build_search_query_item_schema_has_min_items_of_three(): void
    {
        $identityFields = ['name'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $this->assertEquals(3, $schema['minItems']);
    }

    #[Test]
    public function build_search_query_item_schema_includes_all_identity_fields(): void
    {
        $identityFields = ['name', 'date', 'code'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $itemProperties = $schema['items']['properties'];
        $this->assertArrayHasKey('name', $itemProperties);
        $this->assertArrayHasKey('date', $itemProperties);
        $this->assertArrayHasKey('code', $itemProperties);
    }

    #[Test]
    public function build_search_query_item_schema_uses_string_search_for_string_fields(): void
    {
        $identityFields = ['name'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $this->assertEquals(['$ref' => '#/$defs/stringSearch'], $schema['items']['properties']['name']);
    }

    #[Test]
    public function build_search_query_item_schema_uses_date_search_for_date_field(): void
    {
        // The 'date' field is a native TeamObject column recognized as a date
        $identityFields = ['date'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $this->assertEquals(['$ref' => '#/$defs/dateSearch'], $schema['items']['properties']['date']);
    }

    #[Test]
    public function build_search_query_item_schema_uses_date_search_for_schema_date_format(): void
    {
        $identityFields = ['accident_date'];
        $fullSchema     = [
            'properties' => [
                'accident_date' => ['type' => 'string', 'format' => 'date'],
            ],
        ];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields, $fullSchema);

        $this->assertEquals(['$ref' => '#/$defs/dateSearch'], $schema['items']['properties']['accident_date']);
    }

    #[Test]
    public function build_search_query_item_schema_uses_boolean_search_for_boolean_type(): void
    {
        $identityFields = ['is_active'];
        $fullSchema     = [
            'properties' => [
                'is_active' => ['type' => 'boolean'],
            ],
        ];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields, $fullSchema);

        $this->assertEquals(['$ref' => '#/$defs/booleanSearch'], $schema['items']['properties']['is_active']);
    }

    #[Test]
    public function build_search_query_item_schema_uses_integer_search_for_integer_type(): void
    {
        $identityFields = ['count'];
        $fullSchema     = [
            'properties' => [
                'count' => ['type' => 'integer'],
            ],
        ];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields, $fullSchema);

        $this->assertEquals(['$ref' => '#/$defs/integerSearch'], $schema['items']['properties']['count']);
    }

    #[Test]
    public function build_search_query_item_schema_uses_numeric_search_for_number_type(): void
    {
        $identityFields = ['amount'];
        $fullSchema     = [
            'properties' => [
                'amount' => ['type' => 'number'],
            ],
        ];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields, $fullSchema);

        $this->assertEquals(['$ref' => '#/$defs/numericSearch'], $schema['items']['properties']['amount']);
    }

    #[Test]
    public function build_search_query_item_schema_has_required_fields(): void
    {
        $identityFields = ['name', 'date'];

        $schema = $this->service->buildSearchQueryItemSchema($identityFields);

        $this->assertEquals(['name', 'date'], $schema['items']['required']);
    }

    // ========================================
    // buildItemDescription() Tests
    // ========================================

    #[Test]
    public function build_item_description_includes_index(): void
    {
        $item = ['name' => 'Test Item'];

        $description = $this->service->buildItemDescription($item, 0);

        $this->assertStringContainsString('item 0', $description);
    }

    #[Test]
    public function build_item_description_includes_field_values(): void
    {
        $item = ['name' => 'Chiropractic Adjustment', 'date' => '2024-10-22'];

        $description = $this->service->buildItemDescription($item, 0);

        $this->assertStringContainsString("name='Chiropractic Adjustment'", $description);
        $this->assertStringContainsString("date='2024-10-22'", $description);
    }

    #[Test]
    public function build_item_description_skips_null_values(): void
    {
        $item = ['name' => 'Test', 'date' => null];

        $description = $this->service->buildItemDescription($item, 0);

        $this->assertStringContainsString("name='Test'", $description);
        $this->assertStringNotContainsString('date=', $description);
    }

    #[Test]
    public function build_item_description_skips_empty_string_values(): void
    {
        $item = ['name' => 'Test', 'code' => ''];

        $description = $this->service->buildItemDescription($item, 0);

        $this->assertStringContainsString("name='Test'", $description);
        $this->assertStringNotContainsString('code=', $description);
    }

    #[Test]
    public function build_item_description_json_encodes_non_string_values(): void
    {
        $item = ['name' => 'Test', 'count' => 42, 'active' => true];

        $description = $this->service->buildItemDescription($item, 0);

        $this->assertStringContainsString("count='42'", $description);
        $this->assertStringContainsString("active='true'", $description);
    }

    // ========================================
    // parseResponse() Tests
    // ========================================

    #[Test]
    public function parse_response_maps_hashes_back_to_indices(): void
    {
        $response = [
            'abc12345' => ['search_query' => [['name' => ['Test']]]],
            'def67890' => ['search_query' => [['name' => ['Other']]]],
        ];
        $hashMapping = [
            'abc12345' => 0,
            'def67890' => 1,
        ];

        $result = $this->service->parseResponse($response, $hashMapping);

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertEquals([['name' => ['Test']]], $result[0]);
        $this->assertEquals([['name' => ['Other']]], $result[1]);
    }

    #[Test]
    public function parse_response_with_start_index_offsets_results(): void
    {
        $response = [
            'abc12345' => ['search_query' => [['name' => ['Test']]]],
        ];
        $hashMapping = [
            'abc12345' => 0,
        ];

        $result = $this->service->parseResponse($response, $hashMapping, 10);

        $this->assertArrayHasKey(10, $result);
        $this->assertEquals([['name' => ['Test']]], $result[10]);
    }

    #[Test]
    public function parse_response_handles_missing_hash_keys(): void
    {
        $response = [
            'abc12345' => ['search_query' => [['name' => ['Test']]]],
            // 'def67890' is missing
        ];
        $hashMapping = [
            'abc12345' => 0,
            'def67890' => 1,
        ];

        $result = $this->service->parseResponse($response, $hashMapping);

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertEquals([['name' => ['Test']]], $result[0]);
        $this->assertNull($result[1]);
    }

    #[Test]
    public function parse_response_handles_empty_response(): void
    {
        $response    = [];
        $hashMapping = [
            'abc12345' => 0,
        ];

        $result = $this->service->parseResponse($response, $hashMapping);

        $this->assertArrayHasKey(0, $result);
        $this->assertNull($result[0]);
    }

    #[Test]
    public function parse_response_handles_missing_search_query_key(): void
    {
        $response = [
            'abc12345' => ['other_key' => 'value'], // No search_query key
        ];
        $hashMapping = [
            'abc12345' => 0,
        ];

        $result = $this->service->parseResponse($response, $hashMapping);

        $this->assertNull($result[0]);
    }

    #[Test]
    public function parse_response_handles_non_array_search_query(): void
    {
        $response = [
            'abc12345' => ['search_query' => 'not_an_array'],
        ];
        $hashMapping = [
            'abc12345' => 0,
        ];

        $result = $this->service->parseResponse($response, $hashMapping);

        $this->assertNull($result[0]);
    }

    // ========================================
    // getSearchTypeDefinitions() Tests
    // ========================================

    #[Test]
    public function get_search_type_definitions_includes_string_search_for_string_fields(): void
    {
        $identityFields = ['name'];

        $defs = $this->service->getSearchTypeDefinitions($identityFields);

        $this->assertArrayHasKey('stringSearch', $defs);
    }

    #[Test]
    public function get_search_type_definitions_includes_date_search_for_date_fields(): void
    {
        $identityFields = ['date'];

        $defs = $this->service->getSearchTypeDefinitions($identityFields);

        $this->assertArrayHasKey('dateSearch', $defs);
    }

    #[Test]
    public function get_search_type_definitions_only_includes_used_types(): void
    {
        // Only string fields - should not include dateSearch, etc.
        $identityFields = ['name', 'description'];

        $defs = $this->service->getSearchTypeDefinitions($identityFields);

        $this->assertArrayHasKey('stringSearch', $defs);
        $this->assertArrayNotHasKey('dateSearch', $defs);
        $this->assertArrayNotHasKey('booleanSearch', $defs);
        $this->assertArrayNotHasKey('integerSearch', $defs);
        $this->assertArrayNotHasKey('numericSearch', $defs);
    }

    #[Test]
    public function get_search_type_definitions_includes_multiple_types_when_needed(): void
    {
        $identityFields = ['name', 'date'];

        $defs = $this->service->getSearchTypeDefinitions($identityFields);

        $this->assertArrayHasKey('stringSearch', $defs);
        $this->assertArrayHasKey('dateSearch', $defs);
        $this->assertCount(2, $defs);
    }

    #[Test]
    public function get_search_type_definitions_with_schema_type_detection(): void
    {
        $identityFields = ['name', 'amount', 'is_active'];
        $schema         = [
            'properties' => [
                'name'      => ['type' => 'string'],
                'amount'    => ['type' => 'number'],
                'is_active' => ['type' => 'boolean'],
            ],
        ];

        $defs = $this->service->getSearchTypeDefinitions($identityFields, $schema);

        $this->assertArrayHasKey('stringSearch', $defs);
        $this->assertArrayHasKey('numericSearch', $defs);
        $this->assertArrayHasKey('booleanSearch', $defs);
    }

    #[Test]
    public function get_search_type_definitions_does_not_duplicate_types(): void
    {
        // Multiple string fields should only result in one stringSearch definition
        $identityFields = ['name', 'description', 'code'];

        $defs = $this->service->getSearchTypeDefinitions($identityFields);

        $this->assertCount(1, $defs);
        $this->assertArrayHasKey('stringSearch', $defs);
    }

    // ========================================
    // Round-trip Integration Tests
    // ========================================

    #[Test]
    public function build_indexed_schema_and_parse_response_round_trip(): void
    {
        $items = [
            ['name' => 'Item A', 'date' => '2024-01-01'],
            ['name' => 'Item B', 'date' => '2024-01-02'],
            ['name' => 'Item C', 'date' => '2024-01-03'],
        ];
        $identityFields = ['name', 'date'];

        // Build the schema
        $schemaData  = $this->service->buildIndexedSchema($items, $identityFields);
        $hashMapping = $schemaData['hashMapping'];

        // Simulate an LLM response using the hash keys
        $response = [];
        foreach ($hashMapping as $hashKey => $index) {
            $response[$hashKey] = [
                'search_query' => [
                    ['name' => ['Item ' . chr(65 + $index)], 'date' => ['2024-01-0' . ($index + 1)]],
                ],
            ];
        }

        // Parse the response
        $result = $this->service->parseResponse($response, $hashMapping);

        // Verify all indices are present and correctly mapped
        $this->assertCount(3, $result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);

        // Verify data integrity
        $this->assertStringContainsString('Item A', json_encode($result[0]));
        $this->assertStringContainsString('Item B', json_encode($result[1]));
        $this->assertStringContainsString('Item C', json_encode($result[2]));
    }
}
