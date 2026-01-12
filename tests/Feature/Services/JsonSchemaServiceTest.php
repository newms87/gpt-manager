<?php

namespace Tests\Feature\Services;

use App\Services\JsonSchema\JsonSchemaService;
use Tests\AuthenticatedTestCase;

class JsonSchemaServiceTest extends AuthenticatedTestCase
{
    public function test_formatAndCleanSchema_providesValidJsonSchema(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type' => 'string',
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => 'string',
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndCleanSchema_requiresAllPropertiesOfNestedObjects(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'       => 'object',
                'properties' => [
                    'nested_a' => [
                        'type' => 'string',
                    ],
                    'nested_b' => [
                        'type'       => 'object',
                        'properties' => [
                            'nested-key' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'                 => 'object',
                        'properties'           => [
                            'nested_a' => [
                                'type' => 'string',
                            ],
                            'nested_b' => [
                                'type'                 => 'object',
                                'properties'           => [
                                    'nested-key' => [
                                        'type' => 'string',
                                    ],
                                ],
                                'required'             => ['nested-key'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required'             => ['nested_a', 'nested_b'],
                        'additionalProperties' => false,
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndCleanSchema_requiresAllPropertiesOfNestedArrays(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'nested_a' => [
                            'type' => 'string',
                        ],
                        'nested_b' => [
                            'type'       => 'object',
                            'properties' => [
                                'nested-key' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'properties'           => [
                                'nested_a' => [
                                    'type' => 'string',
                                ],
                                'nested_b' => [
                                    'type'                 => 'object',
                                    'properties'           => [
                                        'nested-key' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                    'required'             => ['nested-key'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'required'             => ['nested_a', 'nested_b'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndCleanSchema_addsDescriptionToProperties(): void
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type'        => 'string',
                'description' => 'A test description',
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type'        => 'string',
                        'description' => 'A test description',
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndCleanSchema_addsEnumToProperties()
    {
        // Given
        $name     = 'test-schema';
        $response = [
            'key' => [
                'type' => 'string',
                'enum' => ['value1', 'value2'],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndCleanSchema($name, $response);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'key' => [
                        'type' => 'string',
                        'enum' => ['value1', 'value2'],
                    ],
                ],
                'required'             => ['key'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndCleanSchema_withAdditionalObjectProperty_additionalPropertyShouldBeOptional(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertEquals(['type' => 'string'], $objectSchema['properties']['dob'] ?? null, 'The DOB property should have null type added to indicate it is optional');
    }

    public function test_formatAndCleanSchema_allPropertiesShouldBeInRequiredList(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertEquals(['name', 'dob'], $objectSchema['required'] ?? null, 'The name, dob and property_meta properties should have been added to the required list');
    }

    public function test_formatAndCleanSchema_useIdEnabled_idShouldBeAddedToPropertiesAndRequiredList(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->useId()->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertNotNull($objectSchema['properties']['id'] ?? null, 'The id should also be added list');
        $this->assertEquals(['name', 'dob', 'id'], $objectSchema['required'] ?? null, 'The id should also be in the required list');
    }

    public function test_formatAndCleanSchema_usePropertyMetaEnabled_propertyMetaShouldBeAddedToPropertiesAndRequiredList(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'    => [
                    'type' => 'string',
                ],
                'address' => [
                    'type'       => 'object',
                    'title'      => 'Address',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->usePropertyMeta()->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertNotNull($objectSchema['properties']['property_meta'] ?? null, 'The property_meta should also be added to the properties');
        $this->assertNotNull($objectSchema['$defs']['property_meta'] ?? null, 'The property_meta should also be added to the definitions');
        $this->assertEquals(['name', 'address', 'property_meta'], $objectSchema['required'] ?? null, 'The id should also be in the required list');
        $this->assertNotNull($objectSchema['properties']['address']['properties']['property_meta'] ?? null, 'The property_meta should also be added to the properties of the address object');
    }

    public function test_formatAndCleanSchema_propertyMetaShouldBeAddedToSchema(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->useCitations()->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertNotNull($objectSchema['properties']['property_meta'] ?? null, 'property_meta should be added to the schema');
    }

    public function test_formatAndCleanSchema_withoutCitations_citedValuesMissingFromPropertyMeta(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertNull($objectSchema['$defs']['property_meta']['items']['properties']['citation'] ?? null, 'citation should not be added to the schema');
    }

    public function test_formatAndCleanSchema_citedValuesAddedToSchema(): void
    {
        // Given
        $name   = 'person';
        $schema = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->useCitations()->formatAndCleanSchema($name, $schema);

        // Then
        $objectSchema = $formattedSchema['schema'];
        $this->assertNotNull($objectSchema['$defs']['property_meta']['items']['properties']['citation'] ?? null, 'citation should be added to the schema');
    }

    public function test_formatAndFilterSchema_onlySelectedPropertyIsReturned(): void
    {
        // Given
        $schema           = [
            'type'        => 'object',
            'title'       => 'Person',
            'description' => 'The important person',
            'properties'  => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Name of the person',
                ],
                'dob'  => [
                    'type' => 'string',
                ],
            ],
        ];
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'description'          => 'The important person',
                'properties'           => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Name of the person',
                    ],
                ],
                'required'             => ['name'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndFilterSchema_allowCreationOfObject_objectIdIsNullAndNameIsRequiredInSchema(): void
    {
        // Given
        $schema           = [
            'type'        => 'object',
            'title'       => 'Person',
            'description' => 'The important person',
            'properties'  => [
                'dob' => [
                    'type' => 'string',
                ],
            ],
        ];
        $fragmentSelector = [
            'type'   => 'object',
            'create' => true,
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'type'                 => 'object',
            'title'                => 'Person',
            'description'          => 'The important person',
            'properties'           => [
                'id'   => JsonSchemaService::$idCreateDef,
                'name' => [
                    'type'        => 'string',
                    'description' => 'Name of the Person',
                ],
            ],
            'required'             => ['id', 'name'],
            'additionalProperties' => false,
        ], $formattedResponse['schema'] ?? null);
    }

    public function test_formatAndFilterSchema_allowUpdatingAnObject_objectIdIsRequiredInSchema(): void
    {
        // Given
        $schema           = [
            'type'        => 'object',
            'title'       => 'Person',
            'description' => 'The important person',
            'properties'  => [
                'dob' => [
                    'type' => 'string',
                ],
            ],
        ];
        $fragmentSelector = [
            'type'   => 'object',
            'update' => true,
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'type'                 => 'object',
            'title'                => 'Person',
            'description'          => 'The important person',
            'properties'           => [
                'id' => JsonSchemaService::$idUpdateDef,
            ],
            'required'             => ['id'],
            'additionalProperties' => false,
        ], $formattedResponse['schema'] ?? null);
    }

    public function test_formatAndFilterSchema_allowUpdateAndCreateObject_objectIdAndNameAreBothOptionalInSchema(): void
    {
        // Given
        $schema           = [
            'type'        => 'object',
            'title'       => 'Person',
            'description' => 'The important person',
            'properties'  => [
                'dob' => [
                    'type' => 'string',
                ],
            ],
        ];
        $fragmentSelector = [
            'type'   => 'object',
            'create' => true,
            'update' => true,
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'type'                 => 'object',
            'title'                => 'Person',
            'description'          => 'The important person',
            'properties'           => [
                'id'   => JsonSchemaService::$idCreateOrUpdateDef,
                'name' => [
                    'type'        => ['string', 'null'],
                    'description' => 'Name of the Person. ' . JsonSchemaService::$nameOptionalDescription,
                ],
            ],
            'required'             => ['id', 'name'],
            'additionalProperties' => false,
        ], $formattedResponse['schema'] ?? null);
    }

    public function test_formatAndFilterSchema_onlySelectedObjectsAreReturned(): void
    {
        // Given
        $schema           = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'    => [
                    'type' => 'string',
                ],
                'dob'     => [
                    'type' => 'string',
                ],
                'job'     => [
                    'type'       => 'object',
                    'title'      => 'Job',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'address' => [
                    'type'       => 'object',
                    'title'      => 'Address',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                        ],
                        'city'   => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'address' => [
                    'type'     => 'object',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'address' => [
                        'type'                 => 'object',
                        'title'                => 'Address',
                        'properties'           => [
                            'city' => [
                                'type' => 'string',
                            ],
                        ],
                        'required'             => ['city'],
                        'additionalProperties' => false,
                    ],
                ],
                'required'             => ['address'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndFilterSchema_selectedObjectSkippedWhenNoPropertiesSelected(): void
    {
        // Given
        $schema           = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'    => [
                    'type' => 'string',
                ],
                'address' => [
                    'type'       => 'object',
                    'title'      => 'Address',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                        ],
                        'city'   => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'name'    => [
                    'type' => 'string',
                ],
                'address' => [
                    'type' => 'object',
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'name' => [
                        'type' => 'string',
                    ],
                ],
                'required'             => ['name'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_formatAndFilterSchema_onlySelectedArraysAreReturned(): void
    {
        // Given
        $schema           = [
            'type'       => 'object',
            'title'      => 'Person',
            'properties' => [
                'name'      => [
                    'type' => 'string',
                ],
                'dob'       => [
                    'type' => 'string',
                ],
                'job'       => [
                    'type'       => 'object',
                    'title'      => 'Job',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'addresses' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'title'      => 'Address',
                        'properties' => [
                            'street' => [
                                'type' => 'string',
                            ],
                            'city'   => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'addresses' => [
                    'type'     => 'array',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAndFilterSchema('', $schema, $fragmentSelector);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'addresses' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'title'                => 'Address',
                            'properties'           => [
                                'city' => [
                                    'type' => 'string',
                                ],
                            ],
                            'required'             => ['city'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'             => ['addresses'],
                'additionalProperties' => false,
            ],
        ], $formattedResponse);
    }

    public function test_filterDataByFragmentSelector_onlySelectedPropertyIsReturned(): void
    {
        // Given
        $data = [
            'name' => 'Dan',
            'dob'  => '2020-01-01',
        ];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataByFragmentSelector($data, $fragmentSelector);

        // Then
        $this->assertEquals(['name' => $data['name']], $filteredData);
    }

    public function test_filterDataByFragmentSelector_onlySelectedPropertyOfChildObjectIsReturned(): void
    {
        // Given
        $data = [
            'name'    => 'Dan',
            'dob'     => '2020-01-01',
            'address' => [
                'street' => '123 Main St',
                'city'   => 'Springfield',
            ],
        ];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'address' => [
                    'type'     => 'object',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataByFragmentSelector($data, $fragmentSelector);

        // Then
        $this->assertEquals(['address' => ['city' => $data['address']['city']]], $filteredData);
    }

    public function test_filterDataByFragmentSelector_onlySelectedPropertyOfChildArrayIsReturned(): void
    {
        // Given
        $data = [
            'name'      => 'Dan',
            'dob'       => '2020-01-01',
            'addresses' => [
                ['street' => '123 Main St', 'city' => 'Springfield'],
                ['street' => '456 Elm St', 'city' => 'Shelbyville'],
            ],
        ];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'addresses' => [
                    'type'     => 'array',
                    'children' => [
                        'city' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataByFragmentSelector($data, $fragmentSelector);

        // Then
        $this->assertEquals(['addresses' => [['city' => 'Springfield'], ['city' => 'Shelbyville']]], $filteredData);
    }

    public function test_filterDataByFragmentSelector_onlySelectedPropertyArrayOfScalarsIsReturned(): void
    {
        // Given
        $data = [
            'name'      => 'Dan',
            'dob'       => '2020-01-01',
            'nicknames' => ['Danny', 'Hammer', 'Tater Salad'],
        ];

        $fragmentSelector = [
            'type'     => 'object',
            'children' => [
                'nicknames' => [
                    'type' => 'array',
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataByFragmentSelector($data, $fragmentSelector);

        // Then
        $this->assertEquals(['nicknames' => $data['nicknames']], $filteredData);
    }

    public function test_formatAndCleanSchema_refEntriesArePassedThroughUnchanged(): void
    {
        // Given
        $name   = 'test-schema';
        $schema = [
            'type'       => 'object',
            'properties' => [
                'name'   => [
                    'type' => 'string',
                ],
                'source' => [
                    '$ref' => '#/$defs/pageSource',
                ],
            ],
        ];

        // When
        $formattedSchema = app(JsonSchemaService::class)->formatAndCleanSchema($name, $schema);

        // Then
        $this->assertEquals([
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'name'   => [
                        'type' => 'string',
                    ],
                    'source' => [
                        '$ref' => '#/$defs/pageSource',
                    ],
                ],
                'required'             => ['name', 'source'],
                'additionalProperties' => false,
            ],
        ], $formattedSchema);
    }
}
