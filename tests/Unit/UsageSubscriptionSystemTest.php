<?php

namespace Tests\Unit;

use App\Events\UsageEventCreated;
use App\Listeners\UiDemandUsageSubscriber;
use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageEvent;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageSubscriptionSystemTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_usage_event_has_subscribers_relationship(): void
    {
        $usageEvent = new UsageEvent();

        $this->assertTrue(method_exists($usageEvent, 'subscribers'));
    }

    public function test_has_usage_tracking_trait_has_subscribed_usage_events_relationship(): void
    {
        $uiDemand = new UiDemand();

        $this->assertTrue(method_exists($uiDemand, 'subscribedUsageEvents'));
        $this->assertTrue(method_exists($uiDemand, 'subscribeToUsageEvent'));
        $this->assertTrue(method_exists($uiDemand, 'refreshUsageSummaryFromSubscribedEvents'));
    }

    public function test_ui_demand_can_subscribe_to_usage_event(): void
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

        $subscribedEvents = $uiDemand->subscribedUsageEvents;
        $this->assertCount(1, $subscribedEvents);
        $this->assertEquals($usageEvent->id, $subscribedEvents->first()->id);
    }

    public function test_usage_summary_refreshed_from_subscribed_events(): void
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
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();

        $uiDemand->refresh();
        $summary = $uiDemand->usageSummary;

        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
        $this->assertEquals(100, $summary->input_tokens);
        $this->assertEquals(50, $summary->output_tokens);
        $this->assertEquals(0.001, $summary->input_cost);
        $this->assertEquals(0.002, $summary->output_cost);
        $this->assertEquals(0.003, $summary->total_cost);
    }

    public function test_ui_demand_usage_subscriber_handles_task_process_event(): void
    {
        $this->setUpTeam();

        $taskRun     = TaskRun::factory()->create();
        $workflowRun = WorkflowRun::factory()->create();
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
        $this->assertTrue($uiDemand->subscribedUsageEvents()->where('usage_event_id', $usageEvent->id)->exists());

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
    }
}
