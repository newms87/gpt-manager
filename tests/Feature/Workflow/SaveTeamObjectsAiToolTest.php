<?php

namespace Tests\Feature\Workflow;

use App\AiTools\SaveTeamObjects\SaveTeamObjectsAiTool;
use App\Models\Agent\Message;
use App\Models\TeamObject\TeamObject;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class SaveTeamObjectsAiToolTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTeam();
    }

    public function test_execute_objectCreated(): void
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
        app(SaveTeamObjectsAiTool::class)->execute($params);

        // Then
        $testObject = TeamObject::firstWhere('name', $name);
        $expected   = [
            'type'        => $type,
            'name'        => $name,
            'description' => $description,
            'url'         => $url,
            'meta'        => $meta,
        ];
        $this->assertArrayIsEqualToArrayOnlyConsideringListOfKeys($expected, $testObject->toArray(), array_keys($expected));
    }

    public function test_execute_objectWithAttributes(): void
    {
        // Given
        $type      = 'Test Object';
        $name      = 'Test B';
        $attrADate = '2021-01-01 00:00:00';
        $attrAUrl  = 'https://example-a.com';
        $attrBUrl  = 'https://example-b.com';
        $params    = [
            'objects' => [
                [
                    'type'       => $type,
                    'name'       => $name,
                    'attributes' => [
                        [
                            'name'       => 'Attribute A',
                            'value'      => 'Value A',
                            'date'       => $attrADate,
                            'source_url' => $attrAUrl,
                        ],
                        [
                            'name'       => 'Attribute B',
                            'value'      => 'Value B',
                            'source_url' => $attrBUrl,
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveTeamObjectsAiTool::class)->execute($params);

        // Then
        $testObject = TeamObject::where('type', $type)->where('name', $name)->first();
        $attributes = $testObject->attributes()->get();

        $this->assertCount(2, $attributes, "Expected 2 attributes to be created for object $name");

        $attributeA           = $attributes->firstWhere('name', 'Attribute A');
        $attributeAStoredFile = StoredFile::find($attributeA->source_stored_file_id);
        $this->assertEquals('Value A', $attributeA->text_value, 'Attribute A value should be Value A');
        $this->assertEquals($attrADate, $attributeA->date, 'Attribute A date should match');
        $this->assertEquals($attrAUrl, $attributeAStoredFile?->url, 'Attribute A url should match');

        $attributeB = $attributes->firstWhere('name', 'Attribute B');
        $this->assertEquals('Value B', $attributeB->text_value, 'Attribute B value should be Value B');
        $this->assertNull($attributeB->date, 'Attribute B date should be null');
    }

    public function test_execute_objectWithAttributesStoresSourceMessages(): void
    {
        // Given
        $type      = 'Test Object';
        $name      = 'Test B';
        $attrADate = '2021-01-01 00:00:00';
        $attrAUrl  = 'https://example-a.com';
        $message   = Message::factory()->create();
        $params    = [
            'objects' => [
                [
                    'type'       => $type,
                    'name'       => $name,
                    'attributes' => [
                        [
                            'name'        => 'Attribute A',
                            'value'       => 'Value A',
                            'date'        => $attrADate,
                            'source_url'  => $attrAUrl,
                            'message_ids' => [$message->id],
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveTeamObjectsAiTool::class)->execute($params);

        // Then
        $testObject = TeamObject::where('type', $type)->where('name', $name)->first();
        $attributes = $testObject->attributes()->get();

        $this->assertCount(1, $attributes, "Expected 1 attribute to be created for object $name");

        $attributeA = $attributes->firstWhere('name', 'Attribute A');
        $this->assertEquals('Value A', $attributeA->text_value, 'Attribute A value should be Value A');
        $this->assertEquals($attrADate, $attributeA->date, 'Attribute A date should match');

        $sourceMessages = $attributeA->sourceMessages()->get();
        $this->assertCount(1, $sourceMessages, 'Expected 1 source message to be created');
        $this->assertEquals($message->id, $sourceMessages->first()->id, 'Source message ID should match');
    }

    public function test_execute_creating2AttributesWithSameNameDifferentDates(): void
    {
        // Given
        $type      = 'Test Object';
        $name      = 'Test B';
        $attrADate = '2021-01-01 00:00:00';
        $attrBDate = '2021-02-05 00:00:00';
        $params    = [
            'objects' => [
                [
                    'type'       => $type,
                    'name'       => $name,
                    'attributes' => [
                        [
                            'name'  => 'Attribute A',
                            'value' => 'Value A',
                            'date'  => $attrADate,
                        ],
                        [
                            'name'  => 'Attribute A',
                            'value' => 'Value B',
                            'date'  => $attrBDate,
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveTeamObjectsAiTool::class)->execute($params);

        // Then
        $testObject = TeamObject::where('type', $type)->where('name', $name)->first();
        $attributes = $testObject->attributes()->get();

        $this->assertCount(2, $attributes, "Expected 2 attributes to be created for object $name");

        $attributeA = $attributes->firstWhere('date', $attrADate);
        $this->assertEquals('Value A', $attributeA->text_value, 'Attribute date A value should be Value A');
        $this->assertEquals($attrADate, $attributeA->date, 'Attribute A date should match');

        $attributeB = $attributes->firstWhere('date', $attrBDate);
        $this->assertEquals('Value B', $attributeB->text_value, 'Attribute date B value should be Value B');
        $this->assertEquals($attrBDate, $attributeB->date, 'Attribute B date should match');
    }

    public function test_execute_objectWithRelationsCreatedCorrectly(): void
    {
        // Given
        $productName   = 'Phone';
        $companyName   = 'Apple';
        $location1Name = 'Denver';
        $location2Name = 'Boulder';
        $params        = [
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
                            'name'              => $location1Name,
                        ],
                        [
                            'relationship_name' => 'Locations',
                            'type'              => 'Location',
                            'name'              => $location2Name,
                        ],
                    ],
                ],
            ],
        ];

        // When
        app(SaveTeamObjectsAiTool::class)->execute($params);

        // Then
        $product = TeamObject::where('type', 'Product')->where('name', $productName)->first();

        $this->assertNotNull($product, 'Product object should be created');

        $productCompany = $product->relatedObjects('Company')->first();
        $this->assertNotNull($productCompany, 'Product should have a relationship with Company');
        $this->assertEquals($companyName, $productCompany->name, 'The related company should be the same company');

        $productLocations = $product->relatedObjects('Locations')->get();
        $this->assertCount(2, $productLocations, 'Product should have 2 locations');
        $locationNames = $productLocations->pluck('name')->toArray();
        $this->assertContains($location1Name, $locationNames, 'Product should have a relationship with Location 1');
        $this->assertContains($location2Name, $locationNames, 'Product should have a relationship with Location 2');
    }
}
