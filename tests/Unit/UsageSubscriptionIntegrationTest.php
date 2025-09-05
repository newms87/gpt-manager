<?php

namespace Tests\Unit;

use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageEvent;
use App\Models\Workflow\WorkflowRun;
use App\Services\Usage\UsageTrackingService;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageSubscriptionIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_full_end_to_end_usage_tracking_flow(): void
    {
        $this->setUpTeam();

        $workflowRun = WorkflowRun::factory()->create();
        $taskRun     = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun);
        $taskRun->save();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'extract_data',
        ]);

        $taskProcess = TaskProcess::factory()->create();
        $taskProcess->taskRun()->associate($taskRun);
        $taskProcess->save();

        $usageTrackingService = app(UsageTrackingService::class);

        $usageEvent = $usageTrackingService->recordUsage(
            $taskProcess,
            'ai_completion',
            'openai',
            [
                'input_tokens'  => 150,
                'output_tokens' => 75,
                'input_cost'    => 0.003,
                'output_cost'   => 0.006,
                'run_time_ms'   => 2500,
                'request_count' => 1,
            ],
            $this->user
        );

        $this->assertInstanceOf(UsageEvent::class, $usageEvent);
        $this->assertEquals(TaskProcess::class, $usageEvent->object_type);
        $this->assertEquals($taskProcess->id, $usageEvent->object_id_int);

        $uiDemand->refresh();
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
        $this->assertEquals(150, $summary->input_tokens);
        $this->assertEquals(75, $summary->output_tokens);
        $this->assertEquals(0.003, $summary->input_cost);
        $this->assertEquals(0.006, $summary->output_cost);
        $this->assertEquals(0.009, $summary->total_cost);

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(225, $summary->total_tokens);
        $this->assertEquals(0.009, $summary->total_cost);
    }

    public function test_multiple_usage_events_aggregate_correctly(): void
    {
        $this->setUpTeam();

        $workflowRun = WorkflowRun::factory()->create();
        $taskRun     = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun);
        $taskRun->save();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'extract_data',
        ]);

        $taskProcess1 = TaskProcess::factory()->create();
        $taskProcess1->taskRun()->associate($taskRun);
        $taskProcess1->save();

        $taskProcess2 = TaskProcess::factory()->create();
        $taskProcess2->taskRun()->associate($taskRun);
        $taskProcess2->save();

        $usageTrackingService = app(UsageTrackingService::class);

        $usageEvent1 = $usageTrackingService->recordUsage($taskProcess1, 'ai_completion', 'openai', [
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ], $this->user);

        $usageEvent2 = $usageTrackingService->recordUsage($taskProcess2, 'ai_completion', 'openai', [
            'input_tokens'  => 200,
            'output_tokens' => 100,
            'input_cost'    => 0.002,
            'output_cost'   => 0.004,
            'request_count' => 1,
        ], $this->user);

        $uiDemand->refresh();

        $this->assertEquals(2, $uiDemand->subscribedUsageEvents()->count());

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->count);
        $this->assertEquals(300, $summary->input_tokens);
        $this->assertEquals(150, $summary->output_tokens);
        $this->assertEquals(0.003, $summary->input_cost);
        $this->assertEquals(0.006, $summary->output_cost);
        $this->assertEquals(0.009, $summary->total_cost);
    }

    public function test_usage_subscription_across_different_object_types(): void
    {
        $this->setUpTeam();

        $workflowRun = WorkflowRun::factory()->create();
        $taskRun     = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun);
        $taskRun->save();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'extract_data',
        ]);

        $taskProcess = TaskProcess::factory()->create();
        $taskProcess->taskRun()->associate($taskRun);
        $taskProcess->save();

        $usageTrackingService = app(UsageTrackingService::class);

        $taskProcessEvent = $usageTrackingService->recordUsage($taskProcess, 'ai_completion', 'openai', [
            'input_tokens' => 100, 'output_tokens' => 50, 'input_cost' => 0.001, 'output_cost' => 0.002, 'request_count' => 1,
        ], $this->user);

        $taskRunEvent = $usageTrackingService->recordUsage($taskRun, 'data_processing', 'custom_api', [
            'input_tokens' => 0, 'output_tokens' => 0, 'input_cost' => 0.005, 'output_cost' => 0.010, 'request_count' => 1,
        ], $this->user);

        $workflowRunEvent = $usageTrackingService->recordUsage($workflowRun, 'orchestration', 'internal', [
            'input_tokens' => 50, 'output_tokens' => 25, 'input_cost' => 0.0005, 'output_cost' => 0.001, 'request_count' => 1,
        ], $this->user);

        $uiDemand->refresh();

        $this->assertEquals(3, $uiDemand->subscribedUsageEvents()->count());

        $summary = $uiDemand->usageSummary;
        $this->assertEquals(3, $summary->count);
        $this->assertEquals(150, $summary->input_tokens);
        $this->assertEquals(75, $summary->output_tokens);
        $this->assertEquals(0.0065, $summary->input_cost);
        $this->assertEquals(0.013, $summary->output_cost);
        $this->assertEquals(0.0195, $summary->total_cost);
    }

    public function test_unsubscribe_from_usage_event(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $taskProcess = TaskProcess::factory()->create();

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());

        $uiDemand->unsubscribeFromUsageEvent($usageEvent);
        $this->assertFalse($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
    }

    public function test_duplicate_subscription_prevented(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $taskProcess = TaskProcess::factory()->create();

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->subscribeToUsageEvent($usageEvent);

        $this->assertEquals(1, $uiDemand->subscribedUsageEvents()->count());
    }

    public function test_usage_summary_refresh_with_no_subscribed_events(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->refreshUsageSummaryFromSubscribedEvents();

        $this->assertNull($uiDemand->usageSummary);
    }

    public function test_usage_attribute_returns_null_when_no_summary(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertNull($uiDemand->usageSummary);
    }
}
