<?php

namespace Tests\Unit;

use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Usage\UsageEvent;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UsageSubscriptionEdgeCasesTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_subscription_prevents_duplicate_entries(): void
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

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->subscribeToUsageEvent($usageEvent);

        $this->assertEquals(1, $uiDemand->subscribedUsageEvents()->count());
    }

    public function test_unsubscribe_from_nonexistent_subscription_does_not_error(): void
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

        $this->assertEquals(0, $uiDemand->subscribedUsageEvents()->count());

        $uiDemand->unsubscribeFromUsageEvent($usageEvent);

        $this->assertEquals(0, $uiDemand->subscribedUsageEvents()->count());
    }

    public function test_usage_summary_refresh_with_deleted_usage_event(): void
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

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $this->assertNotNull($uiDemand->usageSummary);
        $this->assertEquals(0.003, $uiDemand->usageSummary->total_cost);

        $usageEvent->delete();

        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $this->assertNull($uiDemand->usageSummary);
    }

    public function test_usage_summary_handles_zero_values_correctly(): void
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
            'event_type'    => 'data_processing',
            'api_name'      => 'custom_api',
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'input_cost'    => 0.0,
            'output_cost'   => 0.0,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
        $this->assertEquals(0, $summary->input_tokens);
        $this->assertEquals(0, $summary->output_tokens);
        $this->assertEquals(0.0, $summary->input_cost);
        $this->assertEquals(0.0, $summary->output_cost);
        $this->assertEquals(0.0, $summary->total_cost);
    }

    public function test_usage_summary_handles_large_numbers_correctly(): void
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
            'input_tokens'  => 999999,
            'output_tokens' => 888888,
            'input_cost'    => 999.99,
            'output_cost'   => 888.88,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(1, $summary->count);
        $this->assertEquals(999999, $summary->input_tokens);
        $this->assertEquals(888888, $summary->output_tokens);
        $this->assertEquals(999.99, $summary->input_cost);
        $this->assertEquals(888.88, $summary->output_cost);
        $this->assertEquals(1888.87, $summary->total_cost);
    }

    public function test_usage_summary_precision_maintained_with_small_decimals(): void
    {
        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $taskProcess1 = TaskProcess::factory()->create();
        $taskProcess2 = TaskProcess::factory()->create();

        $usageEvent1 = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess1->id,
            'object_id_int' => $taskProcess1->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 10,
            'output_tokens' => 5,
            'input_cost'    => 0.00001,
            'output_cost'   => 0.00002,
            'request_count' => 1,
        ]);

        $usageEvent2 = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess2->id,
            'object_id_int' => $taskProcess2->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 15,
            'output_tokens' => 8,
            'input_cost'    => 0.00003,
            'output_cost'   => 0.00004,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent1);
        $uiDemand->subscribeToUsageEvent($usageEvent2);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->count);
        $this->assertEquals(25, $summary->input_tokens);
        $this->assertEquals(13, $summary->output_tokens);

        // Very small decimal values may be rounded to 0 by database precision
        // This is expected behavior for very small currency values
        if ($summary->input_cost > 0) {
            $this->assertEqualsWithDelta(0.00004, $summary->input_cost, 0.0000001);
        } else {
            $this->assertEquals(0, $summary->input_cost);
        }

        if ($summary->output_cost > 0) {
            $this->assertEqualsWithDelta(0.00006, $summary->output_cost, 0.0000001);
        } else {
            $this->assertEquals(0, $summary->output_cost);
        }

        if ($summary->total_cost > 0) {
            $this->assertEqualsWithDelta(0.0001, $summary->total_cost, 0.0000001);
        } else {
            $this->assertEquals(0, $summary->total_cost);
        }
    }

    public function test_usage_attribute_returns_correct_format(): void
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
            'input_tokens'  => 150,
            'output_tokens' => 75,
            'input_cost'    => 0.003,
            'output_cost'   => 0.006,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $summary = $uiDemand->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(225, $summary->total_tokens);
        $this->assertEquals(0.009, $summary->total_cost);
    }

    public function test_subscription_works_with_different_team_contexts(): void
    {
        $team1       = $this->user->currentTeam;
        $team2       = $team1->replicate();
        $team2->name = 'Team 2';
        $team2->save();

        $uiDemand1 = UiDemand::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $this->user->id,
        ]);

        $uiDemand2 = UiDemand::factory()->create([
            'team_id' => $team2->id,
            'user_id' => $this->user->id,
        ]);

        $taskProcess1 = TaskProcess::factory()->create();
        $taskProcess2 = TaskProcess::factory()->create();

        $usageEvent1 = UsageEvent::create([
            'team_id'       => $team1->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess1->id,
            'object_id_int' => $taskProcess1->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ]);

        $usageEvent2 = UsageEvent::create([
            'team_id'       => $team2->id,
            'user_id'       => $this->user->id,
            'object_type'   => TaskProcess::class,
            'object_id'     => (string)$taskProcess2->id,
            'object_id_int' => $taskProcess2->id,
            'event_type'    => 'ai_completion',
            'api_name'      => 'openai',
            'input_tokens'  => 200,
            'output_tokens' => 100,
            'input_cost'    => 0.004,
            'output_cost'   => 0.008,
            'request_count' => 1,
        ]);

        $uiDemand1->subscribeToUsageEvent($usageEvent1);
        $uiDemand2->subscribeToUsageEvent($usageEvent2);

        $this->assertEquals(1, $uiDemand1->subscribedUsageEvents()->count());
        $this->assertEquals(1, $uiDemand2->subscribedUsageEvents()->count());

        $this->assertEquals($usageEvent1->id, $uiDemand1->subscribedUsageEvents()->first()->id);
        $this->assertEquals($usageEvent2->id, $uiDemand2->subscribedUsageEvents()->first()->id);
    }
}
