<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\Workflow\WorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;

class WorkflowRunnerServiceTest extends AuthenticatedTestCase
{
    public function test_start_withEmptyWorkflow_throwValidationError(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create();

        // Then
        $this->expectException(ValidationError::class);

        // When
        $workflowRun = WorkflowRunnerService::start($workflowDefinition);

        // Then
        $this->assertNull($workflowRun, 'WorkflowRun should not have been created');
    }

    public function test_start_withSingleNode_singleTaskRunCreatedAndCompleted(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->withStartingNode()->create();

        // When
        $workflowRun = WorkflowRunnerService::start($workflowDefinition);

        // Then
        $this->assertNotNull($workflowRun, 'WorkflowRun should be created');
        $this->assertEquals(1, $workflowRun->taskRuns->count(), 'TaskRun should have a been created');
        $this->assertTrue($workflowRun->taskRuns->first()->isCompleted(), 'TaskRun should have completed successfully');
    }

    public function test_start_withStartingNodeAndTargetNode_twoTaskRunsCreatedAndCompleted(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create();
        $workflowNodeA      = WorkflowNode::factory()->recycle($workflowDefinition)->startingNode()->create();
        $workflowNodeB      = WorkflowNode::factory()->recycle($workflowDefinition)->create();
        WorkflowConnection::factory()->connect($workflowDefinition, $workflowNodeA, $workflowNodeB)->create();

        // When
        $workflowRun = WorkflowRunnerService::start($workflowDefinition);

        // Then
        $this->assertEquals(2, $workflowRun->taskRuns->count(), '2 TaskRuns should have a been created');
        $this->assertTrue($workflowRun->taskRuns->first()->isCompleted(), 'TaskRun A should have completed successfully');
        $this->assertTrue($workflowRun->taskRuns->last()->isCompleted(), 'TaskRun B should have completed successfully');
    }

    public function test_start_withStartingNodeAndTargetNode_outputArtifactOfSourceIsPassedToTarget(): void
    {
        // Given
        $artifactJsonContent = ['hello' => 'world'];
        $artifact            = Artifact::factory()->create(['json_content' => $artifactJsonContent]);
        $workflowDefinition  = WorkflowDefinition::factory()->create();
        $workflowNodeA       = WorkflowNode::factory()->recycle($workflowDefinition)->startingNode()->create();
        $workflowNodeB       = WorkflowNode::factory()->recycle($workflowDefinition)->create();
        $connection          = WorkflowConnection::factory()->connect($workflowDefinition, $workflowNodeA, $workflowNodeB)->create();

        // When
        $workflowRun = WorkflowRunnerService::start($workflowDefinition, [$artifact]);

        // Then
        $sourceOutputArtifacts = $workflowRun->collectOutputArtifactsForNode($connection->sourceNode);
        $targetInputArtifacts  = $workflowRun->collectInputArtifactsForNode($connection->targetNode);

        $this->assertEquals(1, $sourceOutputArtifacts->count(), 'Exactly 1 Output artifact should be produced by source node');
        $this->assertEquals(1, $targetInputArtifacts->count(), 'Exactly 1 Output artifact of source node should be passed to target node');

        /** @var Artifact $sourceOutputArtifact */
        $sourceOutputArtifact = $sourceOutputArtifacts->first();
        /** @var Artifact $targetInputArtifact */
        $targetInputArtifact = $targetInputArtifacts->first();
        $this->assertEquals($artifactJsonContent, $sourceOutputArtifact->json_content, 'Source Output artifact should match the input');
        $this->assertEquals($artifactJsonContent, $targetInputArtifact->json_content, 'Output artifacts of source node should be passed as input to target node');
    }
}
