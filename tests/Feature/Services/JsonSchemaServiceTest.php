<?php

namespace Tests\Feature\Services;

use App\Models\Agent\Agent;
use App\Services\JsonSchema\JsonSchemaService;
use Tests\AuthenticatedTestCase;

class JsonSchemaServiceTest extends AuthenticatedTestCase
{
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
        $this->assertEquals(['type' => ['string', 'null']], $objectSchema['properties']['dob'] ?? null, 'The DOB property should have null type added to indicate it is optional');
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

    public function test_formatAgentResponseSchema_onlySelectedPropertyIsReturned(): void
    {
        // Given
        $schema       = [
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
        $subSelection = [
            'type'     => 'object',
            'children' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAgentResponseSchema($agent);

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

    public function test_formatAgentResponseSchema_onlySelectedObjectsAreReturned(): void
    {
        // Given
        $schema       = [
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
        $subSelection = [
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
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAgentResponseSchema($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'address' => [
                        'type'                 => ['object', 'null'],
                        'title'                => 'Address',
                        'properties'           => [
                            'city' => [
                                'type' => ['string', 'null'],
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

    public function test_formatAgentResponseSchema_selectedObjectSkippedWhenNoPropertiesSelected(): void
    {
        // Given
        $schema       = [
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
        $subSelection = [
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
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAgentResponseSchema($agent);

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

    public function test_formatAgentResponseSchema_onlySelectedArraysAreReturned(): void
    {
        // Given
        $schema       = [
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
        $subSelection = [
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
        $agent        = Agent::factory()->forResponseSchema(['schema' => $schema])->create([
            'response_sub_selection' => $subSelection,
        ]);

        // When
        $formattedResponse = app(JsonSchemaService::class)->formatAgentResponseSchema($agent);

        // Then
        $this->assertEquals([
            'name'   => $formattedResponse['name'],
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'title'                => 'Person',
                'properties'           => [
                    'addresses' => [
                        'type'  => ['array', 'null'],
                        'items' => [
                            'type'                 => ['object', 'null'],
                            'title'                => 'Address',
                            'properties'           => [
                                'city' => [
                                    'type' => ['string', 'null'],
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

    public function test_filterDataBySubSelection_onlySelectedPropertyIsReturned(): void
    {
        // Given
        $data = [
            'name' => 'Dan',
            'dob'  => '2020-01-01',
        ];

        $subSelection = [
            'type'     => 'object',
            'children' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataBySubSelection($data, $subSelection);

        // Then
        $this->assertEquals(['name' => $data['name']], $filteredData);
    }

    public function test_filterDataBySubSelection_onlySelectedPropertyOfChildObjectIsReturned(): void
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

        $subSelection = [
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
        $filteredData = app(JsonSchemaService::class)->filterDataBySubSelection($data, $subSelection);

        // Then
        $this->assertEquals(['address' => ['city' => $data['address']['city']]], $filteredData);
    }

    public function test_filterDataBySubSelection_onlySelectedPropertyOfChildArrayIsReturned(): void
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

        $subSelection = [
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
        $filteredData = app(JsonSchemaService::class)->filterDataBySubSelection($data, $subSelection);

        // Then
        $this->assertEquals(['addresses' => [['city' => 'Springfield'], ['city' => 'Shelbyville']]], $filteredData);
    }

    public function test_filterDataBySubSelection_onlySelectedPropertyArrayOfScalarsIsReturned(): void
    {
        // Given
        $data = [
            'name'      => 'Dan',
            'dob'       => '2020-01-01',
            'nicknames' => ['Danny', 'Hammer', 'Tater Salad'],
        ];

        $subSelection = [
            'type'     => 'object',
            'children' => [
                'nicknames' => [
                    'type' => 'array',
                ],
            ],
        ];

        // When
        $filteredData = app(JsonSchemaService::class)->filterDataBySubSelection($data, $subSelection);

        // Then
        $this->assertEquals(['nicknames' => $data['nicknames']], $filteredData);
    }
}
