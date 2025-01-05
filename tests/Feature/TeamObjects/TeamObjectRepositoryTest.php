<?php

namespace Tests\Feature\TeamObjects;

use App\Repositories\TeamObjectRepository;
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

    }
}
