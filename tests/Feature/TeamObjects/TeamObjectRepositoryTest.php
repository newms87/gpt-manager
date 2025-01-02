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

    public function test_saveTeamObjectFromResponseSchema_savesPropertiesOfTopLevelObject(): void
    {
        // Given
        $response = [
            'name' => 'Dan',
            'date' => '1987-11-18',
        ];

        // When
        $teamObject = app(TeamObjectRepository::class)->saveTeamObjectFromResponseSchema(static::$schema, $response);

        // Then
        $teamObject->refresh();
        $this->assertNotNull($teamObject->id, "The team object should have been created");
        $this->assertEquals($response['name'], $teamObject->name, "The name should have been saved");
        $this->assertEquals($response['date'], $teamObject->date->toDateString(), "The date should have been saved");
    }
}
