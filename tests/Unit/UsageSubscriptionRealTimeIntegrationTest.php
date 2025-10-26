<?php

namespace Tests\Unit;

use App\Events\UsageSummaryUpdatedEvent;
use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowRun;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Support\Facades\Event;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageSubscriptionRealTimeIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_usage_summary_events_are_fired_during_full_workflow(): void
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

        $eventsFired = [];
        Event::listen(UsageSummaryUpdatedEvent::class, function ($event) use (&$eventsFired) {
            $eventsFired[] = [
                'event'       => $event,
                'summary_id'  => $event->getUsageSummary()->id,
                'object_type' => $event->getUsageSummary()->object_type,
                'object_id'   => $event->getUsageSummary()->object_id,
                'total_cost'  => $event->getUsageSummary()->total_cost,
            ];
        });

        $usageTrackingService = app(UsageTrackingService::class);

        // First usage event
        $usageEvent1 = $usageTrackingService->recordUsage(
            $taskProcess,
            'ai_completion',
            'openai',
            [
                'input_tokens'  => 100,
                'output_tokens' => 50,
                'input_cost'    => 0.001,
                'output_cost'   => 0.002,
                'run_time_ms'   => 1500,
                'request_count' => 1,
            ],
            $this->user
        );

        // Verify UiDemand events were fired (filter out other object types)
        $uiDemandEvents = collect($eventsFired)->filter(fn($event) => $event['object_type'] === $uiDemand->getMorphClass());
        $this->assertGreaterThan(0, $uiDemandEvents->count());

        $firstUiDemandEvent = $uiDemandEvents->first(fn($event) => $event['total_cost'] > 0);
        $this->assertNotNull($firstUiDemandEvent);
        $this->assertEquals($uiDemand->getMorphClass(), $firstUiDemandEvent['object_type']);
        $this->assertEquals((string)$uiDemand->id, $firstUiDemandEvent['object_id']);

        // Second usage event
        $usageEvent2 = $usageTrackingService->recordUsage(
            $taskProcess,
            'ai_completion',
            'openai',
            [
                'input_tokens'  => 200,
                'output_tokens' => 100,
                'input_cost'    => 0.004,
                'output_cost'   => 0.008,
                'run_time_ms'   => 2500,
                'request_count' => 1,
            ],
            $this->user
        );

        // Verify second UiDemand event was fired with updated totals
        $uiDemandEventsAfter = collect($eventsFired)->filter(fn($event) => $event['object_type'] === $uiDemand->getMorphClass());
        $this->assertGreaterThan(1, $uiDemandEventsAfter->count());
        $lastUiDemandEvent = $uiDemandEventsAfter->last();
        $this->assertEquals(0.015, $lastUiDemandEvent['total_cost']); // 0.003 + 0.012 = 0.015

        // Verify the UiDemand usage was updated correctly
        $uiDemand->refresh();
        $this->assertNotNull($uiDemand->usageSummary);
        $this->assertEquals(2, $uiDemand->usageSummary->count);
        $this->assertEquals(300, $uiDemand->usageSummary->input_tokens);  // 100 + 200
        $this->assertEquals(150, $uiDemand->usageSummary->output_tokens); // 50 + 100
        $this->assertEquals(0.015, $uiDemand->usageSummary->total_cost);  // 0.005 + 0.010
    }

    public function test_usage_summary_broadcast_channel_matches_team_id(): void
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        // Subscribe to UsageSummary events (required for BroadcastsWithSubscriptions)
        $this->postJson('/api/pusher/subscribe', [
            'resource_type'      => 'UsageSummary',
            'model_id_or_filter' => true,
        ]);

        $capturedChannel = null;
        Event::listen(UsageSummaryUpdatedEvent::class, function ($event) use (&$capturedChannel) {
            $channels = $event->broadcastOn();
            $capturedChannel = !empty($channels) ? $channels[0]->name : null;
        });

        // Create a usage summary to trigger the event
        $uiDemand->usageSummary()->create([
            'object_type'   => $uiDemand->getMorphClass(),
            'object_id'     => $uiDemand->id,
            'object_id_int' => $uiDemand->id,
            'count'         => 1,
            'input_tokens'  => 50,
            'output_tokens' => 25,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'total_cost'    => 0.003,
            'request_count' => 1,
        ]);

        $expectedChannel = 'private-UsageSummary.' . $this->user->currentTeam->id;
        $this->assertEquals($expectedChannel, $capturedChannel);
    }
}
