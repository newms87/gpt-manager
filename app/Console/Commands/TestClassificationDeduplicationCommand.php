<?php

namespace App\Console\Commands;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Services\Task\ClassificationDeduplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

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
    protected $description = 'Test data normalization/deduplication with real AI agent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $taskRunId      = $this->option('task-run');
        $createTestData = $this->option('create-test-data');
        $model          = $this->option('model');

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
        foreach($artifacts as $index => $artifact) {
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
        } catch(\Exception $e) {
            $this->error('❌ Deduplication failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());

            return 1;
        }

        $this->newLine();

        // Show updated classifications
        $this->info('=== UPDATED CLASSIFICATIONS ===');
        foreach($artifacts as $index => $artifact) {
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
    protected function getArtifactsFromTaskRun(string $taskRunId): Collection
    {
        $taskRun = TaskRun::findOrFail($taskRunId);

        return $taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();
    }

    /**
     * Create test data for demonstration
     */
    protected function createTestData(): Collection
    {
        $this->info('Creating test data...');

        $testData = [
            [
                'classification' => [
                    'company'  => 'APPLE',
                    'category' => 'Technology',
                    'type'     => 'consumer electronics',
                    'location' => '123 Main St',
                    'status'   => 'active',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'Apple Inc',
                    'category' => 'technology',
                    'type'     => 'Consumer Electronics',
                    'location' => '123 main street',
                    'status'   => 'ACTIVE',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'apple inc.',
                    'category' => 'TECHNOLOGY',
                    'type'     => 'Electronics',
                    'location' => '123 Main Street',
                    'status'   => 'Active',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'Google',
                    'category' => 'Technology',
                    'type'     => 'Software',
                    'location' => '456 Tech Ave',
                    'status'   => 'operational',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'GOOGLE',
                    'category' => 'tech',
                    'type'     => 'software',
                    'location' => '456 tech ave',
                    'status'   => 'OPERATIONAL',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'Google LLC',
                    'category' => 'TECH',
                    'type'     => 'Software Development',
                    'location' => '456 Tech Avenue',
                    'status'   => 'Operational',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'Microsoft',
                    'category' => 'Technology',
                    'type'     => 'Software',
                    'location' => '789 Innovation Dr',
                    'status'   => 'running',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'MICROSOFT',
                    'category' => 'technology',
                    'type'     => 'software',
                    'location' => '789 innovation dr',
                    'status'   => 'RUNNING',
                ],
            ],
            [
                'classification' => [
                    'company'  => 'microsoft corp',
                    'category' => 'TECHNOLOGY',
                    'type'     => 'Software Development',
                    'location' => '789 Innovation Drive',
                    'status'   => 'Running',
                ],
            ],
        ];

        $artifacts = collect();
        foreach($testData as $index => $data) {
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
