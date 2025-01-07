<?php

namespace Tests\Feature\TeamObjects;

use App\Models\TeamObject\TeamObject;
use App\Repositories\TeamObjectRepository;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TeamObjectForAgentsResourceTest extends AuthenticatedTestCase
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

    /**
     * Test getFullyLoadedTeamObject – ensures attributes and relationships are recursively loaded.
     */
    public function test_make_recursivelyLoadsData(): void
    {
        // Given
        $repo   = app(TeamObjectRepository::class);
        $parent = TeamObject::create([
            'type' => 'ParentType',
            'name' => 'ParentName',
        ]);
        $child  = TeamObject::create([
            'type' => 'ChildType',
            'name' => 'ChildName',
        ]);
        $repo->saveTeamObjectRelationship($parent, 'child', $child);
        $repo->saveTeamObjectAttribute($parent, 'parent_attribute', ['value' => 'ParentVal']);
        $repo->saveTeamObjectAttribute($child, 'child_attribute', ['value' => 'ChildVal']);

        $teamObject = $repo->loadTeamObject('ParentType', $parent->id);

        // When
        $loaded = TeamObjectForAgentsResource::make($teamObject);

        // Then
        $this->assertNotNull($loaded, "Should load the parent object");
        $this->assertNotNull($loaded['parent_attribute'] ?? null, "Should have loaded the parent's attribute");
        $this->assertEquals('ParentVal', $loaded['parent_attribute'], "The parent attribute should be attached");

        // Relationship should be loaded
        $childRelation = $loaded['child'] ?? null;
        $this->assertNotNull($childRelation, "The child relationship should be loaded");
        $this->assertEquals($child->id, $childRelation['id'], "The child object should be attached");
        $this->assertEquals('ChildVal', $childRelation['child_attribute'] ?? null);
    }

    public function test_loadTeamObjectAttributes_loadsAttributesAndSources(): void
    {
        // Given
        $teamObject = TeamObject::create([
            'type' => 'TestType',
            'name' => 'TestName',
        ]);
        app(TeamObjectRepository::class)->saveTeamObjectAttribute($teamObject, 'test_attr', [
            'value' => 'some val',
        ]);

        // Make sure the object is fresh
        $teamObject->refresh();

        // When
        $loadedAttributes = TeamObjectForAgentsResource::loadTeamObjectAttributes($teamObject);

        // Then
        $loadedAttr = $loadedAttributes['test_attr'] ?? null;
        $this->assertNotNull($loadedAttr, "Attribute should be loaded into the object's attributes array");
        $this->assertEquals('some val', $loadedAttr);
    }
}
