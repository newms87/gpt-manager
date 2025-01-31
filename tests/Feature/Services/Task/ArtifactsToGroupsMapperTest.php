<?php

namespace Feature\Services\Task;

use App\Models\Workflow\Artifact;
use App\Services\Task\ArtifactsToGroupsMapper;
use Tests\AuthenticatedTestCase;

class ArtifactsToGroupsMapperTest extends AuthenticatedTestCase
{
    public function test_map_defaultBehavior_singleGroupProduced(): void
    {
        // Given
        $jsonContent = ['name' => 'Alice'];
        $artifacts   = [
            new Artifact(['json_content' => $jsonContent]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $groupArtifacts = $groups['default'] ?? [];
        $this->assertCount(1, $groupArtifacts, 'The group key should have been set in the groups list pointing to an array w/ 1 artifact');
        $this->assertEquals($jsonContent, $groups['default'][0]->json_content, 'The artifact in the group should match the passed in artifact');
    }

    public function test_map_defaultBehaviorWith2Artifacts_singleGroupProducedWith2Artifacts(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $defaultGroup = $groups['default'];
        usort($defaultGroup, fn(Artifact $a, Artifact $b) => $a->json_content['name'] <=> $b->json_content['name']);
        $this->assertEquals($jsonContentA, $defaultGroup[0]->json_content, 'The groups 1st artifact should contain JSON content A');
        $this->assertEquals($jsonContentB, $defaultGroup[1]->json_content, 'The groups 2nd artifact should contain JSON content B');
    }

    public function test_map_withGroupingKeysAndSingleScalarProperty_singleGroupProduced(): void
    {
        // Given
        $jsonContent  = ['name' => 'Alice', 'age' => 30];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContent]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'name' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertEquals($jsonContent, reset($groups)[0]->json_content, 'The group should contain the given artifacts');
    }

    public function test_map_withGroupingKeysAndMultipleObjectsWithSameKeyScalarProperty_singleGroupProduced(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice', 'age' => 30];
        $jsonContentB = ['name' => 'Dan', 'age' => 30];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'age' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        $groups = array_values($groups);
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertEquals($jsonContentA, $groups[0][0]->json_content, 'The group should contain the contents of artifact A');
        $this->assertEquals($jsonContentB, $groups[0][1]->json_content, 'The group should contain the contents of artifact B');
    }

    public function test_map_withGroupingKeysAndMultipleObjectsWithDifferentKeyScalarProperty_groupProducedForEachUniqueKey(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice', 'age' => 30];
        $jsonContentB = ['name' => 'Dan', 'age' => 35];
        $jsonContentC = ['name' => 'Bill', 'age' => 35];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
            new Artifact(['json_content' => $jsonContentC]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'age' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        $groups = array_values($groups);
        $this->assertCount(2, $groups, '2 groups should be produced. One for age 35 and one for age 30');
        $this->assertCount(1, $groups[0], 'A single artifact should be in group A');
        $this->assertCount(2, $groups[1], 'A single artifact should be in group B');
        $this->assertEquals($jsonContentA, $groups[0][0]->json_content, 'The group should contain the contents of artifact A');
        $this->assertEquals($jsonContentB, $groups[1][0]->json_content, 'The group should contain the contents of artifact B');
        $this->assertEquals($jsonContentC, $groups[1][1]->json_content, 'The group should contain the contents of artifact C');
    }

    public function test_map_withGroupingKeysAndScalarPropertyOfNestedObject_singleGroupProduced(): void
    {
        // Given
        $jsonContent  = [
            'name'    => 'Alice',
            'age'     => 30,
            'address' => ['city' => 'Springfield', 'state' => 'IL'],
        ];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContent]),
        ];
        $groupingKeys = [
            [
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
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertCount(1, $groups[0], 'A single artifact in the group should be produced');
        $this->assertEquals($jsonContent, $groups[0][0]->json_content, 'The group should contain the given artifacts');
    }

    public function test_map_withGroupingKeysAndScalarPropertyOfNestedObjectAndMultipleArtifacts_groupProducedForEachUniqueArtifact(): void
    {
        // Given
        $jsonContentA = [
            'name'    => 'Alice',
            'address' => ['city' => 'Springfield', 'state' => 'IL'],
        ];
        $jsonContentB = [
            'name'    => 'Dan',
            'address' => ['city' => 'Denver', 'state' => 'CO'],
        ];
        $jsonContentC = [
            'name'    => 'Bill',
            'address' => ['city' => 'Springfield', 'state' => 'CO'],
        ];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
            new Artifact(['json_content' => $jsonContentC]),
        ];
        $groupingKeys = [
            [
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
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(2, $groups, 'A single group should be produced');
        $this->assertCount(2, $groups[0], '2 artifacts should be in group A');
        $this->assertCount(1, $groups[1], 'A single artifact should be in group B');
        $this->assertEquals($jsonContentA, $groups[0][0]->json_content, 'The 1st item in group A should contain the JSON content A artifact');
        $this->assertEquals($jsonContentC, $groups[0][1]->json_content, 'The 2nd item in group A should contain the JSON content C artifact');
        $this->assertEquals($jsonContentB, $groups[1][0]->json_content, 'The only item in group B should contain the JSON content B artifact');
    }

    public function test_map_withGroupingKeysOnAnArrayOfScalars_aGroupIsProducedForEachScalar(): void
    {
        // Given
        $jsonContent  = [
            'name'    => 'Dan',
            'aliases' => ['Newms', 'Danny', 'The Hammer'],
        ];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContent]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'aliases' => [
                        'type' => 'array',
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(3, $groups, 'There should be 3 unique groups');
        $this->assertCount(1, $groups[0], 'A single artifact should be in group A');
        $this->assertCount(1, $groups[1], 'A single artifact should be in group B');
        $this->assertCount(1, $groups[2], 'A single artifact should be in group C');
        $this->assertEquals(['name' => 'Dan', 'aliases' => 'Newms'], $groups[0][0]->json_content, 'The 1st item in group A should be the alias Newms');
        $this->assertEquals(['name' => 'Dan', 'aliases' => 'Danny'], $groups[1][0]->json_content, 'The 1st item in group A should be the alias Danny');
        $this->assertEquals(['name' => 'Dan', 'aliases' => 'The Hammer'], $groups[2][0]->json_content, 'The 1st item in group A should be the alias The Hammer');
    }

    public function test_map_withGroupingKeysAndScalarPropertyOfArrayOfObjects_groupProducedForEachUniqueArtifact(): void
    {
        // Given
        $jsonContent  = [
            'name'      => 'Alice',
            'addresses' => [
                ['city' => 'Springfield', 'state' => 'IL'],
                ['city' => 'Denver', 'state' => 'CO'],
                ['city' => 'Evergreen', 'state' => 'CO'],
            ],
        ];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContent]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'address' => [
                        'type'     => 'array',
                        'children' => [
                            'city' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)
            ->setGroupingKeys($groupingKeys)
            ->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(2, $groups, 'A single group should be produced');
        $this->assertCount(2, $groups[0], '2 artifacts should be in group A');
        $this->assertCount(1, $groups[1], 'A single artifact should be in group B');
        $this->assertEquals($jsonContent, $groups[0][0]->json_content, 'The 1st item in group A should contain the JSON content A artifact');
        $this->assertEquals($jsonContentC, $groups[0][1]->json_content, 'The 2nd item in group A should contain the JSON content C artifact');
        $this->assertEquals($jsonContentB, $groups[1][0]->json_content, 'The only item in group B should contain the JSON content B artifact');
    }
}
