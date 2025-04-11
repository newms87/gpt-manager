<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\Artifact;
use App\Services\Task\ArtifactsToGroupsMapper;
use Tests\AuthenticatedTestCase;

class ArtifactsToGroupsMapperTest extends AuthenticatedTestCase
{
    public function test_map_concatenateMode_producesSingleGroup(): void
    {
        // Given
        $jsonContent = ['name' => 'Alice'];
        $artifacts   = [
            new Artifact(['json_content' => $jsonContent]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useConcatenateMode()->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $groupArtifacts = $groups['default'] ?? [];
        $this->assertCount(1, $groupArtifacts, 'The group key should have been set in the groups list pointing to an array w/ 1 artifact');
        $this->assertEquals($jsonContent, $groups['default'][0]->json_content, 'The artifact in the group should match the passed in artifact');
    }

    public function test_map_concatenateModeWith2Artifacts_producesSingleGroupWith2Artifacts(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContentA]),
            new Artifact(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useConcatenateMode()->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'A single group should be produced');
        $defaultGroup = $groups['default'];
        usort($defaultGroup, fn(Artifact $a, Artifact $b) => $a->json_content['name'] <=> $b->json_content['name']);
        $this->assertEquals($jsonContentA, $defaultGroup[0]->json_content, 'The groups 1st artifact should contain JSON content A');
        $this->assertEquals($jsonContentB, $defaultGroup[1]->json_content, 'The groups 2nd artifact should contain JSON content B');
    }

    public function test_map_splitByFile_aGroupForEachFile(): void
    {
        // Given
        $jsonContent = ['name' => 'Alice'];
        $artifacts   = [
            Artifact::factory()->withStoredFiles(2)->create(['json_content' => $jsonContent]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->splitByFile()->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);

        $this->assertCount(2, $groups, 'A group for each file should be produced');
        $this->assertCount(2, $groups[0], 'The 1st group should have 2 artifacts: 1. JSON content, 2. the file');
        $this->assertCount(2, $groups[1], 'The 2nd group should have 2 artifacts: 1. JSON content, 2. the file ');
        $this->assertEquals(1, $groups[0][1]->storedFiles()->count(), 'The 1st group should contain 1 file');
        $this->assertEquals(1, $groups[1][1]->storedFiles()->count(), 'The 2nd group should contain 1 file');
    }

    public function test_map_splitModeWithFiles_allFilesAreAddedToEachGroup(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];

        $artifacts = [
            Artifact::factory()->withStoredFiles(1)->create(['json_content' => $jsonContentA]),
            Artifact::factory()->withStoredFiles(1)->create(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useSplitMode()->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);

        $this->assertCount(2, $groups, 'A group for each file should be produced');
        $this->assertCount(2, $groups[0], 'The 1st group should have 2 artifacts: 1. JSON content, 2. the files');
        $this->assertCount(2, $groups[1], 'The 2nd group should have 2 artifacts: 1. JSON content, 2. the files ');
        $this->assertEquals(2, $groups[0][1]->storedFiles()->count(), 'The 1st group should contain 2 files');
        $this->assertEquals(2, $groups[1][1]->storedFiles()->count(), 'The 2nd group should contain 2 files');
    }

    public function test_map_splitModeWith2Artifacts_producesOneGroupForEachArtifact(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];
        $artifacts    = [
            Artifact::factory()->create(['json_content' => $jsonContentA]),
            Artifact::factory()->create(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useSplitMode()->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(2, $groups, 'A group for each artifact should be produced');
        $this->assertEquals($jsonContentA, $groups[0][0]->json_content, 'The groups 1st artifact should contain JSON content A');
        $this->assertEquals($jsonContentB, $groups[1][0]->json_content, 'The groups 2nd artifact should contain JSON content B');
    }

    public function test_map_overwriteModeWith2Artifacts_producesOneGroupWithOneArtifact(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];
        $artifacts    = [
            Artifact::factory()->create(['json_content' => $jsonContentA]),
            Artifact::factory()->create(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useOverwriteMode()->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        $this->assertCount(1, $groups, 'Only one group should exist as the other group was overwritten');
        $this->assertCount(1, $groups[0], 'Only one artifact should be in the group. The other one was throw away.');
        $this->assertEquals($jsonContentB, $groups[0][0]->json_content, 'The group artifact should contain JSON content B');
    }

    public function test_map_mergeModeWith2Artifacts_producesOneGroupWithMergedDataFromBothArtifacts(): void
    {
        // Given
        $jsonContentA = ['name' => 'Alice'];
        $jsonContentB = ['name' => 'Dan'];
        $artifacts    = [
            Artifact::factory()->create(['json_content' => $jsonContentA]),
            Artifact::factory()->create(['json_content' => $jsonContentB]),
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useMergeMode()->map($artifacts);

        // Then
        $this->assertCount(1, $groups, 'Only one group should exist as the other group was overwritten');
        $this->assertCount(1, $groups['default'], 'Only one artifact should be in the group. The other one was throw away.');
        $this->assertEquals(['name' => ['Alice', 'Dan']], $groups['default']['merged']->json_content, 'The group artifact should contain JSON content B');
    }

    public function test_map_splitModeWithGroupingKeys_producesSeparateGroupsForEachArtifactsDerivedGroups(): void
    {
        // Given
        $jsonContentA = [
            'name'      => 'Alice',
            'addresses' => [
                ['city' => 'Springfield', 'state' => 'IL'],
                ['city' => 'Denver', 'state' => 'CO'],
            ],
        ];
        $jsonContentB = [
            'name'      => 'Dan',
            'addresses' => [
                ['city' => 'Chicago', 'state' => 'IL'],
                ['city' => 'Evergreen', 'state' => 'CO'],
            ],
        ];
        $artifacts    = [
            Artifact::factory()->create(['json_content' => $jsonContentA]),
            Artifact::factory()->create(['json_content' => $jsonContentB]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'addresses' => [
                        'type' => 'array',
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useSplitMode()->setGroupingKeys($groupingKeys)->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        usort($groups, fn($a, $b) => $a[0]->json_content['name'] . $a[0]->json_content['addresses']['city'] <=> $b[0]->json_content['name'] . $b[0]->json_content['addresses']['city']);
        $this->assertCount(4, $groups, 'A group for each artifact should be produced');
        $this->assertEquals(['name' => 'Alice', 'addresses' => ['city' => 'Denver', 'state' => 'CO']], $groups[0][0]->json_content, 'The groups 1st artifact should contain the Alice + Denver address');
        $this->assertEquals(['name' => 'Alice', 'addresses' => ['city' => 'Springfield', 'state' => 'IL']], $groups[1][0]->json_content, 'The groups 1st artifact should contain the Alice + Springfield address');
        $this->assertEquals(['name' => 'Dan', 'addresses' => ['city' => 'Chicago', 'state' => 'IL']], $groups[2][0]->json_content, 'The groups 2nd artifact should contain the Dan + Chicago address');
        $this->assertEquals(['name' => 'Dan', 'addresses' => ['city' => 'Evergreen', 'state' => 'CO']], $groups[3][0]->json_content, 'The groups 2nd artifact should contain the Dan + Evergreen address');
    }

    public function test_map_mergeModeWithGroupingKeys_producesSeparateGroupsForEachArtifactsDerivedGroups(): void
    {
        // Given
        $jsonContentA = [
            'name'      => 'Alice',
            'addresses' => [
                ['city' => 'Springfield', 'state' => 'IL'],
                ['city' => 'Denver', 'state' => 'CO'],
            ],
        ];
        $jsonContentB = [
            'name'      => 'Dan',
            'addresses' => [
                ['city' => 'Chicago', 'state' => 'IL'],
                ['city' => 'Evergreen', 'state' => 'CO'],
            ],
        ];
        $artifacts    = [
            Artifact::factory()->create(['json_content' => $jsonContentA]),
            Artifact::factory()->create(['json_content' => $jsonContentB]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'addresses' => [
                        'type'     => 'array',
                        'children' => [
                            'state' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // When
        $groups = (new ArtifactsToGroupsMapper)->useMergeMode()->setGroupingKeys($groupingKeys)->map($artifacts);

        // Then
        /** @var Artifact[][] $groups */
        $groups = array_values($groups);
        usort($groups, fn($a, $b) => ($a['merged']->json_content['addresses']['state'][0] ?? '') <=> ($b['merged']->json_content['addresses']['state'][0] ?? ''));
        $this->assertCount(2, $groups, 'Should produce a group for each state');
        $this->assertCount(1, $groups[0], 'Should produce 1 artifact for group 1');
        $this->assertCount(1, $groups[1], 'Should produce 1 artifact for group 2');
        $this->assertEquals(['name' => ['Alice', 'Dan'], 'addresses' => ['city' => ['Denver', 'Evergreen'], 'state' => 'CO']], $groups[0]['merged']->json_content, 'The groups 1st artifact should contain the Alice + Denver address');
        $this->assertEquals(['name' => ['Alice', 'Dan'], 'addresses' => ['city' => ['Springfield', 'Chicago'], 'state' => 'IL']], $groups[1]['merged']->json_content, 'The groups 1st artifact should contain the Alice + Springfield address');
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
                    'addresses' => [
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
        $this->assertCount(3, $groups, '3 groups should have been produced. One for each city');
        $this->assertCount(1, $groups[0], '1 artifact should be in the 1st group');
        $this->assertCount(1, $groups[1], '1 artifact should be in the 2nd group');
        $this->assertCount(1, $groups[2], '1 artifact should be in the 3rd group');

        // Sort the groups so we know which group has which address
        usort($groups, fn($a, $b) => $a[0]->json_content['addresses']['city'] <=> $b[0]->json_content['addresses']['city']);
        $this->assertEquals(['name' => 'Alice', 'addresses' => ['city' => 'Denver', 'state' => 'CO']], $groups[0][0]->json_content, 'The 1st group should be Denver');
        $this->assertEquals(['name' => 'Alice', 'addresses' => ['city' => 'Evergreen', 'state' => 'CO']], $groups[1][0]->json_content, 'The 2nd should be the Evergreen');
        $this->assertEquals(['name' => 'Alice', 'addresses' => ['city' => 'Springfield', 'state' => 'IL']], $groups[2][0]->json_content, 'The 3rd group should be Springfield');
    }

    public function test_map_withGroupingKeysAndScalarPropertyOfNestedArrayOfObjects_crossProductOfGroupsProducedForEachElementInArrays(): void
    {
        // Given
        $jsonContent  = [
            'name' => 'Dan',
            'jobs' => [
                [
                    'company'   => 'Google',
                    'addresses' => [
                        ['city' => 'Mountain View', 'state' => 'CA'],
                        ['city' => 'Boulder', 'state' => 'CO'],
                    ],
                ],
                [
                    'company'   => 'Microsoft',
                    'addresses' => [
                        ['city' => 'Redmond', 'state' => 'WA'],
                        ['city' => 'Boulder', 'state' => 'CO'],
                    ],
                ],
            ],
        ];
        $artifacts    = [
            new Artifact(['json_content' => $jsonContent]),
        ];
        $groupingKeys = [
            [
                'type'     => 'object',
                'children' => [
                    'jobs' => [
                        'type'     => 'array',
                        'children' => [
                            'addresses' => [
                                'type'     => 'array',
                                'children' => [
                                    'state' => [
                                        'type' => 'string',
                                    ],
                                ],
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

        // Sort the groups so we know which group has which address
        usort($groups, fn($a, $b) => ($a[0]->json_content['jobs'][0]['addresses']['city'] ?? $a[0]->json_content['jobs']['addresses']['city']) <=> ($b[0]->json_content['jobs'][0]['addresses']['city'] ?? $b[0]->json_content['jobs']['addresses']['city']));

        $this->assertCount(3, $groups, '3 groups should have been produced. One for each city');
        $this->assertCount(1, $groups[0], '1 artifact should be in the 1st group');
        $this->assertCount(1, $groups[1], '1 artifact should be in the 2nd group');
        $this->assertCount(1, $groups[2], '1 artifact should be in the 3rd group');

        $firstData = $groups[0][0]->json_content;
        $this->assertCount(2, $firstData['jobs'], 'The 1st group should have 2 jobs');
        $this->assertEquals('Dan', $firstData['name'], 'The 1st group should be Dan');
        $this->assertEquals('Microsoft', $firstData['jobs'][0]['company'], 'The 1st job should be Microsoft');
        $this->assertEquals('Boulder', $firstData['jobs'][0]['addresses']['city'], 'The 1st job address should be Boulder');
        $this->assertEquals('Google', $firstData['jobs'][1]['company'], 'The 2nd job should be Google');
        $this->assertEquals('Boulder', $firstData['jobs'][1]['addresses']['city'], 'The 2nd job address should also be Boulder');

        $secondData = $groups[1][0]->json_content;
        $this->assertEquals('Dan', $secondData['name'], 'The 2nd group should be Dan');
        $this->assertEquals('Google', $secondData['jobs']['company'], 'The 2nd job should be Google');
        $this->assertEquals('Mountain View', $secondData['jobs']['addresses']['city'], 'The 2nd job address should be Mountain View');

        $thirdData = $groups[2][0]->json_content;
        $this->assertEquals('Dan', $thirdData['name'], 'The 3rd group should be Dan');
        $this->assertEquals('Microsoft', $thirdData['jobs']['company'], 'The 3rd job should be Microsoft');
        $this->assertEquals('Redmond', $thirdData['jobs']['addresses']['city'], 'The 3rd job address should be Redmond');
    }
}
