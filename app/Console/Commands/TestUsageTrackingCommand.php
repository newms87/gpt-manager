<?php

namespace App\Console\Commands;

use App\Models\Task\TaskProcess;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Console\Command;

class TestUsageTrackingCommand extends Command
{
    protected $signature = 'test:usage-tracking';
    protected $description = 'Test the new usage tracking system';

    public function handle()
    {
        $this->info('Testing Usage Tracking System...');

        // Get a task process to test with
        $taskProcess = TaskProcess::first();
        
        if (!$taskProcess) {
            $this->error('No task processes found in database. Please create some test data first.');
            return 1;
        }

        $this->info("Testing with TaskProcess ID: {$taskProcess->id}");

        // Test usage tracking service
        $usageService = app(UsageTrackingService::class);

        // Test API usage recording
        $this->info('Recording test API usage...');
        $usageEvent = $usageService->recordApiUsage(
            $taskProcess,
            'imagetotext',
            'ocr_conversion',
            [
                'request_count' => 1,
                'data_volume' => 1024,
                'metadata' => ['test' => true]
            ],
            1500 // 1.5 seconds
        );

        $this->info("Created UsageEvent ID: {$usageEvent->id}");

        // Test AI usage recording
        $this->info('Recording test AI usage...');
        $aiUsageEvent = $usageService->recordAiUsage(
            $taskProcess,
            'openai',
            'gpt-4o',
            [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
            2000
        );

        $this->info("Created AI UsageEvent ID: {$aiUsageEvent->id}");

        // Test usage summary refresh
        $this->info('Refreshing usage summary...');
        $taskProcess->refreshUsageSummary();

        // Display results
        $summary = $taskProcess->usageSummary;
        if ($summary) {
            $this->info('Usage Summary:');
            $this->line("- Total Cost: $" . number_format($summary->total_cost, 6));
            $this->line("- Input Cost: $" . number_format($summary->input_cost, 6));
            $this->line("- Output Cost: $" . number_format($summary->output_cost, 6));
            $this->line("- Events: {$summary->count}");
            $this->line("- Total Tokens: {$summary->input_tokens} + {$summary->output_tokens} = " . ($summary->input_tokens + $summary->output_tokens));
            $this->line("- Run Time: {$summary->run_time_ms}ms");
            $this->line("- Requests: {$summary->request_count}");
            $this->line("- Data Volume: {$summary->data_volume} bytes");
        } else {
            $this->error('No usage summary found');
        }

        // Test the usage attribute from HasUsageTracking trait
        $this->info('Testing usage attribute...');
        $usage = $taskProcess->usage;
        if ($usage) {
            $this->info('Usage attribute works correctly:');
            $this->line("- Total Cost: $" . number_format($usage['total_cost'], 6));
            $this->line("- Total Tokens: {$usage['total_tokens']}");
        }

        $this->info('Usage tracking test completed successfully!');
        return 0;
    }
}
