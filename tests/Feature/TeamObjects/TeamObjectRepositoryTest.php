<?php

namespace Tests\Feature\TeamObjects;

use App\Models\Agent\AgentThreadMessage;
use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Repositories\TeamObjectRepository;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TeamObjectRepositoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    static array $schema = [
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

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTeam();
    }

    public function test_resolveTeamObject_withSameTypeNameAndSchemaDefinition_resolvesExistingObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->create(['team_id' => team()->id]);
        $input      = [
            'schema_definition_id' => $teamObject->schema_definition_id,
        ];

        // When
        $resolvedTeamObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNotNull($resolvedTeamObject);
        $this->assertEquals($teamObject->id, $resolvedTeamObject->id, "The resolved object should match the original");
    }

    public function test_resolvedTeamObject_withSameTypeNameButNullSchemaDefinition_doesNotResolveObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->create();
        $input      = [];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_resolvedTeamObject_withSameTypeNameButDifferentSchemaDefinition_doesNotResolveObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->create();
        $input      = [
            'schema_definition_id' => SchemaDefinition::factory()->create()->id,
        ];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_resolveTeamObject_withSameTypeNameAndRootObject_resolvesExistingObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forRootObject()->create(['team_id' => team()->id]);
        $input      = [
            'root_object_id' => $teamObject->root_object_id,
        ];

        // When
        $resolvedTeamObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNotNull($resolvedTeamObject);
        $this->assertEquals($teamObject->id, $resolvedTeamObject->id, "The resolved object should match the original");
    }

    public function test_resolvedTeamObject_withSameTypeNameButNullRootObject_doesNotResolveObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forRootObject()->create();
        $input      = [];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_resolvedTeamObject_withSameTypeNameButDifferentRootObject_doesNotResolveObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forRootObject()->create();
        $input      = [
            'root_object_id' => TeamObject::factory()->create()->id,
        ];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_resolvedTeamObject_withSameTypeNameRootObjectAndSchemaDefinition_resolvesObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->forRootObject()->create(['team_id' => team()->id]);
        $input      = [
            'schema_definition_id' => $teamObject->schema_definition_id,
            'root_object_id'       => $teamObject->root_object_id,
        ];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNotNull($resolvedObject);
        $this->assertEquals($teamObject->id, $resolvedObject->id, "The resolved object should match the original");
    }

    public function test_resolvedTeamObject_withSameTypeNameRootObjectButNullSchemaDefinition_doesNotResolvesObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->forRootObject()->create();
        $input      = [
            'root_object_id' => $teamObject->root_object_id,
        ];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_resolvedTeamObject_withSameTypeNameSchemaDefinitionButNullRootObject_doesNotResolvesObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->forRootObject()->create();
        $input      = [
            'schema_definition_id' => $teamObject->schema_definition_id,
        ];

        // When
        $resolvedObject = app(TeamObjectRepository::class)->resolveTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertNull($resolvedObject);
    }

    public function test_createTeamObject_withSameTypeNameAndDifferentSchemaDefinition_createsNewObject(): void
    {
        // Given
        $teamObject = TeamObject::factory()->forSchemaDefinition()->create();
        $input      = [
            'schema_definition_id' => SchemaDefinition::factory()->create()->id,
        ];

        // When
        $updatedTeamObject = app(TeamObjectRepository::class)->createTeamObject($teamObject->type, $teamObject->name, $input);

        // Then
        $this->assertEquals(2, TeamObject::where('type', $teamObject->type)->where('name', $teamObject->name)->count(), "There should be 2 of the same type + name");
        $this->assertNotEquals($teamObject->id, $updatedTeamObject->id, "The existing object and updated object should be different");
    }

    /**
     * Test exception-handling scenarios for better coverage.
     */
    public function test_createTeamObject_throwsExceptionIfNoTypeOrName(): void
    {
        // Given
        $repo = app(TeamObjectRepository::class);

        // Then
        $this->expectException(Exception::class);

        // When
        $repo->createTeamObject(null, null, []);
    }

    public function test_saveTeamObjectUsingSchema_savesPropertiesOfTopLevelObject(): void
    {
        // Given
        $response = [
            'name' => 'Dan',
            'date' => '1987-11-18',
            'meta' => [
                'foo' => 'bar',
            ],
        ];

        // When
        $teamObject = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertNotNull($teamObject->id, "The team object should have been created");
        $this->assertEquals($teamObject->id, $response['id'] ?? null, "The ID should have been set in the passed by ref object");
        $this->assertEquals($response['name'], $teamObject->name, "The name should have been saved");
        $this->assertEquals($response['date'], $teamObject->date->toDateString(), "The date should have been saved");
        $this->assertEquals($response['meta'], $teamObject->meta, "The meta data should have been saved");
    }

    public function test_saveTeamObjectUsingSchema_savesScalarAttributeOfTopLevelObject(): void
    {
        // Given
        $response = [
            'name'          => 'Dan',
            'dob'           => '1987-11-18',
            'property_meta' => [
                ['property_name' => 'name'],
                ['property_name' => 'dob'],
            ],
        ];

        // When
        $teamObject = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertEquals(2, $teamObject->attributes()->count(), "Exactly 2 team object attributes should have been created (name and dob)");
        $nameAttribute = $teamObject->attributes()->firstWhere('name', 'name');
        $this->assertEquals($response['name'], $nameAttribute->getValue(), "The name should have been saved");
        $dobAttribute = $teamObject->attributes()->firstWhere('name', 'dob');
        $this->assertEquals($response['dob'], $dobAttribute->getValue(), "The dob should have been saved");
    }

    public function test_saveTeamObjectUsingSchema_withNullScalarValue_doesNotSaveAttribute(): void
    {
        // Given
        $response = [
            'name'          => 'Dan',
            'dob'           => null,
            'property_meta' => [
                ['property_name' => 'name'],
            ],
        ];

        // When
        $teamObject = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertEquals(1, $teamObject->attributes()->count(), "Exactly 1 team object attribute should have been created (dob should have been skipped)");
        $nameAttribute = $teamObject->attributes()->firstWhere('name', 'name');
        $this->assertNotNull($nameAttribute, "The name attribute should have been created");
    }

    /**
     * Test saving a nested object (e.g. 'job') via the JSON schema.
     */
    public function test_saveTeamObjectUsingSchema_withNestedObject_savesNestedObject(): void
    {
        // Given
        $response = [
            'name' => 'Alice',
            'job'  => [
                // The repository will expect a "name" here to avoid throwing an exception
                'name'          => 'MyFirstJob',
                'title'         => 'Developer',
                'property_meta' => [
                    ['property_name' => 'name'],
                    ['property_name' => 'title'],
                ],
            ],
        ];

        // When
        $parentObject = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $parentObject->refresh();
        $this->assertNotNull($parentObject->id, "Parent object should have been created");

        // The child object should have type 'Job' and name 'MyFirstJob'
        $childObjectQuery = $parentObject->relatedObjects('job');
        $this->assertEquals(1, $childObjectQuery->count(), "Exactly 1 nested 'Job' team object should have been created");
        $childObject = $childObjectQuery->first();
        $this->assertEquals($childObject->id, $response['job']['id'] ?? null, "The ID should have been set in the passed by ref object");
        $this->assertEquals('Job', $childObject->type, "The child object should have type 'Job'");
        $this->assertEquals('MyFirstJob', $childObject->name, "The child object should have name 'MyFirstJob'");
        $this->assertEquals('Developer', $childObject->attributes()->firstWhere('name', 'title')?->getValue());
    }

    /**
     * Test saving an array of objects (e.g. 'addresses') via the JSON schema.
     */
    public function test_saveTeamObjectUsingSchema_withArrayOfObjects_savesMultipleTeamObjects(): void
    {
        // Given
        $response = [
            'name'      => 'Alice',
            'dob'       => '1990-05-05',
            'addresses' => [
                [
                    'name'          => 'Home',
                    'street'        => '123 Main St',
                    'city'          => 'Springfield',
                    'property_meta' => [
                        ['property_name' => 'name'],
                        ['property_name' => 'street'],
                        ['property_name' => 'city'],
                    ],
                ],
                [
                    'name'          => 'Work',
                    'street'        => '456 Business Rd',
                    'city'          => 'Metropolis',
                    'property_meta' => [
                        ['property_name' => 'name'],
                        ['property_name' => 'street'],
                        ['property_name' => 'city'],
                    ],
                ],
            ],
        ];

        // When
        $person = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $person->refresh();
        $this->assertNotNull($person->id, "The parent object should have been created");

        // The child objects should have been created
        $homeAddress = $person->relatedObjects('addresses')->firstWhere('name', 'Home');
        $this->assertNotNull($homeAddress, "The 'Home' address object should have been created");
        $this->assertEquals($homeAddress->id, $response['addresses'][0]['id'] ?? null, "The ID should have been set in the passed by ref object");
        $this->assertEquals('Home', $homeAddress->name, "The 'Home' address object should have name 'Home'");
        $this->assertEquals('123 Main St', $homeAddress->attributes()->firstWhere('name', 'street')?->getValue());
        $this->assertEquals('Springfield', $homeAddress->attributes()->firstWhere('name', 'city')?->getValue());

        $workAddress = $person->relatedObjects('addresses')->firstWhere('name', 'Work');
        $this->assertNotNull($workAddress, "The 'Work' address object should have been created");
        $this->assertEquals($workAddress->id, $response['addresses'][1]['id'] ?? null, "The ID should have been set in the passed by ref object");
        $this->assertEquals('Work', $workAddress->name, "The 'Work' address object should have name 'Work'");
        $this->assertEquals('456 Business Rd', $workAddress->attributes()->firstWhere('name', 'street')?->getValue());
        $this->assertEquals('Metropolis', $workAddress->attributes()->firstWhere('name', 'city')?->getValue());
    }

    public function test_saveTeamObjectUsingSchema_withAttributeSource_savesAttributeValueAndSource(): void
    {
        // Given
        $response = [
            'name'          => 'Alice',
            'dob'           => [
                'value' => '1990-05-05',
            ],
            'property_meta' => [
                [
                    'property_name' => 'dob',
                    'citation'      => [
                        'date'       => '2020-01-01',
                        'reason'     => 'Test Reason',
                        'confidence' => 100,
                        'sources'    => [
                            ['url' => 'http://example.com', 'explanation' => 'Source Explanation'],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $person = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $person->refresh();
        $this->assertNotNull($person->id, "The parent object should have been created");

        // The attribute should have been created
        $dobAttribute         = $person->attributes()->firstWhere('name', 'dob');
        $expectedPropertyMeta = $response['property_meta'][0];
        $this->assertNotNull($dobAttribute, "The 'dob' attribute should have been created");
        $this->assertEquals($response['dob']['value'], $dobAttribute->getValue(), "The 'dob' attribute should have the correct value");
        $this->assertEquals($expectedPropertyMeta['citation']['reason'], $dobAttribute->reason, "The 'dob' attribute should have the correct reason");
        $this->assertEquals($expectedPropertyMeta['citation']['confidence'], $dobAttribute->confidence, "The 'dob' attribute should have the correct confidence");

        // The source should have been created
        $source         = $dobAttribute->sources()->first();
        $expectedSource = $expectedPropertyMeta['citation']['sources'][0];
        $this->assertNotNull($source, "The source should have been created");
        $this->assertEquals($expectedSource['url'], $source->source_id, "The source should have the correct URL");
        $this->assertNotNull($source->sourceFile()->first(), "The source should have a source file");
        $this->assertEquals($expectedSource['url'], $source->sourceFile->url, "The source file should have the correct URL");
        $this->assertEquals($expectedSource['explanation'], $source->explanation, "The source should have the correct explanation");
    }

    /**
     * Test saveTeamObjectAttribute – ensures an attribute can be saved, including sources.
     */
    public function test_saveTeamObjectAttribute_savesAttributeAndSources(): void
    {
        // Given
        $teamObject   = TeamObject::factory()->create([
            'type' => 'TestType',
            'name' => 'TestName',
        ]);
        $data         = [
            'name'  => 'test_attribute',
            'value' => 'Test Value',
        ];
        $propertyMeta = [
            [
                'property_name' => $data['name'],
                'citation'      => [
                    'date'       => '2020-01-01',
                    'reason'     => 'Test Reason',
                    'confidence' => 100,
                    'sources'    => [
                        ['url' => 'http://example.com', 'explanation' => 'Source Explanation'],
                    ],
                ],
            ],
        ];

        // When
        $attribute = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectAttribute($teamObject, $data['name'], $data, $propertyMeta);

        // Then
        $citation = $propertyMeta[0]['citation'];
        $this->assertInstanceOf(TeamObjectAttribute::class, $attribute);
        $this->assertEquals($data['name'], $attribute->name);
        $this->assertEquals($data['value'], $attribute->getValue());
        $this->assertEquals($citation['reason'], $attribute->reason);
        $this->assertEquals($citation['confidence'], $attribute->confidence);

        // Check the saved source
        $this->assertEquals(1, $attribute->sources()->count());
        $savedSource = $attribute->sources()->first();
        $this->assertEquals($citation['sources'][0]['url'], $savedSource->source_id);
        $this->assertNotNull($savedSource->sourceFile()->first());
        $this->assertEquals($citation['sources'][0]['url'], $savedSource->sourceFile->url);
        $this->assertEquals($citation['sources'][0]['explanation'], $savedSource->explanation);
    }

    public function test_saveTeamObjectAttributeSource_savesUrl(): void
    {
        // Given
        $teamObjectAttribute = TeamObjectAttribute::factory()->create();
        $source              = ['url' => 'http://example.com', 'explanation' => 'Source Explanation'];

        // When
        $attributeSource = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectAttributeSource($teamObjectAttribute, $source);

        // Then
        $this->assertNotNull($attributeSource->stored_file_id, 'StoredFile should have been created and associated');
        $this->assertNull($attributeSource->agent_thread_message_id, 'AgentThreadMessage ID should be null');
        $this->assertEquals($source['url'], $attributeSource->source_id, 'Source ID should be set to the URL');
        $this->assertEquals($source['url'], $attributeSource->sourceFile->url);
        $this->assertEquals($source['explanation'], $attributeSource->explanation);
    }

    public function test_saveTeamObjectAttributeSource_savesMessage(): void
    {
        // Given
        $teamObjectAttribute = TeamObjectAttribute::factory()->create();
        $message             = AgentThreadMessage::factory()->create();
        $source              = ['message_id' => $message->id, 'explanation' => 'Source Explanation'];

        // When
        $attributeSource = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectAttributeSource($teamObjectAttribute, $source);

        // Then
        $this->assertNotNull($attributeSource->agent_thread_message_id);
        $this->assertNull($attributeSource->stored_file_id, 'No source file was given');
        $this->assertEquals($source['message_id'], $attributeSource->source_id);
        $this->assertEquals($source['explanation'], $attributeSource->explanation);
    }

    public function test_saveTeamObjectAttributeSource_savesFile(): void
    {
        // Given
        $teamObjectAttribute = TeamObjectAttribute::factory()->create();
        $storedFile          = StoredFile::factory()->create();
        $source              = ['file_id' => $storedFile->id, 'explanation' => 'Source Explanation'];

        // When
        $attributeSource = app(JSONSchemaDataToDatabaseMapper::class)->saveTeamObjectAttributeSource($teamObjectAttribute, $source);

        // Then
        $this->assertNull($attributeSource->agent_thread_message_id, 'No message was given');
        $this->assertNotNull($attributeSource->stored_file_id);
        $this->assertEquals($storedFile->id, $attributeSource->source_id, 'Source ID should be set to the File ID');
        $this->assertEquals($storedFile->id, $attributeSource->stored_file_id);
        $this->assertEquals($source['explanation'], $attributeSource->explanation);
    }

    /**
     * Test loadTeamObject – ensures a TeamObject can be loaded by type and ID.
     */
    public function test_loadTeamObject_returnsNullIfNotFound(): void
    {
        // Given
        $type = 'non-existent-type';
        $id   = 999999;

        // When
        $loaded = app(TeamObjectRepository::class)->loadTeamObject($type, $id);

        // Then
        $this->assertNull($loaded, "Should return null if the object doesn't exist");
    }

    public function test_loadTeamObject_returnsTeamObjectIfFound(): void
    {
        // Given
        $teamObject = TeamObject::factory()->create([
            'type' => 'TestType',
            'name' => 'TestName',
        ]);

        // When
        $loaded = app(TeamObjectRepository::class)->loadTeamObject('TestType', $teamObject->id);

        // Then
        $this->assertNotNull($loaded, "Should load the object if it exists");
        $this->assertEquals($teamObject->id, $loaded->id);
    }

    public function test_createRelation_throwsValidationErrorIfNoRelationshipName(): void
    {
        // Given
        $parent = TeamObject::factory()->create([
            'type' => 'ParentType',
            'name' => 'ParentName',
        ]);
        $this->expectException(ValidationError::class);

        // When
        app(TeamObjectRepository::class)->createRelation($parent, null, 'SomeType', 'SomeName');
    }
}
