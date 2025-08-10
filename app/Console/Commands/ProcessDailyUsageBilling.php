<?php

namespace App\Console\Commands;

use App\Services\Billing\UsageBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDailyUsageBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process-daily-usage 
                            {--team= : Process billing for a specific team ID}
                            {--dry-run : Run without actually charging teams}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily usage billing for all teams with usage-based subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily usage billing process...');
        
        $teamId = $this->option('team');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no charges will be created');
        }
        
        try {
            $usageBillingService = app(UsageBillingService::class);
            
            if ($teamId) {
                $team = \App\Models\Team\Team::find($teamId);
                
                if (!$team) {
                    $this->error("Team with ID {$teamId} not found");
                    return Command::FAILURE;
                }
                
                $this->info("Processing billing for team: {$team->name}");
                
                if (!$dryRun) {
                    $usageBillingService->processTeamBilling($team);
                } else {
                    $usage = $usageBillingService->calculateDailyUsage($team);
                    $this->table(
                        ['Metric', 'Value'],
                        [
                            ['Date', $usage['date']],
                            ['Event Count', $usage['event_count']],
                            ['Total Tokens', $usage['total_tokens']],
                            ['Total Cost', '$' . number_format($usage['total_cost'], 2)],
                            ['Total Requests', $usage['total_requests']],
                        ]
                    );
                }
            } else {
                $this->info('Processing billing for all teams...');
                
                if (!$dryRun) {
                    $usageBillingService->processDailyBilling();
                } else {
                    $this->info('Dry run mode - skipping actual billing process');
                }
            }
            
            $this->info('Daily usage billing process completed successfully');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process daily usage billing: ' . $e->getMessage());
            Log::error('Daily usage billing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
