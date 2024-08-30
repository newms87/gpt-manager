<?php

namespace Tests\Feature\Workflow;

use App\Models\Prompt\PromptSchema;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\WorkflowTools\RunAgentThreadWorkflowTool;
use Newms87\Danx\Helpers\ArrayHelper;
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
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = [];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['dob'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['name', 'color'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['address'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['address.zip'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['aliases', 'name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['services'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
        $groupBy               = ['services.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
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
            'force_schema'   => true,
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
            'force_schema'   => true,
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
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'   => true,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertEmpty($groups, 'No groups should have been produced since the field does not exist');
    }

    public function test_getArtifactGroups_includeFieldNonExistingWithExistingStillReturnsGroup(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['name', 'non_existing'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'   => true,
            'include_fields' => $includeFields,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $expected = ['default' => [['name' => 'Dan Newman'], ['name' => 'Mickey Mouse']]];
        $this->assertEquals($expected, $groups, 'The Group should still have been returned with the existing field');
    }

    public function test_getArtifactGroups_allowsNonSchemaFields(): void
    {
        // Given
        $artifacts       = [
            [
                'name'             => 'Bill',
                'dob'              => '1987-11-18',
                'non_schema_field' => 'Hello World',
            ],
        ];
        $responseExample = [
            'name' => 'Dan',
            'dob'  => '1987-11-18',
        ];
        $workflowJob     = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $promptSchema    = PromptSchema::factory()->create(['response_example' => $responseExample,]);
        $workflowJob->responseSchema()->associate($promptSchema)->save();
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'               => false,
            'depends_on_workflow_job_id' => $workflowJob,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertEquals(['default' => $artifacts], $groups, 'The artifact should be unmodified');
    }

    public function test_getArtifactGroups_forcesOnlySchemaFields(): void
    {
        // Given
        $artifacts       = [
            [
                'name'             => 'Bill',
                'dob'              => '1987-11-18',
                'non_schema_field' => 'Hello World',
            ],
        ];
        $responseExample = [
            'name' => 'Dan',
            'dob'  => '1987-11-18',
        ];
        $workflowJob     = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $promptSchema    = PromptSchema::factory()->create(['response_example' => $responseExample]);
        $workflowJob->responseSchema()->associate($promptSchema)->save();
        $agent = $workflowJob->workflowAssignments()->first()->agent;
        $agent->forceFill(['response_format' => 'json_object'])->save();
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'               => true,
            'depends_on_workflow_job_id' => $workflowJob,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertEquals([
            'default' => [
                [
                    'name' => $artifacts[0]['name'],
                    'dob'  => $artifacts[0]['dob'],
                ],
            ],
        ], $groups, 'The artifact should be unmodified');
    }

    public function test_getArtifactGroups_includeFieldScalarOfObjectInSubArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.options.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'   => true,
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

    public function test_getArtifactGroups_includeFieldMultipleScalarsOfObjectInSubArray(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $includeFields         = ['services.*.options.*.name', 'services.*.options.*.cost'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'force_schema'   => true,
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
                        [
                            'name' => $artifacts[0]['services'][0]['options'][0]['name'],
                            'cost' => $artifacts[0]['services'][0]['options'][0]['cost'],
                        ],
                        [
                            'name' => $artifacts[0]['services'][0]['options'][1]['name'],
                            'cost' => $artifacts[0]['services'][0]['options'][1]['cost'],
                        ],
                    ],
                ],
                [
                    'options' => [
                        [
                            'name' => $artifacts[0]['services'][1]['options'][0]['name'],
                            'cost' => $artifacts[0]['services'][1]['options'][0]['cost'],
                        ],
                        [
                            'name' => $artifacts[0]['services'][1]['options'][1]['name'],
                            'cost' => $artifacts[0]['services'][1]['options'][1]['cost'],
                        ],
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
        $groupBy               = ['services', 'name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
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
        // Given
        $artifacts             = $this->getArtifacts();
        $groupBy               = ['services.*.options.*.name'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by' => $groupBy,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $this->assertCount(4, $groups, 'Should have produced exactly 4 groups');

        $phpGroup                     = array_shift($groups);
        $phpArtifact                  = $phpGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [
            [
                ...$expectedArtifact['services'][0],
                'options' => [
                    $expectedArtifact['services'][0]['options'][0],
                ],
            ],
        ];
        $this->assertCount(1, $phpGroup, "Should have produced exactly 1 artifact in the 'PHP' group");
        $this->assertEquals($expectedArtifact, $phpArtifact, "Should have produced an artifact with the Write Code => PHP path");

        $nodeGroup                    = array_shift($groups);
        $nodeArtifact                 = $nodeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [
            [
                ...$expectedArtifact['services'][0],
                'options' => [
                    $expectedArtifact['services'][0]['options'][1],
                ],
            ],
        ];
        $this->assertCount(1, $nodeGroup, "Should have produced exactly 1 artifact in the 'Node' group");
        $this->assertEquals($expectedArtifact, $nodeArtifact, "Should have produced an artifact with the Write Code => Node path");

        $chromeGroup                  = array_shift($groups);
        $chromeArtifact               = $chromeGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [
            [
                ...$expectedArtifact['services'][1],
                'options' => [
                    $expectedArtifact['services'][1]['options'][0],
                ],
            ],
        ];
        $this->assertCount(1, $chromeGroup, "Should have produced exactly 1 artifact in the 'Chrome' group");
        $this->assertEquals($expectedArtifact, $chromeArtifact, "Should have produced an artifact with the Test Code => Chrome path");

        $ieGroup                      = array_shift($groups);
        $ieArtifact                   = $ieGroup[0];
        $expectedArtifact             = $artifacts[0];
        $expectedArtifact['services'] = [
            [
                ...$expectedArtifact['services'][1],
                'options' => [
                    $expectedArtifact['services'][1]['options'][1],
                ],
            ],
        ];
        $this->assertCount(1, $ieGroup, "Should have produced exactly 1 artifact in the 'IE' group");
        $this->assertEquals($expectedArtifact, $ieArtifact, "Should have produced an artifact with the Test Code => IE path");
    }

    public function test_getArtifactGroups_ordersFieldsInAscendingOrder(): void
    {
        // Given
        $artifacts             = [
            ['name' => 'Mickey'],
            ['name' => 'Bill'],
            ['name' => 'Dan'],
        ];
        $groupBy               = ['name'];
        $orderBy               = ['name' => 'name', 'direction' => 'asc'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'order_by' => $orderBy,
            'group_by' => $groupBy,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $firstGroup  = array_shift($groups);
        $secondGroup = array_shift($groups);
        $thirdGroup  = array_shift($groups);
        $this->assertEquals([['name' => 'Bill']], $firstGroup, 'The first group should be Bill');
        $this->assertEquals([['name' => 'Dan']], $secondGroup, 'The second group should be Dan');
        $this->assertEquals([['name' => 'Mickey']], $thirdGroup, 'The third group should be Mickey');
    }

    public function test_getArtifactGroups_ordersPagesInAscendingOrderWithoutGroupBy(): void
    {
        // Given
        $artifacts             = [
            ['name' => 'Mickey', 'pages' => [3]],
            ['name' => 'Bill', 'pages' => [1]],
            ['name' => 'Dan', 'pages' => [4]],
            ['name' => 'Aaron', 'pages' => [2]],
        ];
        $orderBy               = ['name' => 'pages', 'direction' => 'asc'];
        $workflowJobRun        = WorkflowJobRun::factory()->withArtifactData($artifacts)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'order_by' => $orderBy,
        ]);

        // When
        $groups = app(RunAgentThreadWorkflowTool::class)->getArtifactGroups($workflowJobDependency, $workflowJobRun);

        // Then
        $defaultGroup = array_shift($groups);
        $firstGroup   = array_shift($defaultGroup);
        $secondGroup  = array_shift($defaultGroup);
        $thirdGroup   = array_shift($defaultGroup);
        $fourthGroup  = array_shift($defaultGroup);
        $this->assertEquals(['name' => 'Bill', 'pages' => [1]], $firstGroup, 'The first group should be Bill');
        $this->assertEquals(['name' => 'Aaron', 'pages' => [2]], $secondGroup, 'The second group should be Aaron');
        $this->assertEquals(['name' => 'Mickey', 'pages' => [3]], $thirdGroup, 'The third group should be Mickey');
        $this->assertEquals(['name' => 'Dan', 'pages' => [4]], $fourthGroup, 'The fourth group should be Dan');
    }

    public function test_generateArtifactGroupTuples_producesEmptyArrayWhenNoDependencies(): void
    {
        // Given
        $dependencyArtifactGroups = [];

        // When
        $groupTuples = app(RunAgentThreadWorkflowTool::class)->generateArtifactGroupTuples($dependencyArtifactGroups);

        // Then
        $this->assertEquals(['default' => [['content' => 'Follow the prompt']]], $groupTuples);
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
            'group-a | group-c' => $mergedArtifacts,
            'group-a | group-d' => $mergedArtifacts,
            'group-b | group-c' => $mergedArtifacts,
            'group-b | group-d' => $mergedArtifacts,
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
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
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
        $groupBy               = ['name'];
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'                   => $groupBy,
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
        $inputContent = 'Input Directive Test';
        $artifact     = Artifact::factory()->withStoredFile()->create([
            'content' => $inputContent,
        ]);

        $workflowRun       = WorkflowRun::factory()->create();
        $inputJob          = WorkflowJob::factory()->isWorkflowInputTool()->create();
        $inputJobRun       = WorkflowJobRun::factory()->withArtifact($artifact)->completed()->create(['workflow_job_id' => $inputJob->id]);
        $assignmentsJob    = WorkflowJob::factory()->hasWorkflowAssignments()->dependsOn([$inputJob])->create();
        $assignmentsJobRun = WorkflowJobRun::factory()->recycle($assignmentsJob)->recycle($workflowRun)->create();

        $prerequisiteJobRuns = [$inputJob->id => $inputJobRun];

        // When
        app(RunAgentThreadWorkflowTool::class)->resolveAndAssignTasks($assignmentsJobRun, $prerequisiteJobRuns);

        // Then
        $task   = $assignmentsJobRun->tasks()->first();
        $thread = $task->thread()->first();

        $this->assertEquals(1, $thread->messages()->count());

        $message = $thread->messages()->first();
        $this->assertEquals($inputContent . "\nFilename: test.jpg", $message->content);
        $this->assertEquals($artifact->storedFiles()->first()->id, $message->storedFiles()->first()->id);
    }

    public function test_resolveAndAssignTasks_groupedTasksHaveThreadWithArtifact(): void
    {
        // Given
        $artifacts             = $this->getArtifacts();
        $groupBy               = ['name'];
        $prerequisiteJob       = WorkflowJob::factory()->create();
        $prerequisiteJobRun    = WorkflowJobRun::factory()->withArtifactData($artifacts)->completed()->create(['workflow_job_id' => $prerequisiteJob]);
        $workflowJob           = WorkflowJob::factory()->hasWorkflowAssignments()->create();
        $workflowJobRun        = WorkflowJobRun::factory()->recycle($workflowJob)->create();
        $workflowJobDependency = WorkflowJobDependency::factory()->create([
            'group_by'                   => $groupBy,
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
