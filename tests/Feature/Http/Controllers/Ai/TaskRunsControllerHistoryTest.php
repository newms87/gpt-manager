<?php

namespace Tests\Feature\Http\Controllers\Ai;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskRunnerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests for the TaskRunsController history endpoint.
 *
 * The history endpoint returns historical (soft-deleted) task runs that were
 * replaced by the current active run via the historicalRuns() relationship.
 */
class TaskRunsControllerHistoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ============================================================================
    // History Endpoint Returns Historical Runs
    // ============================================================================

    #[Test]
    public function history_endpoint_returns_historical_runs_after_restart(): void
    {
        // Given: A failed task run that has been restarted
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinute();
        $originalRun->failed_at  = now();
        $originalRun->save();

        // Restart the task run to create a historical run
        $activeRun = TaskRunnerService::restart($originalRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$activeRun->id}/history");

        // Then: Should return the historical run
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(1, $data, 'Should have 1 historical run');
        $this->assertEquals($originalRun->id, $data[0]['id'], 'Historical run should be the original run');
    }

    #[Test]
    public function history_endpoint_returns_multiple_historical_runs_after_multiple_restarts(): void
    {
        // Given: A task run that has been restarted multiple times
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinute();
        $originalRun->failed_at  = now();
        $originalRun->save();

        // First restart
        $firstActiveRun             = TaskRunnerService::restart($originalRun);
        $firstActiveRun->started_at = now()->subMinute();
        $firstActiveRun->failed_at  = now();
        $firstActiveRun->save();

        // Second restart
        $finalActiveRun = TaskRunnerService::restart($firstActiveRun);

        // When: We call the history endpoint on the final active run
        $response = $this->getJson("/api/task-runs/{$finalActiveRun->id}/history");

        // Then: Should return both historical runs
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data, 'Should have 2 historical runs');

        // Historical runs should be ordered by created_at DESC (most recent first)
        $historicalIds = array_column($data, 'id');
        $this->assertContains($originalRun->id, $historicalIds, 'Should contain original run');
        $this->assertContains($firstActiveRun->id, $historicalIds, 'Should contain first restarted run');
    }

    #[Test]
    public function history_endpoint_returns_empty_array_when_no_historical_runs(): void
    {
        // Given: A fresh task run with no restart history
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $taskRun = TaskRunnerService::prepareTaskRun($taskDefinition);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$taskRun->id}/history");

        // Then: Should return empty array
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
        ]);
    }

    // ============================================================================
    // History Endpoint Includes Correct Relationships
    // ============================================================================

    #[Test]
    public function history_endpoint_includes_task_processes_relationship(): void
    {
        // Given: A failed task run with processes that has been restarted
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinute();
        $originalRun->save();

        // Add some completed task processes to the original run
        TaskProcess::factory()->for($originalRun)->count(2)->create([
            'is_ready'     => true,
            'started_at'   => now()->subMinute(),
            'completed_at' => now(),
            'status'       => WorkflowStatesContract::STATUS_COMPLETED,
        ]);
        $originalRun->updateRelationCounter('taskProcesses');

        // Now mark the task run as failed and refresh to get proper state
        $originalRun->fresh();
        $originalRun->failed_at = now();
        $originalRun->save();

        // Restart the task run
        $activeRun = TaskRunnerService::restart($originalRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$activeRun->id}/history");

        // Then: The historical run should be returned with process_count
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(2, $data[0]['process_count'], 'Historical run should have process_count = 2');
    }

    #[Test]
    public function history_endpoint_includes_input_artifacts_relationship(): void
    {
        // Given: A failed task run with input artifacts that has been restarted
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinute();
        $originalRun->failed_at  = now();
        $originalRun->save();

        // Add input artifacts to the original run
        $artifacts = Artifact::factory()->count(3)->create();
        $originalRun->inputArtifacts()->sync($artifacts->pluck('id'));
        $originalRun->updateRelationCounter('inputArtifacts');

        // Restart the task run
        $activeRun = TaskRunnerService::restart($originalRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$activeRun->id}/history");

        // Then: The historical run should be returned with input_artifacts_count
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(3, $data[0]['input_artifacts_count'], 'Historical run should have input_artifacts_count = 3');
    }

    #[Test]
    public function history_endpoint_includes_output_artifacts_relationship(): void
    {
        // Given: A failed task run with output artifacts that has been restarted
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinute();
        $originalRun->failed_at  = now();
        $originalRun->save();

        // Add output artifacts to the original run
        $artifacts = Artifact::factory()->count(2)->create();
        $originalRun->outputArtifacts()->sync($artifacts->pluck('id'));
        $originalRun->updateRelationCounter('outputArtifacts');

        // Restart the task run
        $activeRun = TaskRunnerService::restart($originalRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$activeRun->id}/history");

        // Then: The historical run should be returned with output_artifacts_count
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(2, $data[0]['output_artifacts_count'], 'Historical run should have output_artifacts_count = 2');
    }

    // ============================================================================
    // History Endpoint Preserves Historical Data
    // ============================================================================

    #[Test]
    public function history_endpoint_returns_correct_status_and_timestamps_for_historical_runs(): void
    {
        // Given: A failed task run that has been restarted
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $failedAt                = now()->subMinutes(5);
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinutes(10);
        $originalRun->failed_at  = $failedAt;
        $originalRun->save();

        // Restart the task run
        $activeRun = TaskRunnerService::restart($originalRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$activeRun->id}/history");

        // Then: The historical run should preserve its failed status
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(WorkflowStatesContract::STATUS_FAILED, $data[0]['status'], 'Historical run should have failed status');
        $this->assertNotNull($data[0]['failed_at'], 'Historical run should have failed_at timestamp');
    }

    #[Test]
    public function history_endpoint_returns_historical_runs_ordered_by_created_at_desc(): void
    {
        // Given: A task run that has been restarted multiple times
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create original run with older timestamp
        $originalRun             = TaskRunnerService::prepareTaskRun($taskDefinition);
        $originalRun->started_at = now()->subMinutes(15);
        $originalRun->failed_at  = now()->subMinutes(10);
        $originalRun->save();

        // First restart
        $firstActiveRun             = TaskRunnerService::restart($originalRun);
        $firstActiveRun->started_at = now()->subMinutes(5);
        $firstActiveRun->failed_at  = now();
        $firstActiveRun->save();

        // Second restart
        $finalActiveRun = TaskRunnerService::restart($firstActiveRun);

        // When: We call the history endpoint
        $response = $this->getJson("/api/task-runs/{$finalActiveRun->id}/history");

        // Then: Historical runs should be ordered by created_at DESC (most recent first)
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // The first restarted run (more recent) should come before the original run
        $this->assertEquals($firstActiveRun->id, $data[0]['id'], 'Most recent historical run should come first');
        $this->assertEquals($originalRun->id, $data[1]['id'], 'Original run should come second');
    }
}
