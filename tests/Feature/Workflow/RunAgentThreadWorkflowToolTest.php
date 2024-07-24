<?php

namespace Tests\Feature\Workflow;

use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\WorkflowTools\RunAgentThreadWorkflowTool;
use Illuminate\Support\Str;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class RunAgentThreadWorkflowToolTest extends AuthenticatedTestCase
{
    use AiMockData;

    private function getArtifacts(): array
    {
        return [
            [
                'name'     => 'Dan Newman',
                'aliases'  => ['The Hammer', 'Daniel'],
                'dob'      => '1987-11-18',
                'color'    => 'green',
                'address'  => [
                    'city'  => 'Cordoba',
                    'state' => 'Cordoba',
                    'zip'   => '5000',
                ],
                'services' => [
                    [
                        'name'    => 'Write Code',
                        'cost'    => 500,
                        'options' => [
                            [
                                'name' => 'PHP',
                                'cost' => 100,
                            ],
                            [
                                'name' => 'Node',
                                'cost' => 0,
                            ],
                        ],
                    ],
                    [
                        'name'    => 'Test Code',
                        'cost'    => 300,
                        'options' => [
                            [
                                'name' => 'Chrome',
                                'cost' => 100,
                            ],
                            [
                                'name' => 'IE',
                                'cost' => 99950,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name'     => 'Mickey Mouse',
                'aliases'  => ['The Mouse', 'Mickey'],
                'dob'      => '1987-11-18',
                'color'    => 'red',
                'address'  => [
                    'city'  => 'Orlando',
                    'state' => 'FL',
                    'zip'   => '32830',
                ],
                'services' => [
                    [
                        'name' => 'Entertain',
                        'cost' => 800,
                    ],
                    [
                        'name' => 'Dance',
                        'cost' => 300,
                    ],
                ],
            ],
        ];
    }

    public function test_crossProductExtractData_producesEmptyArrayWhenNoFields(): void
    {
        // Given
        $data   = [];
        $fields = [];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([], $crossProduct);
    }

    public function test_crossProductExtractData_producesSingleEntryForScalar(): void
    {
        // Given
        $data   = [
            'name' => 'Dan Newman',
        ];
        $fields = ['name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([['name' => 'Dan Newman']], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArray(): void
    {
        // Given
        $data   = [
            'name'    => 'Dan Newman',
            'aliases' => ['The Hammer', 'Daniel'],
        ];
        $fields = ['aliases'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([['aliases' => 'The Hammer'], ['aliases' => 'Daniel']], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayCrossScalar(): void
    {
        // Given
        $data   = [
            'name'    => 'Dan Newman',
            'aliases' => ['The Hammer', 'Daniel'],
        ];
        $fields = ['aliases', 'name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'name' => 'Dan Newman'],
            ['aliases' => 'Daniel', 'name' => 'Dan Newman'],
        ], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayCrossArray(): void
    {
        // Given
        $data   = [
            'name'    => 'Dan Newman',
            'aliases' => ['The Hammer', 'Daniel'],
            'powers'  => ['Hammer', 'Code'],
        ];
        $fields = ['aliases', 'powers'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'powers' => 'Hammer'],
            ['aliases' => 'Daniel', 'powers' => 'Hammer'],
            ['aliases' => 'The Hammer', 'powers' => 'Code'],
            ['aliases' => 'Daniel', 'powers' => 'Code'],
        ], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayOfObjectsCrossArray(): void
    {
        // Given
        $data   = [
            'name'    => 'Dan Newman',
            'aliases' => ['The Hammer', 'Daniel'],
            'powers'  => [['name' => 'Hammer', 'power' => 50], ['name' => 'Code', 'power' => 80]],
        ];
        $fields = ['aliases', 'powers.*.name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'powers.*.name' => 'Hammer'],
            ['aliases' => 'Daniel', 'powers.*.name' => 'Hammer'],
            ['aliases' => 'The Hammer', 'powers.*.name' => 'Code'],
            ['aliases' => 'Daniel', 'powers.*.name' => 'Code'],
        ], $crossProduct);
    }

    public function test_getArtifactGroups_returnsDefaultArtifactGroupGivenEmptyGroupAndIncludes(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $defaultGroup = $groups['default'] ?? [];
        $this->assertCount(2, $defaultGroup, "Should have produced exactly 2 artifacts in the default group");
        $this->assertEquals($artifacts[0], array_shift($defaultGroup), "Should have produced the original artifact in the default group");
    }

    public function test_getArtifactGroups_producesOnlyDefaultGroupGiven2ArtifactsAndEmptyGroupAndIncludes(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $defaultGroup = $groups['default'] ?? [];
        $this->assertCount(2, $defaultGroup, "Should have produced exactly 2 artifacts in the default group");
        $this->assertEquals($artifacts[0], array_shift($defaultGroup), "Should have produced the 1st artifact in the default group");
        $this->assertEquals($artifacts[1], array_shift($defaultGroup), "Should have produced the 2nd artifact in the default group");
    }

    public function test_getArtifactGroups_defaultGroupEmptyWhenGroupBySet(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $defaultGroup = $groups['default'] ?? [];
        $this->assertCount(0, $defaultGroup, "Should not have produced any artifacts in the default group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByScalar(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $danGroup = array_shift($groups);
        $this->assertCount(1, $danGroup, "Should have produced exactly 1 artifact in the 'Dan Newman' group");
        $this->assertEquals($artifacts[0], $danGroup[0], "Should have produced the 1st artifact in the 'Dan Newman' group");

        $MickeyGroup = array_shift($groups);
        $this->assertCount(1, $MickeyGroup, "Should have produced exactly 1 artifact in the 'Mickey Mouse' group");
        $this->assertEquals($artifacts[1], $MickeyGroup[0], "Should have produced the 2nd artifact in the 'Mickey Mouse' group");
    }

    public function test_getArtifactGroups_producesSingleArtifactGroupForGroupByNonUniqueScalar(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['dob'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $dobGroup = $groups['dob:1987-11-18'] ?? [];
        $this->assertCount(2, $dobGroup, "Should have produced exactly 2 artifacts in the '1987-11-18' group");
        $this->assertEquals($artifacts[0], $dobGroup[0], "Should have produced the 1st artifact in the '1987-11-18' group");
        $this->assertEquals($artifacts[1], $dobGroup[1], "Should have produced the 2nd artifact in the '1987-11-18' group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForMultipleGroupByScalar(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['name', 'color'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(2, $groups, "Should have produced exactly 2 groups");

        $danGroup    = $groups['color:green,name:Dan Newman'] ?? [];
        $MickeyGroup = $groups['color:red,name:Mickey Mouse'] ?? [];
        $this->assertCount(1, $danGroup, "Should have produced exactly 1 artifact in the 'Dan Newman,green' group");
        $this->assertEquals($artifacts[0], array_pop($danGroup), "Should have produced the 1st artifact in the 'Dan Newman' group");
        $this->assertCount(1, $MickeyGroup, "Should have produced exactly 1 artifact in the 'Mickey Mouse' group");
        $this->assertEquals($artifacts[1], array_pop($MickeyGroup), "Should have produced the 2nd artifact in the 'Mickey Mouse' group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupForGroupByArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['address'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $cordobaGroup = array_shift($groups);
        $this->assertCount(1, $cordobaGroup, "Should have produced exactly 1 artifact in the Cordoba group");
        $this->assertEquals($artifacts[0], $cordobaGroup[0], "Should have produced the 1st artifact in the Cordoba group");

        $orlandoGroup = array_shift($groups);
        $this->assertCount(1, $orlandoGroup, "Should have produced exactly 1 artifact in the Orlando group");
        $this->assertEquals($artifacts[1], $orlandoGroup[0], "Should have produced the 2nd artifact in the Orlando group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByKeyInArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['address.zip'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $cordobaGroup = array_shift($groups);
        $orlandoGroup = array_shift($groups);
        $this->assertCount(1, $cordobaGroup, "Should have produced exactly 1 artifact in the Cordoba group");
        $this->assertEquals($artifacts[0], array_pop($cordobaGroup), "Should have produced the 1st artifact in the Cordoba group");
        $this->assertCount(1, $orlandoGroup, "Should have produced exactly 1 artifact in the Orlando group");
        $this->assertEquals($artifacts[1], array_pop($orlandoGroup), "Should have produced the 2nd artifact in the Orlando group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByArrayOfScalars(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['aliases', 'name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $hammerGroup = array_shift($groups);
        $this->assertCount(1, $hammerGroup, "Should have produced exactly 1 artifact");
        $this->assertEquals('The Hammer', $hammerGroup[0]['aliases'][0] ?? null, "The alias for hammer group should have been set to the first alias of the first artifact");
        $this->assertEquals('Dan Newman', $hammerGroup[0]['name'] ?? null, "The alias for hammer group should have been set to the name of the first artifact");

        $danielGroup = array_shift($groups);
        $this->assertCount(1, $danielGroup, "Should have produced exactly 1 artifact");
        $this->assertEquals('Daniel', $danielGroup[0]['aliases'][0] ?? null, "The alias for daniel group should have been set to the second alias of the first artifact");
        $this->assertEquals('Dan Newman', $danielGroup[0]['name'] ?? null, "The alias for daniel group should have been set to the name of the first artifact");

        $mouseGroup = array_shift($groups);
        $this->assertCount(1, $mouseGroup, "Should have produced exactly 1 artifact");
        $this->assertEquals('The Mouse', $mouseGroup[0]['aliases'][0] ?? null, "The alias for mouse group should have been set to the first alias of the second artifact");
        $this->assertEquals('Mickey Mouse', $mouseGroup[0]['name'] ?? null, "The alias for mouse group should have been set to the name of the second artifact");

        $mickeyGroup = array_shift($groups);
        $this->assertCount(1, $mickeyGroup, "Should have produced exactly 1 artifact");
        $this->assertEquals('Mickey', $mickeyGroup[0]['aliases'][0] ?? null, "The alias for mickey group should have been set to the second alias of the second artifact");
        $this->assertEquals('Mickey Mouse', $mickeyGroup[0]['name'] ?? null, "The alias for mickey group should have been set to the name of the second artifact");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByArrayOfObjects(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['services'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $writeCodeGroup               = array_shift($groups);
        $writeCodeArtifact            = $writeCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group");

        $testCodeGroup                = array_shift($groups);
        $testCodeArtifact             = $testCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group");

        $entertainGroup               = array_shift($groups);
        $entertainArtifact            = $entertainGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group");

        $danceGroup                   = array_shift($groups);
        $danceArtifact                = $danceGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByIndexInArrayOfObjects(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['services.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $writeCodeGroup               = array_shift($groups);
        $writeCodeArtifact            = $writeCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group");

        $testCodeGroup                = array_shift($groups);
        $testCodeArtifact             = $testCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group");

        $entertainGroup               = array_shift($groups);
        $entertainArtifact            = $entertainGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group");

        $danceGroup                   = array_shift($groups);
        $danceArtifact                = $danceGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group");
    }

    public function test_getArtifactGroups_groupByObjectInArrayAndIncludeFieldInGroupBy(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.name'];
        $groupBy               = ['services.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $writeCodeGroup    = array_shift($groups);
        $writeCodeArtifact = $writeCodeGroup[0];
        $expectedArtifact  = ['services' => [['name' => 'Write Code']]];
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group");

        $testCodeGroup    = array_shift($groups);
        $testCodeArtifact = $testCodeGroup[0];
        $expectedArtifact = ['services' => [['name' => 'Test Code']]];
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group");

        $entertainGroup    = array_shift($groups);
        $entertainArtifact = $entertainGroup[0];
        $expectedArtifact  = ['services' => [['name' => 'Entertain']]];
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group");

        $danceGroup       = array_shift($groups);
        $danceArtifact    = $danceGroup[0];
        $expectedArtifact = ['services' => [['name' => 'Dance']]];
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group");
    }

    public function test_getArtifactGroups_groupByObjectInArrayAndIncludeFieldNotInGroupBy(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.cost'];
        $groupBy               = ['services.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $writeCodeGroup    = array_shift($groups);
        $writeCodeArtifact = $writeCodeGroup[0];
        $expectedArtifact  = ['services' => [['cost' => 500]]];
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group with cost only");

        $testCodeGroup    = array_shift($groups);
        $testCodeArtifact = $testCodeGroup[0];
        $expectedArtifact = ['services' => [['cost' => 300]]];
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group with cost only");

        $entertainGroup    = array_shift($groups);
        $entertainArtifact = $entertainGroup[0];
        $expectedArtifact  = ['services' => [['cost' => 800]]];
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group with cost only");

        $danceGroup       = array_shift($groups);
        $danceArtifact    = $danceGroup[0];
        $expectedArtifact = ['services' => [['cost' => 300]]];
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group with cost only");
    }

    public function test_getArtifactGroups_includeFieldNonExistingScalarOfObjectInSubArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.uses.*.name'];
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertEmpty($groups, 'No groups should have been produced since the field does not exist');
    }

    public function test_getArtifactGroups_noGroupByAndIncludeFieldScalarOfObjectInSubArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.options.*.name'];
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(1, $groups, 'Should have produced exactly 1 group');

        $defaultGroup = array_shift($groups);
        $this->assertCount(1, $defaultGroup, "Should have produced exactly 1 artifact in the default group. Dan Group has services w/ options, Mickey group does not.");
        $danArtifact      = $defaultGroup[0];
        $expectedArtifact = [
            'services' => [
                [
                    'options' => [
                        ['name' => $artifacts[0]['services'][0]['options'][0]['name']],
                        ['name' => $artifacts[0]['services'][0]['options'][1]['name']],
                    ],
                ],
                [
                    'options' => [
                        ['name' => $artifacts[0]['services'][1]['options'][0]['name']],
                        ['name' => $artifacts[0]['services'][1]['options'][1]['name']],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedArtifact, $danArtifact, "Should have produced the artifact containing only all the services' options for the Dan object");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByArrayOfObjectsAndScalar(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['services', 'name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $singularKey = Str::singular('services');

        $writeCodeGroup               = array_shift($groups);
        $writeCodeArtifact            = $writeCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group");

        $testCodeGroup                = array_shift($groups);
        $testCodeArtifact             = $testCodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group");

        $entertainGroup               = array_shift($groups);
        $entertainArtifact            = $entertainGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][0]];
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group");

        $danceGroup                   = array_shift($groups);
        $danceArtifact                = $danceGroup[0];
        $expectedArtifact             = $artifacts[1];
        $expectedArtifact['services'] = [$expectedArtifact['services'][1]];
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupsForGroupByIndexInNestedArrayOfObjects(): void
    {
        $this->markTestSkipped("This adds another layer of complexity, which will require a custom implementation of data_get() or a new approach");

        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['services.*.options.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'       => $groupBy,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');
        $singularKey = Str::singular('services');

        $writeCodeGroup                 = array_shift($groups);
        $writeCodeArtifact              = $writeCodeGroup[0];
        $expectedArtifact               = $artifacts[0];
        $expectedArtifact[$singularKey] = $expectedArtifact['services'][0];
        unset($expectedArtifact['services']);
        $this->assertCount(1, $writeCodeGroup, "Should have produced exactly 1 artifact in the 'Write Code' group");
        $this->assertEquals($expectedArtifact, $writeCodeArtifact, "Should have produced the 1st artifact in the 'Write Code' group");

        $testCodeGroup                  = array_shift($groups);
        $testCodeArtifact               = $testCodeGroup[0];
        $expectedArtifact               = $artifacts[0];
        $expectedArtifact[$singularKey] = $expectedArtifact['services'][1];
        unset($expectedArtifact['services']);
        $this->assertCount(1, $testCodeGroup, "Should have produced exactly 1 artifact in the 'Test Code' group");
        $this->assertEquals($expectedArtifact, $testCodeArtifact, "Should have produced the 1st artifact in the 'Test Code' group");

        $entertainGroup                 = array_shift($groups);
        $entertainArtifact              = $entertainGroup[0];
        $expectedArtifact               = $artifacts[1];
        $expectedArtifact[$singularKey] = $expectedArtifact['services'][0];
        unset($expectedArtifact['services']);
        $this->assertCount(1, $entertainGroup, "Should have produced exactly 1 artifact in the 'Entertain' group");
        $this->assertEquals($expectedArtifact, $entertainArtifact, "Should have produced the 1st artifact in the 'Entertain' group");

        $danceGroup                     = array_shift($groups);
        $danceArtifact                  = $danceGroup[0];
        $expectedArtifact               = $artifacts[1];
        $expectedArtifact[$singularKey] = $expectedArtifact['services'][1];
        unset($expectedArtifact['services']);
        $this->assertCount(1, $danceGroup, "Should have produced exactly 1 artifact in the 'Dance' group");
        $this->assertEquals($expectedArtifact, $danceArtifact, "Should have produced the 1st artifact in the 'Dance' group");
    }

    public function test_generateArtifactGroupTuples_producesEmptyArrayWhenNoDependencies(): void
    {
        // Given
        $dependencyArtifactGroups = [];

        // When
        $groupTuples = app(RunAgentThreadWorkflowTool::class)->generateArtifactGroupTuples($dependencyArtifactGroups);

        // Then
        $this->assertEquals(['default' => []], $groupTuples);
    }

    public function test_generateArtifactGroupTuples_returnOriginalWhenOnlyOneGroup(): void
    {
        // Given
        $artifactGroups = [
            'default' => $this->getArtifacts(),
        ];

        // When
        $groupTuples = app(RunAgentThreadWorkflowTool::class)->generateArtifactGroupTuples([1 => $artifactGroups]);

        // Then
        $this->assertEquals($artifactGroups, $groupTuples);
    }

    public function test_generateArtifactGroupTuples_crossProductOfTwoGroupsOfTwo(): void
    {
        // Given
        $artifacts                = $this->getArtifacts();
        $artifactGroups1          = [
            'group-a' => $artifacts,
            'group-b' => $artifacts,
        ];
        $artifactGroups2          = [
            'group-c' => $artifacts,
            'group-d' => $artifacts,
        ];
        $dependencyArtifactGroups = [
            1 => $artifactGroups1,
            2 => $artifactGroups2,
        ];

        // When
        $groupTuples = app(RunAgentThreadWorkflowTool::class)->generateArtifactGroupTuples($dependencyArtifactGroups);

        // Then
        $mergedArtifacts = array_merge($artifacts, $artifacts);
        $this->assertEquals([
            'group-a|group-c' => $mergedArtifacts,
            'group-a|group-d' => $mergedArtifacts,
            'group-b|group-c' => $mergedArtifacts,
            'group-b|group-d' => $mergedArtifacts,
        ], $groupTuples);
    }

    public function test_resolveAndAssignTasks_producesSingleDefaultTaskWhenNoDependencies(): void
    {
        // Given
        $artifacts      = $this->getArtifacts();
        $workflowJob    = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun = WorkflowJobRun::factory()->recycle($workflowJob)->withArtifactData($artifacts)->create();

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun);

        // Then
        $tasks = $workflowJobRun->tasks()->get();
        $this->assertEquals(1, $tasks->count());
        $this->assertEquals('default', $tasks->first()->group);
    }

    public function test_resolveAndAssignTasks_producesSingleTaskWithNoGroupBy(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = [];
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'                   => $groupBy,
            'include_fields'             => $includeFields,
            'depends_on_workflow_job_id' => $prerequisiteJob,
        ]);
        $workflowJob->dependencies()->save($workflowJobDependency);
        $prerequisiteJobRuns = [$prerequisiteJob->id => $prerequisiteJobRun];

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun, $prerequisiteJobRuns);

        // Then
        $tasks = $workflowJobRun->tasks()->get();
        $this->assertEquals(1, $tasks->count());
        $this->assertEquals('default', $tasks->first()->group);
    }

    public function test_resolveAndAssignTasks_producesMultipleTasksWithScalarGroupBy(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['name'];
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'                   => $groupBy,
            'include_fields'             => $includeFields,
            'depends_on_workflow_job_id' => $prerequisiteJob,
        ]);
        $workflowJob->dependencies()->save($workflowJobDependency);
        $prerequisiteJobRuns = [$prerequisiteJob->id => $prerequisiteJobRun];

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun, $prerequisiteJobRuns);

        // Then
        $tasks = $workflowJobRun->tasks()->get();
        $this->assertEquals(2, $tasks->count());
        $this->assertEquals('name:' . $artifacts[0]['name'], $tasks->get(0)->group);
        $this->assertEquals('name:' . $artifacts[1]['name'], $tasks->get(1)->group);
    }

    public function test_resolveAndAssignTasks_taskThreadHasWorkflowInput(): void
    {
        // Given
        $inputContent  = 'Input Directive Test';
        $storedFile    = StoredFile::create([
            'disk'     => 'local',
            'filepath' => 'test.jpg',
            'filename' => 'test.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $workflowInput = WorkflowInput::factory()->create(['content' => $inputContent]);
        $workflowInput->storedFiles()->save($storedFile);
        $workflowRun    = WorkflowRun::factory()->recycle($workflowInput)->create();
        $workflowJob    = WorkflowJob::factory()->hasWorkflowAssignments()->create([
            'use_input' => true,
        ]);
        $workflowJobRun = WorkflowJobRun::factory()->recycle($workflowJob)->recycle($workflowRun)->create();

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun);

        // Then
        $task   = $workflowJobRun->tasks()->first();
        $thread = $task->thread()->first();
        $this->assertEquals(1, $thread->messages()->count());

        $message = $thread->messages()->first();
        $this->assertEquals($inputContent, $message->content);
        $this->assertEquals($storedFile->id, $message->storedFiles()->first()->id);
    }

    public function test_resolveAndAssignTasks_groupedTasksHaveThreadWithArtifact(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = [];
        $groupBy               = ['name'];
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'                   => $groupBy,
            'include_fields'             => $includeFields,
            'depends_on_workflow_job_id' => $prerequisiteJob,
        ]);
        $workflowJob->dependencies()->save($workflowJobDependency);
        $prerequisiteJobRuns = [$prerequisiteJob->id => $prerequisiteJobRun];

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun, $prerequisiteJobRuns);

        // Then
        $task1   = $workflowJobRun->tasks()->first();
        $thread1 = $task1->thread()->first();
        $this->assertEquals(1, $thread1->messages()->count());

        $message = $thread1->messages()->first();
        $this->assertEquals($artifacts[0], json_decode($message->content, true));
    }
}
