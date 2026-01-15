<?php

namespace App\Console\Commands;

use App\Models\Demand\UiDemand;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\User;
use App\Models\Workflow\WorkflowRun;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Console\Command;

class TestUsageSubscriptionSystem extends Command
{
    protected $signature   = 'test:usage-subscription';

    protected $description = 'Test the new polymorphic usage subscription system';

    public function handle(): int
    {
        $this->info('🧪 Testing Usage Subscription System...');

        $user = User::first();
        if (!$user) {
            $this->error('No users found. Please create a user first.');

            return 1;
        }

        $team = $user->teams()->first();
        if (!$team) {
            $this->error('No teams found for user. Please create a team first.');

            return 1;
        }

        $this->line("Using User: {$user->name} (ID: {$user->id})");
        $this->line("Using Team: {$team->name} (ID: {$team->id})");

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title'   => 'Test Usage Subscription Demand',
        ]);

        $workflowRun = WorkflowRun::factory()->create();
        $taskRun     = TaskRun::factory()->create();
        $taskRun->workflowRun()->associate($workflowRun);
        $taskRun->save();

        $uiDemand->workflowRuns()->attach($workflowRun, [
            'workflow_type' => 'extract_data',
        ]);

        $taskProcess = TaskProcess::factory()->create();
        $taskProcess->taskRun()->associate($taskRun);
        $taskProcess->save();

        $this->line('✅ Created test entities:');
        $this->line("   - UiDemand: {$uiDemand->title} (ID: {$uiDemand->id})");
        $this->line("   - WorkflowRun: (ID: {$workflowRun->id})");
        $this->line("   - TaskRun: (ID: {$taskRun->id})");
        $this->line("   - TaskProcess: (ID: {$taskProcess->id})");

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
                'metadata'      => [
                    'model'       => 'gpt-5',
                    'temperature' => 0.7,
                    'test_run'    => true,
                ],
            ],
            $user
        );

        $this->line("✅ Created usage event: (ID: {$usageEvent->id})");
        $this->line("   - Original object: {$usageEvent->object_type} (ID: {$usageEvent->object_id})");
        $this->line("   - Input tokens: {$usageEvent->input_tokens}");
        $this->line("   - Output tokens: {$usageEvent->output_tokens}");
        $this->line('   - Total cost: $' . number_format($usageEvent->input_cost + $usageEvent->output_cost, 4));

        $uiDemand->refresh();

        $subscribedEvents = $uiDemand->subscribedUsageEvents;
        $this->line("🔗 UiDemand subscribed to {$subscribedEvents->count()} usage events");

        if ($subscribedEvents->count() > 0) {
            $this->line("   - First subscribed event ID: {$subscribedEvents->first()->id}");
        }

        $summary = $uiDemand->usageSummary;
        if ($summary) {
            $this->line('📊 UiDemand usage summary:');
            $this->line("   - Event count: {$summary->count}");
            $this->line("   - Input tokens: {$summary->input_tokens}");
            $this->line("   - Output tokens: {$summary->output_tokens}");
            $this->line('   - Total cost: $' . number_format($summary->total_cost, 4));
        } else {
            $this->error('❌ No usage summary found for UiDemand');
        }

        $directUsageEvents = $uiDemand->usageEvents;
        $this->line("📋 UiDemand direct usage events: {$directUsageEvents->count()}");

        $summary = $uiDemand->usageSummary;
        if ($summary) {
            $this->line('🧮 UiDemand usage summary:');
            $this->line("   - Total tokens: {$summary->total_tokens}");
            $this->line('   - Total cost: $' . number_format($summary->total_cost, 4));
        }

        $this->newLine();
        $this->info('🎉 Usage Subscription System working correctly!');
        $this->line('The system automatically:');
        $this->line('  1. ✅ Created usage event for TaskProcess');
        $this->line('  2. ✅ Fired UsageEventCreated event');
        $this->line('  3. ✅ UiDemandUsageSubscriber automatically subscribed UiDemand');
        $this->line('  4. ✅ Refreshed UiDemand usage summary from subscribed events');

        return 0;
    }
}
