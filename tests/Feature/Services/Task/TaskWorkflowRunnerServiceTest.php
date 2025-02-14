<?php

namespace Feature\Services\Task;

use App\Models\Task\TaskWorkflow;
use App\Models\Task\TaskWorkflowConnection;
use App\Models\Task\TaskWorkflowNode;
use App\Models\Workflow\Artifact;
use App\Services\Task\TaskWorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;

class TaskWorkflowRunnerServiceTest extends AuthenticatedTestCase
{
    public function test_start_withEmptyWorkflow_throwValidationError(): void
    {
        // Given
        $taskWorkflow = TaskWorkflow::factory()->create();

        // Then
        $this->expectException(ValidationError::class);

        // When
        $taskWorkflowRun = TaskWorkflowRunnerService::start($taskWorkflow);

        // Then
        $this->assertNull($taskWorkflowRun, 'TaskWorkflowRun should not have been created');
    }

    public function test_start_withSingleNode_singleTaskRunCreatedAndCompleted(): void
    {
        // Given
        $taskWorkflow = TaskWorkflow::factory()->withNodes(1)->create();

        // When
        $taskWorkflowRun = TaskWorkflowRunnerService::start($taskWorkflow);

        // Then
        $this->assertNotNull($taskWorkflowRun, 'TaskWorkflowRun should be created');
        $this->assertEquals(1, $taskWorkflowRun->taskRuns->count(), 'TaskRun should have a been created');
        $this->assertTrue($taskWorkflowRun->taskRuns->first()->isCompleted(), 'TaskRun should have completed successfully');
    }

    public function test_start_withStartingNodeAndTargetNode_twoTaskRunsCreatedAndCompleted(): void
    {
        // Given
        $taskWorkflow  = TaskWorkflow::factory()->create();
        $workflowNodeA = TaskWorkflowNode::factory()->recycle($taskWorkflow)->create();
        $workflowNodeB = TaskWorkflowNode::factory()->recycle($taskWorkflow)->create();
        TaskWorkflowConnection::factory()->connect($taskWorkflow, $workflowNodeA, $workflowNodeB)->create();

        // When
        $taskWorkflowRun = TaskWorkflowRunnerService::start($taskWorkflow);

        // Then
        $this->assertEquals(2, $taskWorkflowRun->taskRuns->count(), '2 TaskRuns should have a been created');
        $this->assertTrue($taskWorkflowRun->taskRuns->first()->isCompleted(), 'TaskRun A should have completed successfully');
        $this->assertTrue($taskWorkflowRun->taskRuns->last()->isCompleted(), 'TaskRun B should have completed successfully');
    }

    public function test_start_withStartingNodeAndTargetNode_outputArtifactOfSourceIsPassedToTarget(): void
    {
        // Given
        $artifact      = Artifact::factory()->create(['json_content' => ['hello' => 'world']]);
        $taskWorkflow  = TaskWorkflow::factory()->create();
        $workflowNodeA = TaskWorkflowNode::factory()->recycle($taskWorkflow)->create();
        $workflowNodeB = TaskWorkflowNode::factory()->recycle($taskWorkflow)->create();
        $connection    = TaskWorkflowConnection::factory()->connect($taskWorkflow, $workflowNodeA, $workflowNodeB)->create();

        // When
        $taskWorkflowRun = TaskWorkflowRunnerService::start($taskWorkflow, null, [$artifact]);

        // Then
        $sourceOutputArtifacts = $taskWorkflowRun->collectOutputArtifactsFromSourceNodes($connection->targetNode);
        $targetInputArtifacts  = $taskWorkflowRun->collectInputArtifactsForNode($connection->targetNode);
        $this->assertEquals(1, $targetInputArtifacts->count(), 'Exactly 1 Output artifact of source node should be passed to target node');

        /** @var Artifact $sourceOutputArtifact */
        $sourceOutputArtifact = $sourceOutputArtifacts->first();
        /** @var Artifact $targetInputArtifact */
        $targetInputArtifact = $targetInputArtifacts->first();
        $this->assertEquals($sourceOutputArtifact->json_content, $targetInputArtifact->json_content, 'Output artifacts of source node should be passed to target node');
    }
}
