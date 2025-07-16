<?php

namespace App\Console\Commands;

use App\Models\Task\Artifact;
use App\Services\Task\ClassificationDeduplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Global team helper function for CLI context
if (!function_exists('team')) {
    function team() {
        return app('team');
    }
}

class TestClassificationDeduplicationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:classification-deduplication
                           {--task-run= : Task run ID to test deduplication on}
                           {--create-test-data : Create test data for demonstration}
                           {--model= : Model to use for deduplication agent (defaults to config)}';

    /**
     * The console command description.
     */
    protected $description = 'Test classification deduplication with real AI agent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Set up team context (required for agent operations)
        $team = \App\Models\Team\Team::first();
        if (!$team) {
            $this->error('No team found. Please create a team first.');
            return 1;
        }
        
        // Set team context
        app()->bind('team', fn() => $team);
        $this->info("Using team: {$team->name} (ID: {$team->id})");
        
        $taskRunId = $this->option('task-run');
        $createTestData = $this->option('create-test-data');
        $model = $this->option('model');

        // Override model in config if specified
        if ($model) {
            config(['ai.classification_deduplication.model' => $model]);
            $this->info("Using model: $model");
        } else {
            $configModel = config('ai.classification_deduplication.model');
            $this->info("Using configured model: $configModel");
        }

        if ($createTestData) {
            $artifacts = $this->createTestData();
        } elseif ($taskRunId) {
            $artifacts = $this->getArtifactsFromTaskRun($taskRunId);
        } else {
            $this->error('Please specify either --task-run=ID or --create-test-data');
            return 1;
        }

        if ($artifacts->isEmpty()) {
            $this->error('No artifacts found with classification metadata');
            return 1;
        }

        $this->info("Found {$artifacts->count()} artifacts with classification metadata");
        $this->newLine();

        // Show original classifications
        $this->info('=== ORIGINAL CLASSIFICATIONS ===');
        foreach ($artifacts as $index => $artifact) {
            $this->info("Artifact " . ($index + 1) . ":");
            $this->line(json_encode($artifact->meta['classification'], JSON_PRETTY_PRINT));
            $this->newLine();
        }

        // Run deduplication
        $this->info('=== RUNNING DEDUPLICATION ===');
        $service = app(ClassificationDeduplicationService::class);
        
        try {
            $service->deduplicateClassificationLabels($artifacts);
            $this->info('✅ Deduplication completed successfully');
        } catch (\Exception $e) {
            $this->error('❌ Deduplication failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();

        // Show updated classifications
        $this->info('=== UPDATED CLASSIFICATIONS ===');
        foreach ($artifacts as $index => $artifact) {
            $artifact->refresh();
            $this->info("Artifact " . ($index + 1) . ":");
            $this->line(json_encode($artifact->meta['classification'], JSON_PRETTY_PRINT));
            $this->newLine();
        }

        return 0;
    }

    /**
     * Get artifacts from a specific task run
     */
    protected function getArtifactsFromTaskRun(string $taskRunId): \Illuminate\Support\Collection
    {
        return DB::table('artifacts')
            ->join('artifact_task_run_outputs', 'artifacts.id', '=', 'artifact_task_run_outputs.artifact_id')
            ->where('artifact_task_run_outputs.task_run_id', $taskRunId)
            ->whereNotNull('artifacts.meta->classification')
            ->select('artifacts.*')
            ->get()
            ->map(function ($row) {
                return Artifact::find($row->id);
            })
            ->filter();
    }

    /**
     * Create test data for demonstration
     */
    protected function createTestData(): \Illuminate\Support\Collection
    {
        $this->info('Creating test data...');

        $testData = [
            [
                'classification' => [
                    'provider' => 'HEALTHCARE PROVIDER',
                    'category' => 'Primary Care',
                    'professional' => [
                        'name' => 'Dr. John Smith',
                        'role' => 'Primary',
                    ],
                    'date' => '2024-01-15',
                    'active' => true,
                    'score' => 95.5,
                ],
            ],
            [
                'classification' => [
                    'provider' => 'healthcare provider',
                    'category' => 'primary care',
                    'professional' => [
                        'name' => 'Dr. John Smith',
                        'role' => 'Main',
                    ],
                    'date' => '2024-01-15',
                    'active' => true,
                    'score' => 92.3,
                ],
            ],
            [
                'classification' => [
                    'provider' => 'Healthcare Provider',
                    'category' => 'PRIMARY CARE',
                    'professional' => [
                        'name' => 'Dr. Johnny Smith',
                        'role' => 'Primary',
                    ],
                    'date' => '01/15/2024',
                    'active' => false,
                    'score' => 88.7,
                ],
            ],
            [
                'classification' => [
                    'food_categories' => 'Dairy, milk, cream, eggs, meat, vegetables',
                    'provider' => 'MEDICAL CENTER',
                    'tags' => ['urgent', 'follow-up', 'priority'],
                ],
            ],
        ];

        $artifacts = collect();
        foreach ($testData as $index => $data) {
            $artifact = new Artifact([
                'name' => 'Test Artifact ' . ($index + 1),
                'meta' => $data,
            ]);
            $artifact->save();
            $artifacts->push($artifact);
        }

        return $artifacts;
    }
}