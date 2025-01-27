<?php

namespace Tests\Feature\Workflow;

use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\WorkflowTools\WorkflowInputWorkflowTool;
use Tests\AuthenticatedTestCase;
use Tests\Feature\MockData\AiMockData;

class WorkflowInputWorkflowToolTest extends AuthenticatedTestCase
{
    use AiMockData;

    public function test_resolveAndAssignTasks_assignsOneTask(): void
    {
        // Given
        $workflowJob    = WorkflowJob::factory()->isWorkflowInputTool()->create();
        $workflowJobRun = WorkflowJobRun::factory()->recycle($workflowJob)->create();

        // When
        app(WorkflowInputWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun);

        // Then
        $tasks = $workflowJobRun->tasks()->get();
        $this->assertCount(1, $tasks, 'Expected one task');
    }

    public function test_runTask_producesArtifactWithContent(): void
    {
        // Given
        $content        = 'Workflow Input Content';
        $workflowInput  = WorkflowInput::factory()->create(['content' => $content]);
        $workflowJob    = WorkflowJob::factory()->isWorkflowInputTool()->create();
        $workflowJobRun = WorkflowJobRun::factory()->recycle($workflowJob)->recycle($workflowInput)->create();
        app(WorkflowInputWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun);
        $task = $workflowJobRun->tasks()->first();

        // When
        app(WorkflowInputWorkflowTool::class)->runTask($task);

        // Then
        $artifacts = $task->artifacts()->get();
        $this->assertCount(1, $artifacts, 'Expected one artifact');
        $this->assertEquals($content, $artifacts->first()->text_content);
    }

    public function test_runTask_producesArtifactWithFiles(): void
    {
        // Given
        $workflowInput  = WorkflowInput::factory()->withStoredFile()->create();
        $workflowJob    = WorkflowJob::factory()->isWorkflowInputTool()->create();
        $workflowJobRun = WorkflowJobRun::factory()->recycle($workflowJob)->recycle($workflowInput)->create();
        app(WorkflowInputWorkflowTool::class)->resolveAndAssignTasks($workflowJobRun);
        $task = $workflowJobRun->tasks()->first();

        // When
        app(WorkflowInputWorkflowTool::class)->runTask($task);

        // Then
        $artifact = $task->artifacts()->first();
        $this->assertCount(1, $artifact->storedFiles, 'Expected one stored file on the artifact');
    }
}
