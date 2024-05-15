<?php

namespace Tests\Feature\Workflow;

use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    public function test_start_producesArtifact(): void
    {
        // Given
        $workflowRun = WorkflowRun::factory()->create();

        // When
        WorkflowService::start($workflowRun);

        // Then
        $this->assertNotNull($workflowRun->artifact);
    }
}
