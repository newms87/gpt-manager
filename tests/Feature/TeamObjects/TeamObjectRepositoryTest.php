<?php

namespace Tests\Feature\TeamObjects;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Repositories\TeamObjectRepository;
use BadFunctionCallException;
use Newms87\Danx\Exceptions\ValidationError;
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
        $teamObject = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertNotNull($teamObject->id, "The team object should have been created");
        $this->assertEquals($response['name'], $teamObject->name, "The name should have been saved");
        $this->assertEquals($response['date'], $teamObject->date->toDateString(), "The date should have been saved");
        $this->assertEquals($response['meta'], $teamObject->meta, "The meta data should have been saved");
    }

    public function test_saveTeamObjectUsingSchema_savesScalarAttributeOfTopLevelObject(): void
    {
        // Given
        $response = [
            'name' => 'Dan',
            'dob'  => '1987-11-18',
        ];

        // When
        $teamObject = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

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
            'name' => 'Dan',
            'dob'  => null,
        ];

        // When
        $teamObject = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertEquals(1, $teamObject->attributes()->count(), "Exactly 1 team object attribute should have been created (dob should have been skipped)");
        $nameAttribute = $teamObject->attributes()->firstWhere('name', 'name');
        $this->assertNotNull($nameAttribute, "The name attribute should have been created");
    }

    public function test_saveTeamObjectUsingSchema_withSaveSetToFalse_doesNotSaveScalarAttribute(): void
    {
        // Given
        $response = [
            'name' => 'Dan',
            'dob'  => [
                'value' => '1987-11-18',
                'save'  => false,
            ],
        ];

        // When
        $teamObject = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertEquals(1, $teamObject->attributes()->count(), "Exactly 1 team object attributes should have been created (dob should not be created)");
        $nameAttribute = $teamObject->attributes()->firstWhere('name', 'name');
        $this->assertEquals($response['name'], $nameAttribute->getValue(), "The name should have been saved");
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
                'name'  => 'MyFirstJob',
                'title' => 'Developer',
            ],
        ];

        // When
        $parentObject = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $parentObject->refresh();
        $this->assertNotNull($parentObject->id, "Parent object should have been created");

        // The child object should have type 'Job' and name 'MyFirstJob'
        $childObjectQuery = $parentObject->relatedObjects('job');
        $this->assertEquals(1, $childObjectQuery->count(), "Exactly 1 nested 'Job' team object should have been created");
        $childObject = $childObjectQuery->first();
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
                    'name'   => 'Home',
                    'street' => '123 Main St',
                    'city'   => 'Springfield',
                ],
                [
                    'name'   => 'Work',
                    'street' => '456 Business Rd',
                    'city'   => 'Metropolis',
                ],
            ],
        ];

        // When
        $person = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $person->refresh();
        $this->assertNotNull($person->id, "The parent object should have been created");

        // The child objects should have been created
        $homeAddress = $person->relatedObjects('addresses')->firstWhere('name', 'Home');
        $this->assertNotNull($homeAddress, "The 'Home' address object should have been created");
        $this->assertEquals('Home', $homeAddress->name, "The 'Home' address object should have name 'Home'");
        $this->assertEquals('123 Main St', $homeAddress->attributes()->firstWhere('name', 'street')?->getValue());
        $this->assertEquals('Springfield', $homeAddress->attributes()->firstWhere('name', 'city')?->getValue());

        $workAddress = $person->relatedObjects('addresses')->firstWhere('name', 'Work');
        $this->assertNotNull($workAddress, "The 'Work' address object should have been created");
        $this->assertEquals('Work', $workAddress->name, "The 'Work' address object should have name 'Work'");
        $this->assertEquals('456 Business Rd', $workAddress->attributes()->firstWhere('name', 'street')?->getValue());
        $this->assertEquals('Metropolis', $workAddress->attributes()->firstWhere('name', 'city')?->getValue());
    }

    public function test_saveTeamObjectUsingSchema_withAttributeSource_savesAttributeValueAndSource(): void
    {
        // Given
        $response = [
            'name' => 'Alice',
            'dob'  => [
                'value'    => '1990-05-05',
                'citation' => [
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
        $person = app(TeamObjectRepository::class)->saveTeamObjectUsingSchema(static::$schema, $response);

        // Then
        $person->refresh();
        $this->assertNotNull($person->id, "The parent object should have been created");

        // The attribute should have been created
        $dobAttribute         = $person->attributes()->firstWhere('name', 'dob');
        $expectedDobAttribute = $response['dob'];
        $this->assertNotNull($dobAttribute, "The 'dob' attribute should have been created");
        $this->assertEquals($expectedDobAttribute['value'], $dobAttribute->getValue(), "The 'dob' attribute should have the correct value");
        $this->assertEquals($expectedDobAttribute['citation']['date'], $dobAttribute->date->toDateString(), "The 'dob' attribute should have the correct date");
        $this->assertEquals($expectedDobAttribute['citation']['reason'], $dobAttribute->reason, "The 'dob' attribute should have the correct reason");
        $this->assertEquals($expectedDobAttribute['citation']['confidence'], $dobAttribute->confidence, "The 'dob' attribute should have the correct confidence");

        // The source should have been created
        $source         = $dobAttribute->sources()->first();
        $expectedSource = $response['dob']['citation']['sources'][0];
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
        $teamObject = TeamObject::create([
            'type' => 'TestType',
            'name' => 'TestName',
        ]);
        $data       = [
            'name'     => 'test_attribute',
            'value'    => 'Test Value',
            'citation' => [
                'date'       => '2020-01-01',
                'reason'     => 'Test Reason',
                'confidence' => 100,
                'sources'    => [
                    ['url' => 'http://example.com', 'explanation' => 'Source Explanation'],
                ],
            ],
        ];

        // When
        $attribute = app(TeamObjectRepository::class)->saveTeamObjectAttribute($teamObject, $data['name'], $data);

        // Then
        $this->assertInstanceOf(TeamObjectAttribute::class, $attribute);
        $this->assertEquals($data['name'], $attribute->name);
        $this->assertEquals($data['value'], $attribute->getValue());
        $this->assertEquals($data['citation']['date'], $attribute->date->toDateString());
        $this->assertEquals($data['citation']['reason'], $attribute->reason);
        $this->assertEquals($data['citation']['confidence'], $attribute->confidence);

        // Check the saved source
        $this->assertEquals(1, $attribute->sources()->count());
        $savedSource = $attribute->sources()->first();
        $this->assertEquals($data['citation']['sources'][0]['url'], $savedSource->source_id);
        $this->assertNotNull($savedSource->sourceFile()->first());
        $this->assertEquals($data['citation']['sources'][0]['url'], $savedSource->sourceFile->url);
        $this->assertEquals($data['citation']['sources'][0]['explanation'], $savedSource->explanation);
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
        $repo       = app(TeamObjectRepository::class);
        $teamObject = TeamObject::create([
            'type' => 'TestType',
            'name' => 'TestName',
        ]);

        // When
        $loaded = $repo->loadTeamObject('TestType', $teamObject->id);

        // Then
        $this->assertNotNull($loaded, "Should load the object if it exists");
        $this->assertEquals($teamObject->id, $loaded->id);
    }

    /**
     * Test exception-handling scenarios for better coverage.
     */
    public function test_saveTeamObject_throwsBadFunctionCallExceptionIfNoTypeOrName(): void
    {
        // Given
        $repo = app(TeamObjectRepository::class);

        // Then
        $this->expectException(BadFunctionCallException::class);

        // When
        $repo->saveTeamObject(null, null, []);
    }

    public function test_createRelation_throwsValidationErrorIfNoRelationshipName(): void
    {
        // Given
        $repo   = app(TeamObjectRepository::class);
        $parent = TeamObject::create([
            'type' => 'ParentType',
            'name' => 'ParentName',
        ]);
        $this->expectException(ValidationError::class);

        // When
        $repo->createRelation($parent, null, 'SomeType', 'SomeName');
    }
}
