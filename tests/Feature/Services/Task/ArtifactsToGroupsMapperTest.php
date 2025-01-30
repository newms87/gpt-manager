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
        $groupKey    = ArtifactsToGroupsMapper::getGroupKey($jsonContent);
        $artifacts   = [
            new Artifact(['json_content' => $jsonContent]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertNotNull($groups[$groupKey] ?? null, 'The group key should have been set in the groups list');
        $this->assertEquals($jsonContent, $groups[$groupKey], 'The group should contain the given artifacts');
    }

    public function test_map_defaultBehaviorWithMultipleArtifacts_singleGroupProduced(): void
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
        $defaultGroup = array_values($groups['default']);
        $this->assertEquals($jsonContentA, $defaultGroup[0]->json_content, 'The groups 1st artifact should contain JSON content A');
        $this->assertEquals($jsonContentA, $defaultGroup[1]->json_content, 'The groups 2nd artifact should contain JSON content B');
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
        $this->assertEquals($jsonContent, reset($groups), 'The group should contain the given artifacts');
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
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertEquals($jsonContent, reset($groups), 'The group should contain the given artifacts');
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
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
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
        $groups = array_values($groups);
        $this->assertCount(2, $groups, 'A single group should be produced');
        $this->assertEquals($jsonContentA, $groups[0], 'The 1st group should contain the JSON content A artifact');
        $this->assertEquals($jsonContentB, $groups[1], 'The 2nd group should contain the JSON content B artifact');
    }

    public function test_map_withGroupingKeysAndArrayOfScalars_singleGroupProduced(): void
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
        $this->assertCount(1, $groups, 'A single group should be produced');
        $this->assertEquals($jsonContent, reset($groups), 'The group should contain the given artifacts');
    }
}
