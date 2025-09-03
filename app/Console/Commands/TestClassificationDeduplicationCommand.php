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
                           {--property= : Specific classification property to deduplicate (e.g., company, location)}
                           {--create-test-data : Create test data for demonstration}
                           {--create-patient-data : Create patient test data with escaped slashes}
                           {--create-zoo-data : Create zoo animal test data}
                           {--model= : Model to use for deduplication agent (defaults to config)}';

    /**
     * The console command description.
     */
    protected $description = 'Test property-specific classification deduplication with real AI agent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $taskRunId         = $this->option('task-run');
        $property          = $this->option('property');
        $createTestData    = $this->option('create-test-data');
        $createPatientData = $this->option('create-patient-data');
        $createZooData     = $this->option('create-zoo-data');
        $model             = $this->option('model');

        if (!$property) {
            $this->error('Please specify a classification property to deduplicate using --property=PROPERTY_NAME');

            return 1;
        }
        
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
        } elseif ($createPatientData) {
            $artifacts = $this->createPatientTestData();
        } elseif ($createZooData) {
            $artifacts = $this->createZooTestData();
        } elseif ($taskRunId) {
            $artifacts = $this->getArtifactsFromTaskRun($taskRunId);
        } else {
            $this->error('Please specify either --task-run=ID, --create-test-data, --create-patient-data, or --create-zoo-data');

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
            $this->line(json_encode($artifact->meta['classification'][$property], JSON_PRETTY_PRINT));
            $this->newLine();
        }

        $service = app(ClassificationDeduplicationService::class);

        // If property is specified, deduplicate only that property
        $this->info("=== RUNNING DEDUPLICATION FOR PROPERTY: {$property} ===");

        try {
            $service->deduplicateClassificationProperty($artifacts, $property);
            $this->info("✅ Property '{$property}' deduplication completed successfully");
        } catch(\Exception $e) {
            $this->error("❌ Property '{$property}' deduplication failed: " . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());

            return 1;
        }

        $this->newLine();

        // Show updated classifications
        $this->info('=== UPDATED CLASSIFICATIONS ===');
        foreach($artifacts as $index => $artifact) {
            $artifact->refresh();
            $this->info("Artifact " . ($index + 1) . ":");
            $this->line(json_encode($artifact->meta['classification'][$property] ?? '', JSON_PRETTY_PRINT));
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

    /**
     * Create patient test data with problematic escaped forward slashes
     * This tests the exact data patterns that were causing API timeouts
     */
    protected function createPatientTestData(): Collection
    {
        $this->info('Creating patient test data with escaped forward slashes...');

        $testData = [
            [
                'classification' => [
                    'patient' => 'Renese Antoine; DOB: 1964-11-15; MRN: CT10793',
                    'provider' => 'Dr. Smith',
                    'insurance' => 'Aetna',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Renese Antoine; DOB: 1964-11-15',
                    'provider' => 'Doctor Smith',
                    'insurance' => 'aetna',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Renese Antoine; DOB: 1964-11-15; MRN: CT10793; Gender: Female',
                    'provider' => 'DR. SMITH',
                    'insurance' => 'AETNA',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Name: Renese Antoine; DOB: 1964-11-15; MRN: CT10793',
                    'provider' => 'Dr Smith',
                    'insurance' => 'Aetna Inc',
                ],
            ],
            [
                'classification' => [
                    // This contains the problematic escaped forward slashes
                    'patient' => 'Name: Renese Antoine; MRN: CT10793; DOB: 11\\/15\\/1964',
                    'provider' => 'Dr. Smith MD',
                    'insurance' => 'Aetna Insurance',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Renese Antoine; DOB 1964-11-15; MRN CT10793',
                    'provider' => 'Smith, Dr.',
                    'insurance' => 'Aetna Corp',
                ],
            ],
            [
                'classification' => [
                    // More problematic escaped slashes
                    'patient' => 'Renese Antoine; DOB: 11\\/15\\/1964; MRN: CT10793',
                    'provider' => 'Dr. John Smith',
                    'insurance' => 'Aetna Healthcare',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Renese Antoine; MRN: CT10793; DOB: 1964-11-15',
                    'provider' => 'John Smith, MD',
                    'insurance' => 'Aetna Health',
                ],
            ],
            [
                'classification' => [
                    // Even more problematic patterns
                    'patient' => 'Renese Antoine, DOB 11\\/15\\/1964, MRN CT10793',
                    'provider' => 'Dr John Smith',
                    'insurance' => 'AETNA HEALTH',
                ],
            ],
            [
                'classification' => [
                    'patient' => 'Renese Antoine, DOB: 1964-11-15, MRN: CT10793',
                    'provider' => 'Smith MD',
                    'insurance' => 'aetna health insurance',
                ],
            ],
        ];

        $artifacts = collect();
        foreach($testData as $index => $data) {
            $artifact = new Artifact([
                'name' => 'Patient Test Artifact ' . ($index + 1),
                'meta' => $data,
            ]);
            $artifact->save();
            $artifacts->push($artifact);
        }

        return $artifacts;
    }

    /**
     * Create zoo animal test data to test generalization on completely different domain
     */
    protected function createZooTestData(): Collection
    {
        $this->info('Creating zoo animal test data...');

        $testData = [
            [
                'classification' => [
                    'animal_name' => 'African Elephant',
                    'habitat' => 'Savanna',
                    'diet' => 'Herbivore',
                    'conservation_status' => 'Endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'AFRICAN ELEPHANT',
                    'habitat' => 'savanna',
                    'diet' => 'herbivore',
                    'conservation_status' => 'endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'African Elephant (Loxodonta africana)',
                    'habitat' => 'African Savanna',
                    'diet' => 'Plant Eater',
                    'conservation_status' => 'Critically Endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'Loxodonta africana',
                    'habitat' => 'SAVANNA',
                    'diet' => 'HERBIVORE',
                    'conservation_status' => 'ENDANGERED',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'Siberian Tiger',
                    'habitat' => 'Taiga Forest',
                    'diet' => 'Carnivore',
                    'conservation_status' => 'Endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'SIBERIAN TIGER',
                    'habitat' => 'taiga forest',
                    'diet' => 'carnivore',
                    'conservation_status' => 'endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'Siberian Tiger (Panthera tigris altaica)',
                    'habitat' => 'Russian Taiga',
                    'diet' => 'Meat Eater',
                    'conservation_status' => 'Critically Endangered',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'Panthera tigris altaica',
                    'habitat' => 'TAIGA',
                    'diet' => 'CARNIVORE',
                    'conservation_status' => 'ENDANGERED',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'Giant Panda',
                    'habitat' => 'Bamboo Forest',
                    'diet' => 'Herbivore',
                    'conservation_status' => 'Vulnerable',
                ],
            ],
            [
                'classification' => [
                    'animal_name' => 'GIANT PANDA',
                    'habitat' => 'bamboo forest',
                    'diet' => 'herbivore',
                    'conservation_status' => 'vulnerable',
                ],
            ],
        ];

        $artifacts = collect();
        foreach($testData as $index => $data) {
            $artifact = new Artifact([
                'name' => 'Zoo Animal Test Artifact ' . ($index + 1),
                'meta' => $data,
            ]);
            $artifact->save();
            $artifacts->push($artifact);
        }

        return $artifacts;
    }
}
