<?php

namespace Tests\Feature\Workflow;

use App\AiTools\SaveObjects\SaveObjectsAiTool;
use App\Services\Database\SchemaManager;
use Tests\AuthenticatedTestCase;

class SaveObjectsAiToolTest extends AuthenticatedTestCase
{
    public SchemaManager $schemaManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->user->currentTeam->update(['namespace' => 'testing']);
        $this->schemaManager = new SchemaManager('testing', database_path('schemas/object_relationships.yaml'));
    }

    public function tearDown(): void
    {
        $this->schemaManager->query('object_attributes')->where('id', '>', 0)->delete();
        $this->schemaManager->query('object_relationships')->where('id', '>', 0)->delete();
        $this->schemaManager->query('objects')->where('id', '>', 0)->delete();
        parent::tearDown();
    }

    public function test_execute_objectWithNoRelationsOrAttributesCreated(): void
    {
        // Given
        $type        = 'Test Object';
        $name        = 'Test A';
        $description = 'This is a test object';
        $url         = 'https://example.com';
        $meta        = [
            'key' => 'value',
        ];
        $params      = [
            'objects' => [
                [
                    'type'        => $type,
                    'name'        => $name,
                    'description' => $description,
                    'url'         => $url,
                    'meta'        => $meta,
                ],
            ],
        ];

        // When
        app(SaveObjectsAiTool::class)->execute($params);

        // Then
        $testObject = (array)$this->schemaManager->query('objects')->where('name', $name)->first();
        $expected   = [
            'type'        => $type,
            'name'        => $name,
            'description' => $description,
            'url'         => $url,
            'meta'        => $meta,
        ];

        $this->assertArrayIsEqualToArrayOnlyConsideringListOfKeys($expected, $testObject, array_keys($expected));
    }

    public function test_execute_objectWithAttributesCreatedCorrectly(): void
    {
        // Given
        $type   = 'Test Object';
        $name   = 'Test B';
        $params = [
            'objects' => [
                [
                    'type'       => $type,
                    'name'       => $name,
                    'attributes' => [
                        [
                            'name'  => 'Attribute A',
                            'value' => 'Value A',
                        ],
                        [
                            'name'  => 'Attribute B',
                            'value' => 'Value B',
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveObjectsAiTool::class)->execute($params);

        // Then
        $testObject = $this->schemaManager->query('objects')->where('type', $type)->where('name', $name)->first();
        $attributes = $this->schemaManager->query('object_attributes')->where('object_id', $testObject->id)->get();

        $this->assertCount(2, $attributes, "Expected 2 attributes to be created for object $name");

        $attributeA = $attributes->firstWhere('name', 'Attribute A');
        $this->assertEquals('Value A', $attributeA->text_value, 'Attribute A value should be Value A');

        $attributeB = $attributes->firstWhere('name', 'Attribute B');
        $this->assertEquals('Value B', $attributeB->text_value, 'Attribute B value should be Value B');
    }

    public function test_execute_objectWithRelationsCreatedCorrectly(): void
    {
        // Given
        $productName  = 'Phone';
        $companyName  = 'Apple';
        $locationName = 'Denver';
        $params       = [
            'objects' => [
                [
                    'type'      => 'Product',
                    'name'      => $productName,
                    'relations' => [
                        [
                            'relationship_name' => 'Company',
                            'type'              => 'Company',
                            'name'              => $companyName,
                        ],
                        [
                            'relationship_name' => 'Locations',
                            'type'              => 'Location',
                            'name'              => $locationName,
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveObjectsAiTool::class)->execute($params);

        // Then
        $product  = $this->schemaManager->query('objects')->where('type', 'Product')->where('name', $productName)->first();
        $company  = $this->schemaManager->query('objects')->where('type', 'Company')->where('name', $companyName)->first();
        $location = $this->schemaManager->query('objects')->where('type', 'Location')->where('name', $locationName)->first();

        $this->assertNotNull($product, 'Product object should be created');
        $this->assertNotNull($company, 'Company object should be created');
        $this->assertNotNull($location, 'Location object should be created');

        $productCompany = $this->schemaManager->query('object_relationships')->where('object_id', $product->id)->where('related_object_id', $company->id)->first();
        $this->assertNotNull($productCompany, 'Product should have a relationship with Company');

        $productLocation = $this->schemaManager->query('object_relationships')->where('object_id', $product->id)->where('related_object_id', $location->id)->first();
        $this->assertNotNull($productLocation, 'Product should have a relationship with Location');
    }
}
