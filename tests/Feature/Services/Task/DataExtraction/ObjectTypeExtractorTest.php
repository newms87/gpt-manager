<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Services\Task\DataExtraction\ObjectTypeExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObjectTypeExtractorTest extends TestCase
{
    protected ObjectTypeExtractor $extractor;

    public function setUp(): void
    {
        parent::setUp();
        $this->extractor = app(ObjectTypeExtractor::class);
    }

    #[Test]
    public function extracts_single_object_type_from_simple_schema(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'User';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'User',
            'properties' => [
                'name'  => ['type' => 'string', 'title' => 'Name'],
                'email' => ['type' => 'string', 'title' => 'Email'],
                'age'   => ['type' => 'integer', 'title' => 'Age'],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(1, $objectTypes);
        $this->assertEquals([
            'name'          => 'User',
            'path'          => '',
            'level'         => 0,
            'parent_type'   => null,
            'is_array'      => false,
            'simple_fields' => [
                'name'  => ['title' => 'Name', 'description' => null],
                'email' => ['title' => 'Email', 'description' => null],
                'age'   => ['title' => 'Age', 'description' => null],
            ],
        ], $objectTypes[0]);
    }

    #[Test]
    public function extracts_nested_object_type(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'Order';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'Order',
            'properties' => [
                'order_id' => ['type' => 'string', 'title' => 'Order ID'],
                'customer' => [
                    'type'       => 'object',
                    'title'      => 'Customer',
                    'properties' => [
                        'name'  => ['type' => 'string', 'title' => 'Name'],
                        'email' => ['type' => 'string', 'title' => 'Email'],
                    ],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(2, $objectTypes);

        // Root object
        $this->assertEquals('Order', $objectTypes[0]['name']);
        $this->assertEquals('', $objectTypes[0]['path']);
        $this->assertEquals(0, $objectTypes[0]['level']);
        $this->assertNull($objectTypes[0]['parent_type']);
        $this->assertFalse($objectTypes[0]['is_array']);
        $this->assertEquals([
            'order_id' => ['title' => 'Order ID', 'description' => null],
        ], $objectTypes[0]['simple_fields']);

        // Nested object
        $this->assertEquals('Customer', $objectTypes[1]['name']);
        $this->assertEquals('customer', $objectTypes[1]['path']);
        $this->assertEquals(1, $objectTypes[1]['level']);
        $this->assertEquals('Order', $objectTypes[1]['parent_type']);
        $this->assertFalse($objectTypes[1]['is_array']);
        $this->assertEquals([
            'name'  => ['title' => 'Name', 'description' => null],
            'email' => ['title' => 'Email', 'description' => null],
        ], $objectTypes[1]['simple_fields']);
    }

    #[Test]
    public function extracts_array_of_objects(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'Document';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'Document',
            'properties' => [
                'title'    => ['type' => 'string', 'title' => 'Title'],
                'comments' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'title'      => 'Comment',
                        'properties' => [
                            'author' => ['type' => 'string', 'title' => 'Author'],
                            'text'   => ['type' => 'string', 'title' => 'Text'],
                        ],
                    ],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(2, $objectTypes);

        // Root object
        $this->assertEquals('Document', $objectTypes[0]['name']);
        $this->assertEquals([
            'title' => ['title' => 'Title', 'description' => null],
        ], $objectTypes[0]['simple_fields']);

        // Array of objects
        $this->assertEquals('Comment', $objectTypes[1]['name']);
        $this->assertEquals('comments', $objectTypes[1]['path']);
        $this->assertEquals(1, $objectTypes[1]['level']);
        $this->assertEquals('Document', $objectTypes[1]['parent_type']);
        $this->assertTrue($objectTypes[1]['is_array']);
        $this->assertEquals([
            'author' => ['title' => 'Author', 'description' => null],
            'text'   => ['title' => 'Text', 'description' => null],
        ], $objectTypes[1]['simple_fields']);
    }

    #[Test]
    public function handles_complex_nested_schema(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'Demand';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'Demand',
            'properties' => [
                'name'   => ['type' => 'string', 'title' => 'Name'],
                'date'   => ['type' => 'string', 'title' => 'Date'],
                'client' => [
                    'type'       => 'object',
                    'title'      => 'Client',
                    'properties' => [
                        'name'    => ['type' => 'string', 'title' => 'Client Name'],
                        'email'   => ['type' => 'string', 'title' => 'Email'],
                        'address' => [
                            'type'       => 'object',
                            'title'      => 'Address',
                            'properties' => [
                                'street' => ['type' => 'string', 'title' => 'Street'],
                                'city'   => ['type' => 'string', 'title' => 'City'],
                            ],
                        ],
                    ],
                ],
                'care_summaries' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'title'      => 'Care Summary',
                        'properties' => [
                            'provider' => ['type' => 'string', 'title' => 'Provider'],
                            'date'     => ['type' => 'string', 'title' => 'Date'],
                        ],
                    ],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(4, $objectTypes);

        // Root: Demand (level 0)
        $this->assertEquals('Demand', $objectTypes[0]['name']);
        $this->assertEquals(0, $objectTypes[0]['level']);
        $this->assertEquals([
            'name' => ['title' => 'Name', 'description' => null],
            'date' => ['title' => 'Date', 'description' => null],
        ], $objectTypes[0]['simple_fields']);

        // Client (level 1, nested object)
        $this->assertEquals('Client', $objectTypes[1]['name']);
        $this->assertEquals('client', $objectTypes[1]['path']);
        $this->assertEquals(1, $objectTypes[1]['level']);
        $this->assertEquals('Demand', $objectTypes[1]['parent_type']);
        $this->assertFalse($objectTypes[1]['is_array']);
        $this->assertEquals([
            'name'  => ['title' => 'Client Name', 'description' => null],
            'email' => ['title' => 'Email', 'description' => null],
        ], $objectTypes[1]['simple_fields']);

        // Address (level 2, nested within Client)
        $this->assertEquals('Address', $objectTypes[2]['name']);
        $this->assertEquals('client.address', $objectTypes[2]['path']);
        $this->assertEquals(2, $objectTypes[2]['level']);
        $this->assertEquals('Client', $objectTypes[2]['parent_type']);
        $this->assertFalse($objectTypes[2]['is_array']);

        // Care Summary (level 1, array of objects)
        $this->assertEquals('Care Summary', $objectTypes[3]['name']);
        $this->assertEquals('care_summaries', $objectTypes[3]['path']);
        $this->assertEquals(1, $objectTypes[3]['level']);
        $this->assertEquals('Demand', $objectTypes[3]['parent_type']);
        $this->assertTrue($objectTypes[3]['is_array']);
    }

    #[Test]
    public function handles_missing_titles_by_converting_property_keys(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'TestSchema';
        $schema->schema = [
            'type'       => 'object',
            'properties' => [
                'user_profile' => [
                    'type'       => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name'  => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(2, $objectTypes);

        // Root should use schema name when no title
        $this->assertEquals('TestSchema', $objectTypes[0]['name']);

        // Nested object should convert snake_case key to Title Case
        $this->assertEquals('User Profile', $objectTypes[1]['name']);
        $this->assertEquals([
            'first_name' => ['title' => 'First Name', 'description' => null],
            'last_name'  => ['title' => 'Last Name', 'description' => null],
        ], $objectTypes[1]['simple_fields']);
    }

    #[Test]
    public function classifies_array_of_primitives_as_simple_field(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'Product';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'Product',
            'properties' => [
                'name' => ['type' => 'string', 'title' => 'Name'],
                'tags' => [
                    'type'  => 'array',
                    'title' => 'Tags',
                    'items' => ['type' => 'string'],
                ],
                'prices' => [
                    'type'  => 'array',
                    'title' => 'Prices',
                    'items' => ['type' => 'number'],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        // Should only have one object type (root)
        $this->assertCount(1, $objectTypes);

        // All fields should be classified as simple (including arrays of primitives)
        $this->assertEquals([
            'name'   => ['title' => 'Name', 'description' => null],
            'tags'   => ['title' => 'Tags', 'description' => null],
            'prices' => ['title' => 'Prices', 'description' => null],
        ], $objectTypes[0]['simple_fields']);
    }

    #[Test]
    public function handles_union_types_with_null(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'NullableSchema';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'NullableSchema',
            'properties' => [
                'nullable_string' => ['type' => ['string', 'null'], 'title' => 'Nullable String'],
                'nullable_object' => [
                    'type'       => ['object', 'null'],
                    'title'      => 'Nullable Object',
                    'properties' => [
                        'field' => ['type' => 'string', 'title' => 'Field'],
                    ],
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(2, $objectTypes);

        // nullable_string should be treated as simple field
        $this->assertArrayHasKey('nullable_string', $objectTypes[0]['simple_fields']);

        // nullable_object should create a nested object type
        $this->assertEquals('Nullable Object', $objectTypes[1]['name']);
        $this->assertEquals('nullable_object', $objectTypes[1]['path']);
    }

    #[Test]
    public function includes_field_descriptions_when_present(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'WithDescriptions';
        $schema->schema = [
            'type'       => 'object',
            'properties' => [
                'field_with_description' => [
                    'type'        => 'string',
                    'title'       => 'Field Title',
                    'description' => 'This is a description',
                ],
                'field_without_description' => [
                    'type'  => 'string',
                    'title' => 'No Description',
                ],
            ],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertEquals(
            'This is a description',
            $objectTypes[0]['simple_fields']['field_with_description']['description']
        );

        $this->assertNull(
            $objectTypes[0]['simple_fields']['field_without_description']['description']
        );
    }

    #[Test]
    public function returns_empty_array_for_non_object_schema(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'NotAnObject';
        $schema->schema = [
            'type' => 'string',
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertEmpty($objectTypes);
    }

    #[Test]
    public function handles_empty_properties(): void
    {
        $schema         = new SchemaDefinition;
        $schema->name   = 'EmptyObject';
        $schema->schema = [
            'type'       => 'object',
            'title'      => 'EmptyObject',
            'properties' => [],
        ];

        $objectTypes = $this->extractor->extractObjectTypes($schema);

        $this->assertCount(1, $objectTypes);
        $this->assertEquals('EmptyObject', $objectTypes[0]['name']);
        $this->assertEmpty($objectTypes[0]['simple_fields']);
    }
}
