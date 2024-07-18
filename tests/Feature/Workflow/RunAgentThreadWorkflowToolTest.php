<?php

namespace Tests\Feature\Workflow;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use App\WorkflowTools\RunAgentThreadWorkflowTool;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class RunAgentThreadWorkflowToolTest extends AuthenticatedTestCase
{
    use AiMockData;

    private function getArtifacts(): array
    {
        return [
            [
                'name'    => 'Dan Newman',
                'dob'     => '1987-11-18',
                'address' => [
                    'city'  => 'Cordoba',
                    'state' => 'Cordoba',
                    'zip'   => '5000',
                ],
            ],
            [
                'name'    => 'Micky Mouse',
                'dob'     => '1987-11-18',
                'address' => [
                    'city'  => 'Orlando',
                    'state' => 'FL',
                    'zip'   => '32830',
                ],
            ],
        ];
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

    public function test_getArtifactGroups_produces2ArtifactGroupsForGroupByScalar(): void
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
        $danGroup   = $groups['Dan Newman'] ?? [];
        $mickyGroup = $groups['Micky Mouse'] ?? [];
        $this->assertCount(1, $danGroup, "Should have produced exactly 1 artifact in the 'Dan Newman' group");
        $this->assertEquals($artifacts[0], array_pop($danGroup), "Should have produced the 1st artifact in the 'Dan Newman' group");
        $this->assertCount(1, $mickyGroup, "Should have produced exactly 1 artifact in the 'Micky Mouse' group");
        $this->assertEquals($artifacts[1], array_pop($mickyGroup), "Should have produced the 2nd artifact in the 'Micky Mouse' group");
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
        $dobGroup = $groups['1987-11-18'] ?? [];
        $this->assertCount(2, $dobGroup, "Should have produced exactly 2 artifacts in the '1987-11-18' group");
        $this->assertEquals($artifacts[0], array_shift($dobGroup), "Should have produced the 1st artifact in the '1987-11-18' group");
        $this->assertEquals($artifacts[1], array_shift($dobGroup), "Should have produced the 2nd artifact in the '1987-11-18' group");
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
        $orlandoGroup = array_shift($groups);
        $this->assertCount(1, $cordobaGroup, "Should have produced exactly 1 artifact in the Cordoba group");
        $this->assertEquals($artifacts[0], array_pop($cordobaGroup), "Should have produced the 1st artifact in the Cordoba group");
        $this->assertCount(1, $orlandoGroup, "Should have produced exactly 1 artifact in the Orlando group");
        $this->assertEquals($artifacts[1], array_pop($orlandoGroup), "Should have produced the 2nd artifact in the Orlando group");
    }

    public function test_getArtifactGroups_producesMultipleArtifactGroupForGroupByKeyInArray(): void
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

    public function test_generateArtifactGroupTuples_producesEmptyArrayWhenNoDependencies(): void
    {
        // Given
        $dependencyArtifactGroups = [];

        // When
        $groupTuples = app(RunAgentThreadWorkflowTool::class)->generateArtifactGroupTuples($dependencyArtifactGroups);

        // Then
        $this->assertEquals(['default' => ''], $groupTuples);
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
        $this->assertEquals($artifacts[0]['name'], $tasks->get(0)->group);
        $this->assertEquals($artifacts[1]['name'], $tasks->get(1)->group);
    }
}
