<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\Runners\WorkflowOutputTaskRunner;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowOutputTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TaskDefinition            $taskDefinition;
    protected TaskRun                   $taskRun;
    protected TaskProcess               $taskProcess;
    protected WorkflowRun               $workflowRun;
    protected WorkflowDefinition        $workflowDefinition;
    protected WorkflowNode              $workflowNode;
    protected WorkflowOutputTaskRunner  $taskRunner;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Create workflow definition and run
        $this->workflowDefinition = WorkflowDefinition::factory()->create();
        $this->workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $this->workflowDefinition->id,
        ]);

        // Create workflow node
        $this->workflowNode = WorkflowNode::factory()->create([
            'workflow_definition_id' => $this->workflowDefinition->id,
        ]);

        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'name'             => 'Test Workflow Output Task',
            'task_runner_name' => WorkflowOutputTaskRunner::RUNNER_NAME,
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'workflow_run_id'    => $this->workflowRun->id,
            'workflow_node_id'   => $this->workflowNode->id,
            'name'               => 'Test Workflow Output Run',
        ]);

        // Create task process
        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'activity'    => 'Testing workflow output collection',
        ]);

        // Get the actual task runner instance
        $this->taskRunner = $this->taskProcess->getRunner();
    }

    public function test_run_withNoInputArtifacts_completesWithEmptyResult(): void
    {
        // Given no input artifacts are attached to the task process
        // (task process already created with no input artifacts)

        // When we run the workflow output task
        $this->taskRunner->run();

        // Then the task should complete with no output artifacts
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts()->get();
        $this->assertCount(0, $outputArtifacts);
        
        // Check that the process finished
        $this->assertNotNull($this->taskProcess->completed_at);
        
        // Check that no workflow output artifacts were added
        $workflowOutputArtifacts = $this->workflowRun->outputArtifacts()->get();
        $this->assertCount(0, $workflowOutputArtifacts);
    }

    public function test_run_withInputArtifacts_attachesArtifactsAsWorkflowOutputs(): void
    {
        // Given we have input artifacts attached to the task process
        $artifact1 = Artifact::factory()->create([
            'name' => 'Final Document',
        ]);
        
        $artifact2 = Artifact::factory()->create([
            'name' => 'Summary Report',
        ]);
        
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id]);

        // When we run the workflow output task
        $this->taskRunner->run();

        // Then the task process should have the artifacts as output artifacts
        $this->taskProcess->refresh();
        $processOutputArtifacts = $this->taskProcess->outputArtifacts()->get();
        $this->assertCount(2, $processOutputArtifacts);
        $this->assertTrue($processOutputArtifacts->contains($artifact1));
        $this->assertTrue($processOutputArtifacts->contains($artifact2));
        
        // And the task run should have the artifacts as output artifacts
        $this->taskRun->refresh();
        $taskRunOutputArtifacts = $this->taskRun->outputArtifacts()->get();
        $this->assertCount(2, $taskRunOutputArtifacts);
        $this->assertTrue($taskRunOutputArtifacts->contains($artifact1));
        $this->assertTrue($taskRunOutputArtifacts->contains($artifact2));
        
        // And the workflow run should have the artifacts as output artifacts
        $this->workflowRun->refresh();
        $workflowOutputArtifacts = $this->workflowRun->outputArtifacts()->get();
        $this->assertCount(2, $workflowOutputArtifacts);
        $this->assertTrue($workflowOutputArtifacts->contains($artifact1));
        $this->assertTrue($workflowOutputArtifacts->contains($artifact2));
        
        // Check that the process finished
        $this->assertNotNull($this->taskProcess->completed_at);
    }

    public function test_run_withMultipleInputArtifacts_maintainsArtifactOrder(): void
    {
        // Given we have multiple input artifacts with specific positions
        $artifact1 = Artifact::factory()->create([
            'name'     => 'Chapter 1',
            'position' => 1,
        ]);
        
        $artifact2 = Artifact::factory()->create([
            'name'     => 'Chapter 2', 
            'position' => 2,
        ]);
        
        $artifact3 = Artifact::factory()->create([
            'name'     => 'Appendix',
            'position' => 3,
        ]);
        
        $this->taskProcess->inputArtifacts()->attach([
            $artifact1->id,
            $artifact2->id, 
            $artifact3->id,
        ]);

        // When we run the workflow output task
        $this->taskRunner->run();

        // Then all artifacts should be attached to the workflow as output artifacts
        $this->workflowRun->refresh();
        $workflowOutputArtifacts = $this->workflowRun->outputArtifacts()->orderBy('position')->get();
        $this->assertCount(3, $workflowOutputArtifacts);
        
        // Verify the artifacts are in the correct order
        $this->assertEquals('Chapter 1', $workflowOutputArtifacts[0]->name);
        $this->assertEquals('Chapter 2', $workflowOutputArtifacts[1]->name);
        $this->assertEquals('Appendix', $workflowOutputArtifacts[2]->name);
    }

    public function test_run_withTaskRunNotInWorkflow_throwsException(): void
    {
        // Given we have a task run that's not part of a workflow
        $standaloneTaskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'workflow_run_id'    => null, // No workflow
            'name'               => 'Standalone Task Run',
        ]);

        $standaloneTaskProcess = TaskProcess::factory()->create([
            'task_run_id' => $standaloneTaskRun->id,
            'activity'    => 'Testing standalone task',
        ]);

        $standaloneRunner = $standaloneTaskProcess->getRunner();
        
        // And we have input artifacts
        $artifact = Artifact::factory()->create([
            'name' => 'Standalone Output',
        ]);
        
        $standaloneTaskProcess->inputArtifacts()->attach($artifact->id);

        // When we run the workflow output task on a standalone task
        // Then it should throw an exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WorkflowOutputTaskRunner can only be used within a workflow context');
        
        $standaloneRunner->run();
    }

    public function test_collectFinalOutputArtifacts_withExplicitOutputs_returnsWorkflowOutputArtifacts(): void
    {
        // Given we have workflow output artifacts attached to the workflow run
        $artifact1 = Artifact::factory()->create(['name' => 'Final Output 1']);
        $artifact2 = Artifact::factory()->create(['name' => 'Final Output 2']);
        
        $this->workflowRun->addOutputArtifacts([$artifact1, $artifact2]);

        // And we also have regular task run artifacts (these should be ignored)
        $regularArtifact = Artifact::factory()->create(['name' => 'Regular Task Output']);
        $this->taskRun->outputArtifacts()->attach($regularArtifact->id);

        // When we collect final output artifacts
        $finalOutputs = $this->workflowRun->collectFinalOutputArtifacts();

        // Then we should get only the explicit workflow output artifacts
        $this->assertCount(2, $finalOutputs);
        $this->assertTrue($finalOutputs->contains($artifact1));
        $this->assertTrue($finalOutputs->contains($artifact2));
        $this->assertFalse($finalOutputs->contains($regularArtifact));
    }

    public function test_collectFinalOutputArtifacts_withoutExplicitOutputs_returnsEmptyCollection(): void
    {
        // Given we have no explicit workflow output artifacts
        // But we have regular task run outputs (these are NOT considered workflow outputs in new design)
        $taskRunArtifact = Artifact::factory()->create(['name' => 'Task Run Output']);
        $this->taskRun->outputArtifacts()->attach($taskRunArtifact->id);

        // When we collect final output artifacts
        $this->workflowRun->refresh();
        $finalOutputs = $this->workflowRun->collectFinalOutputArtifacts();

        // Then we should get an empty collection since there are no explicit workflow outputs
        // The new design requires using WorkflowOutputTaskRunner to explicitly mark workflow outputs
        $this->assertCount(0, $finalOutputs);
    }

    public function test_addOutputArtifacts_syncWithoutDetaching_allowsMultipleCalls(): void
    {
        // Given we have initial artifacts
        $artifact1 = Artifact::factory()->create(['name' => 'Output 1']);
        $artifact2 = Artifact::factory()->create(['name' => 'Output 2']);
        
        $this->workflowRun->addOutputArtifacts([$artifact1]);

        // When we add more artifacts
        $this->workflowRun->addOutputArtifacts([$artifact2]);

        // Then both artifacts should be present (no detaching)
        $outputArtifacts = $this->workflowRun->outputArtifacts()->get();
        $this->assertCount(2, $outputArtifacts);
        $this->assertTrue($outputArtifacts->contains($artifact1));
        $this->assertTrue($outputArtifacts->contains($artifact2));
    }

    public function test_clearOutputArtifacts_removesAllWorkflowOutputs(): void
    {
        // Given we have workflow output artifacts
        $artifact1 = Artifact::factory()->create(['name' => 'Output 1']);
        $artifact2 = Artifact::factory()->create(['name' => 'Output 2']);
        
        $this->workflowRun->addOutputArtifacts([$artifact1, $artifact2]);
        
        // Verify they were added
        $this->assertCount(2, $this->workflowRun->outputArtifacts()->get());

        // When we clear output artifacts
        $this->workflowRun->clearOutputArtifacts();

        // Then no workflow output artifacts should remain
        $outputArtifacts = $this->workflowRun->outputArtifacts()->get();
        $this->assertCount(0, $outputArtifacts);
    }
}