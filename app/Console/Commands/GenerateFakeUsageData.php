<?php

namespace App\Console\Commands;

use App\Models\Demand\UiDemand;
use App\Models\Usage\UsageEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateFakeUsageData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'usage:generate-fake {demand_id : The ID of the demand to generate usage data for} {--count=10 : Number of usage events to generate} {--clear : Clear existing usage data first}';

    /**
     * The console command description.
     */
    protected $description = 'Generate fake usage data for a specific UI demand';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $demandId = $this->argument('demand_id');
        $count    = (int)$this->option('count');
        $clear    = $this->option('clear');

        // Find the demand
        $demand = UiDemand::find($demandId);
        if (!$demand) {
            $this->error("UiDemand with ID {$demandId} not found.");

            return 1;
        }

        $this->info("Generating fake usage data for demand: {$demand->name} (ID: {$demand->id})");

        // Clear existing usage data if requested
        if ($clear) {
            $this->warn('Clearing existing usage data...');
            $demand->usageEvents()->delete();
        }

        $this->info("Generating {$count} usage events...");

        $models     = ['gpt-4o', 'gpt-4o-mini', 'claude-3-5-sonnet', 'claude-3-haiku'];
        $eventTypes = ['ai_completion', 'text_processing', 'document_analysis', 'data_extraction'];
        $providers  = ['openai', 'anthropic', 'custom_api'];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $model     = fake()->randomElement($models);
            $eventType = fake()->randomElement($eventTypes);
            $provider  = fake()->randomElement($providers);

            // Different token/cost patterns based on model
            $inputTokens = match (true) {
                str_contains($model, 'gpt-4o-mini')       => fake()->numberBetween(100, 2000),
                str_contains($model, 'gpt-4o')            => fake()->numberBetween(200, 4000),
                str_contains($model, 'claude-3-5-sonnet') => fake()->numberBetween(300, 5000),
                str_contains($model, 'claude-3-haiku')    => fake()->numberBetween(150, 3000),
                default                                   => fake()->numberBetween(100, 1000)
            };

            $outputTokens = match (true) {
                str_contains($model, 'mini') || str_contains($model, 'haiku') => fake()->numberBetween(50, 800),
                default                                                       => fake()->numberBetween(100, 1500)
            };

            // Realistic cost calculation based on token usage
            $inputCost = match (true) {
                str_contains($model, 'gpt-4o-mini')       => $inputTokens * 0.00000015, // $0.15 per 1M tokens
                str_contains($model, 'gpt-4o')            => $inputTokens * 0.0000025, // $2.50 per 1M tokens
                str_contains($model, 'claude-3-5-sonnet') => $inputTokens * 0.000003, // $3.00 per 1M tokens
                str_contains($model, 'claude-3-haiku')    => $inputTokens * 0.00000025, // $0.25 per 1M tokens
                default                                   => $inputTokens * 0.000001
            };

            $outputCost = match (true) {
                str_contains($model, 'gpt-4o-mini')       => $outputTokens * 0.0000006, // $0.60 per 1M tokens
                str_contains($model, 'gpt-4o')            => $outputTokens * 0.00001, // $10.00 per 1M tokens
                str_contains($model, 'claude-3-5-sonnet') => $outputTokens * 0.000015, // $15.00 per 1M tokens
                str_contains($model, 'claude-3-haiku')    => $outputTokens * 0.00000125, // $1.25 per 1M tokens
                default                                   => $outputTokens * 0.000002
            };

            try {
                UsageEvent::create([
                    'team_id'       => $demand->team_id,
                    'user_id'       => $demand->user_id,
                    'object_type'   => UiDemand::class,
                    'object_id'     => (string)$demand->id,
                    'object_id_int' => $demand->id,
                    'event_type'    => $eventType,
                    'api_name'      => $provider,
                    'run_time_ms'   => fake()->numberBetween(500, 8000),
                    'input_tokens'  => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'input_cost'    => round($inputCost, 6),
                    'output_cost'   => round($outputCost, 6),
                    'request_count' => 1,
                    'data_volume'   => fake()->numberBetween(1000, 50000),
                    'metadata'      => [
                        'model'        => $model,
                        'temperature'  => fake()->randomFloat(2, 0, 1),
                        'max_tokens'   => fake()->randomElement([1000, 2000, 4000, 8000]),
                        'generated_at' => now()->toISOString(),
                    ],
                    'created_at'    => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            } catch (\Exception $e) {
                $this->error('Failed to create usage event: ' . $e->getMessage());
                throw $e;
            }

            $bar->advance();
        }

        // Generate the usage summary after all events are created
        $demand->refreshUsageSummary();

        $bar->finish();

        $this->newLine(2);
        $this->info('✅ Fake usage data generated successfully!');

        // Refresh the demand to get updated counts
        $demand->refresh();

        // Display summary
        $totalEvents = $demand->usageEvents()->count();
        $totalCost   = $demand->usageEvents()->sum(DB::raw('input_cost + output_cost'));
        $totalTokens = $demand->usageEvents()->sum(DB::raw('input_tokens + output_tokens'));

        $this->table(['Metric', 'Value'], [
            ['Total Usage Events', number_format($totalEvents)],
            ['Total Cost', '$' . number_format($totalCost, 4)],
            ['Total Tokens', number_format($totalTokens)],
        ]);

        return 0;
    }
}
