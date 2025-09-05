<?php

namespace Tests\Unit;

use App\Events\UsageEventCreated;
use App\Listeners\UiDemandUsageSubscriber;
use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageEvent;
use App\Models\Workflow\WorkflowRun;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageEventSystemTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_usage_event_created_event_fired_when_usage_recorded(): void
    {
        Event::fake();

        $taskProcess          = TaskProcess::factory()->create();
        $usageTrackingService = app(UsageTrackingService::class);

        $usageEvent = $usageTrackingService->recordUsage($taskProcess, 'ai_completion', 'openai', [
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ], $this->user);

        Event::assertDispatched(UsageEventCreated::class, function ($event) use ($usageEvent) {
            return $event->usageEvent->id === $usageEvent->id;
        });
    }

    public function test_ui_demand_usage_subscriber_handles_task_process_events(): void
    {
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

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 150,
            'output_tokens' => 75,
            'input_cost'    => 0.003,
            'output_cost'   => 0.006,
            'request_count' => 1,
        ]);

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand->refresh();
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
    }

    public function test_ui_demand_usage_subscriber_handles_task_run_events(): void
    {
        $workflowRun = WorkflowRun::factory()->create();
        $taskRun     = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun);
        $taskRun->save();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'write_demand',
        ]);

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskRun::class,
            'object_id'     => (string)$taskRun->id,
            'object_id_int' => $taskRun->id,
            'event_type'    => 'data_processing',
            'api_name'      => 'custom_api',
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'input_cost'    => 0.005,
            'output_cost'   => 0.010,
            'request_count' => 1,
        ]);

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand->refresh();
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
    }

    public function test_ui_demand_usage_subscriber_handles_workflow_run_events(): void
    {
        $workflowRun = WorkflowRun::factory()->create();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'extract_data',
        ]);

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => WorkflowRun::class,
            'object_id'     => (string)$workflowRun->id,
            'object_id_int' => $workflowRun->id,
            'event_type'    => 'orchestration',
            'api_name'      => 'internal',
            'input_tokens'  => 50,
            'output_tokens' => 25,
            'input_cost'    => 0.0005,
            'output_cost'   => 0.001,
            'request_count' => 1,
        ]);

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand->refresh();
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
    }

    public function test_ui_demand_usage_subscriber_ignores_unrelated_events(): void
    {
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

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand->refresh();
        $this->assertFalse($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
    }

    public function test_usage_summary_automatically_refreshed_after_subscription(): void
    {
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

        $this->assertNull($uiDemand->usageSummary);

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 200,
            'output_tokens' => 100,
            'input_cost'    => 0.004,
            'output_cost'   => 0.008,
            'request_count' => 1,
        ]);

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand->refresh();
        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
        $this->assertEquals(200, $summary->input_tokens);
        $this->assertEquals(100, $summary->output_tokens);
        $this->assertEquals(0.012, $summary->total_cost);
    }

    public function test_multiple_ui_demands_can_subscribe_to_same_usage_event(): void
    {
        $workflowRun1 = WorkflowRun::factory()->create();
        $workflowRun2 = WorkflowRun::factory()->create();
        $taskRun      = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun1);
        $taskRun->save();

        $uiDemand1 = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand2 = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand1->workflowRuns()->attach($workflowRun1, [
            'workflow_type' => 'extract_data',
        ]);

        $uiDemand2->workflowRuns()->attach($workflowRun1, [
            'workflow_type' => 'write_demand',
        ]);

        $taskProcess = TaskProcess::factory()->create();
        $taskProcess->taskRun()->associate($taskRun);
        $taskProcess->save();

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 300,
            'output_tokens' => 150,
            'input_cost'    => 0.006,
            'output_cost'   => 0.012,
            'request_count' => 1,
        ]);

        $listener = new UiDemandUsageSubscriber();
        $event    = new UsageEventCreated($usageEvent);
        $listener->handle($event);

        $uiDemand1->refresh();
        $uiDemand2->refresh();

        $this->assertTrue($uiDemand1->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());
        $this->assertTrue($uiDemand2->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());

        $this->assertEquals(0.018, $uiDemand1->usageSummary->total_cost);
        $this->assertEquals(0.018, $uiDemand2->usageSummary->total_cost);
    }
}
