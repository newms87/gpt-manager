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
    protected $description = 'Test classification deduplication with real AI agent';

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
                    'provider'  => 'CNCC',
                    'category'  => 'Chiropractic Care',
                    'specialty' => 'Chiropractic',
                    'location'  => '123 Main St',
                    'type'      => 'In-Person Visit',
                    'urgency'   => 'Routine',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'Chiropractic Natural Care Center',
                    'category'  => 'chiropractic care',
                    'specialty' => 'CHIROPRACTIC',
                    'location'  => '123 main street',
                    'type'      => 'in person visit',
                    'urgency'   => 'ROUTINE',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'CNCC Chiropractic Natural Care Center',
                    'category'  => 'CHIROPRACTIC CARE',
                    'specialty' => 'Chiropractor',
                    'location'  => '123 Main Street',
                    'type'      => 'Office Visit',
                    'urgency'   => 'routine',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'cncc',
                    'category'  => 'Chiropractic',
                    'specialty' => 'Chiropractic Medicine',
                    'location'  => '123 MAIN ST',
                    'type'      => 'Visit',
                    'urgency'   => 'Standard',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'NYC General Hospital',
                    'category'  => 'Emergency Medicine',
                    'specialty' => 'Emergency',
                    'location'  => '456 Hospital Way',
                    'urgency'   => 'urgent',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'NYCGH',
                    'category'  => 'emergency medicine',
                    'specialty' => 'Emergency Medicine',
                    'location'  => '456 Hospital Way',
                    'urgency'   => 'Urgent',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'New York City General Hospital',
                    'category'  => 'EMERGENCY',
                    'specialty' => 'ER',
                    'location'  => '456 hospital way',
                    'urgency'   => 'URGENT',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'Dr. Smith Family Practice',
                    'category'  => 'Family Medicine',
                    'specialty' => 'Family Practice',
                    'location'  => '789 Oak Avenue',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'Dr Smith Family Practice',
                    'category'  => 'family medicine',
                    'specialty' => 'Family Med',
                    'location'  => '789 oak ave',
                ],
            ],
            [
                'classification' => [
                    'provider'  => 'DR. SMITH FAMILY PRACTICE',
                    'category'  => 'FAMILY PRACTICE',
                    'specialty' => 'Family Medicine',
                    'location'  => '789 Oak Ave.',
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
