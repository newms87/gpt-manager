<?php

namespace Tests\Unit\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\Task\ClassificationDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class ClassificationDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClassificationDeduplicationService $service;
    protected Team                               $team;
    protected User                               $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user);
        $this->user->currentTeam = $this->team;
        $this->actingAs($this->user);

        // Configure TestAI
        Config::set('ai.models.test-model', [
            'api'      => TestAiApi::class,
            'name'     => 'Test Model',
            'context'  => 4096,
            'input'    => 0,
            'output'   => 0,
            'features' => [
                'temperature' => true,
            ],
        ]);

        // Configure the deduplication agent to use TestAI
        Config::set('ai.classification_deduplication', [
            'agent_name' => 'Test Data Normalization Agent',
            'model'      => 'test-model',
        ]);
    }

    #[Test]
    public function it_creates_classification_deduplication_agent_if_not_exists()
    {
        // Create test artifacts
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['provider' => 'Test Provider']]]),
        ]);

        // Create service and trigger agent creation
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationProperty($artifacts, 'provider');

        // Assert agent was created
        $agent = Agent::where('name', 'Test Data Normalization Agent')
            ->where('model', 'test-model')
            ->first();

        $this->assertNotNull($agent);
        $this->assertEquals('Test Data Normalization Agent', $agent->name);
        $this->assertEquals('test-model', $agent->model);
        $this->assertEquals(0, $agent->api_options['temperature']);
    }

    #[Test]
    public function it_extracts_classification_property_labels_from_artifacts()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'Apple Inc',
                        'location' => 'Cupertino',
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'APPLE',
                        'location' => 'California',
                        'category' => 'Tech',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        // Test company property extraction
        $companyLabels = $method->invoke($service, $artifacts, 'company');
        $this->assertContains('Apple Inc', $companyLabels);
        $this->assertContains('APPLE', $companyLabels);
        $this->assertNotContains('Cupertino', $companyLabels);
        $this->assertNotContains('Technology', $companyLabels);

        // Test location property extraction
        $locationLabels = $method->invoke($service, $artifacts, 'location');
        $this->assertContains('Cupertino', $locationLabels);
        $this->assertContains('California', $locationLabels);
        $this->assertNotContains('Apple Inc', $locationLabels);
    }

    #[Test]
    public function it_deduplicates_specific_classification_property()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'Apple Inc',
                        'location' => 'Cupertino',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationProperty($artifacts, 'company');

        // TestAI will return "Test AI Response Content" - so no normalization will occur
        $artifact = $artifacts->first()->fresh();
        $this->assertEquals('Apple Inc', $artifact->meta['classification']['company']);
        $this->assertEquals('Cupertino', $artifact->meta['classification']['location']);
    }

    #[Test]
    public function it_creates_deduplication_processes_for_task_run()
    {
        $taskRun = TaskRun::factory()->create();
        
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'Apple Inc',
                        'location' => 'Cupertino',
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'  => 'Google',
                        'location' => 'Mountain View',
                        'category' => 'Technology',
                    ],
                ],
            ]),
        ]);

        // Associate artifacts as output artifacts
        foreach ($artifacts as $artifact) {
            $taskRun->outputArtifacts()->attach($artifact->id);
        }

        $service = app(ClassificationDeduplicationService::class);
        $service->createDeduplicationProcessesForTaskRun($taskRun);

        // Should have created 3 TaskProcesses (company, location, category)
        $processes = $taskRun->taskProcesses()->get();
        $this->assertCount(3, $processes);

        // Check that each process has the correct meta
        $processProperties = $processes->pluck('meta.classification_property')->toArray();
        $this->assertContains('company', $processProperties);
        $this->assertContains('location', $processProperties);
        $this->assertContains('category', $processProperties);

        // Verify processes were created with correct names
        foreach ($processes as $process) {
            $this->assertStringContainsString('Classification Deduplication:', $process->name);
            $this->assertArrayHasKey('classification_property', $process->meta);
        }
    }

    #[Test]
    public function it_extracts_classification_properties_from_single_artifact()
    {
        $artifact = Artifact::factory()->create([
            'meta' => [
                'classification' => [
                    'company'  => 'Apple',
                    'location' => 'Cupertino',
                    'category' => 'Technology',
                ],
            ],
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationProperties');
        $method->setAccessible(true);

        $properties = $method->invoke($service, $artifact);

        $this->assertCount(3, $properties);
        $this->assertContains('company', $properties);
        $this->assertContains('location', $properties);
        $this->assertContains('category', $properties);
    }

    #[Test]
    public function it_handles_empty_classification_metadata()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => []]),
            Artifact::factory()->create(['meta' => ['other_data' => 'value']]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Should not throw exception
        $service->deduplicateClassificationProperty($artifacts, 'company');

        // Artifacts should remain unchanged
        foreach($artifacts as $artifact) {
            $this->assertArrayNotHasKey('classification', $artifact->fresh()->meta);
        }
    }

    #[Test]
    public function it_handles_missing_property_gracefully()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => 'Apple',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Should not throw exception when property doesn't exist
        $service->deduplicateClassificationProperty($artifacts, 'nonexistent');

        // Original data should remain unchanged
        $artifact = $artifacts->first()->fresh();
        $this->assertEquals('Apple', $artifact->meta['classification']['company']);
    }

    #[Test]
    public function it_handles_array_property_values()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'tags' => ['programming', 'frontend', 'react'],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction for array property
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'tags');

        $this->assertContains('programming', $labels);
        $this->assertContains('frontend', $labels);
        $this->assertContains('react', $labels);
    }

    #[Test]
    public function it_handles_object_property_values()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'contact' => [
                            'name' => 'John Smith',
                            'role' => 'Manager',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction for object property
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'contact');

        // Should extract name from object
        $this->assertContains('John Smith', $labels);
        // Should not extract role (only name/id are extracted from objects)
        $this->assertNotContains('Manager', $labels);
    }

    #[Test]
    public function it_skips_artifacts_without_classification_property()
    {
        $taskRun = TaskRun::factory()->create();
        
        $artifacts = collect([
            Artifact::factory()->create(['meta' => []]),
            Artifact::factory()->create(['meta' => ['other' => 'data']]),
        ]);

        foreach ($artifacts as $artifact) {
            $taskRun->outputArtifacts()->attach($artifact->id);
        }

        $service = app(ClassificationDeduplicationService::class);
        $service->createDeduplicationProcessesForTaskRun($taskRun);

        // Should not create any processes since no artifacts have classification
        $processes = $taskRun->taskProcesses()->get();
        $this->assertCount(0, $processes);
    }

    #[Test]
    public function it_deduplicates_object_properties_with_name_field()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => [
                            'name' => 'Apple',
                            'type' => 'Technology'
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => [
                            'name' => 'Apple, Inc',
                            'type' => 'Technology'
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => [
                            'name' => 'APPLE INC.',
                            'type' => 'Technology'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationProperty($artifacts, 'company');

        // TestAI will return "Test AI Response Content" - no actual normalization
        // But we can verify the object structure is preserved
        foreach ($artifacts as $artifact) {
            $artifact->refresh();
            $this->assertArrayHasKey('company', $artifact->meta['classification']);
            $this->assertArrayHasKey('name', $artifact->meta['classification']['company']);
            $this->assertArrayHasKey('type', $artifact->meta['classification']['company']);
            $this->assertEquals('Technology', $artifact->meta['classification']['company']['type']);
        }
    }

    #[Test]
    public function it_deduplicates_object_properties_with_id_field()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => [
                            'id' => 'provider-123',
                            'category' => 'Healthcare'
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => [
                            'id' => 'PROVIDER-123',
                            'category' => 'Healthcare'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationProperty($artifacts, 'provider');

        // Verify object structure is preserved
        foreach ($artifacts as $artifact) {
            $artifact->refresh();
            $this->assertArrayHasKey('provider', $artifact->meta['classification']);
            $this->assertArrayHasKey('id', $artifact->meta['classification']['provider']);
            $this->assertArrayHasKey('category', $artifact->meta['classification']['provider']);
            $this->assertEquals('Healthcare', $artifact->meta['classification']['provider']['category']);
        }
    }

    #[Test]
    public function it_extracts_object_name_fields_for_deduplication()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => [
                            'name' => 'Apple Inc',
                            'sector' => 'Technology'
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => [
                            'name' => 'Google LLC',
                            'sector' => 'Technology'  
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'company');

        $this->assertContains('Apple Inc', $labels);
        $this->assertContains('Google LLC', $labels);
        $this->assertNotContains('Technology', $labels); // Should not extract non-name/id fields
    }

    #[Test]
    public function it_extracts_object_id_fields_for_deduplication()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'user' => [
                            'id' => 'user-123',
                            'role' => 'admin'
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'user' => [
                            'id' => 'user-456',
                            'role' => 'user'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'user');

        $this->assertContains('user-123', $labels);
        $this->assertContains('user-456', $labels);
        $this->assertNotContains('admin', $labels); // Should not extract non-name/id fields
        $this->assertNotContains('user', $labels); // Should not extract non-name/id fields
    }

    #[Test]
    public function it_prefers_id_over_name_when_both_exist()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'entity' => [
                            'id' => 'entity-123',
                            'name' => 'Test Entity',
                            'description' => 'A test entity'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'entity');

        $this->assertContains('entity-123', $labels);
        $this->assertNotContains('Test Entity', $labels); // Should prefer id over name
    }

    #[Test]
    public function it_handles_mixed_string_and_object_properties()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => 'Simple Provider',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => [
                            'name' => 'Complex Provider',
                            'type' => 'Healthcare'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationProperty($artifacts, 'provider');

        // Verify both string and object values are preserved
        $artifact1 = $artifacts->first()->fresh();
        $artifact2 = $artifacts->last()->fresh();

        $this->assertEquals('Simple Provider', $artifact1->meta['classification']['provider']);
        $this->assertIsArray($artifact2->meta['classification']['provider']);
        $this->assertEquals('Complex Provider', $artifact2->meta['classification']['provider']['name']);
    }

    #[Test]
    public function it_handles_array_of_objects_for_deduplication()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'tags' => [
                            ['name' => 'frontend', 'category' => 'technology'],
                            ['name' => 'backend', 'category' => 'technology']
                        ],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'tags' => [
                            ['name' => 'Frontend', 'category' => 'technology'],
                            ['name' => 'database', 'category' => 'technology']
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'tags');

        $this->assertContains('frontend', $labels);
        $this->assertContains('Frontend', $labels);
        $this->assertContains('backend', $labels);
        $this->assertContains('database', $labels);
        $this->assertNotContains('technology', $labels); // Should not extract non-name/id fields
    }

    #[Test]
    public function it_skips_objects_without_name_or_id_fields()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'metadata' => [
                            'created_at' => '2024-01-01',
                            'version' => '1.0',
                            'status' => 'active'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationPropertyLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts, 'metadata');

        // Should not extract any labels since object has no name/id
        $this->assertEmpty($labels);
    }

    #[Test]
    public function it_handles_missing_mappings_for_object_properties_gracefully()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => [
                            'name' => 'CNCC Chiropractic Natural Care Center',
                            'type' => 'Healthcare'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method with incomplete mappings
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('updatePropertyValue');
        $method->setAccessible(true);

        // Simulate AI response that doesn't include mapping for our value
        $incompleteMappings = [
            'Other Provider' => 'Normalized Provider'
            // Missing mapping for 'CNCC Chiropractic Natural Care Center'
        ];

        $originalValue = [
            'name' => 'CNCC Chiropractic Natural Care Center',
            'type' => 'Healthcare'
        ];

        // Should not throw exception when mapping is missing
        $result = $method->invoke($service, $originalValue, $incompleteMappings);

        // Should return original value unchanged when no mapping exists
        $this->assertEquals($originalValue, $result);
        $this->assertEquals('CNCC Chiropractic Natural Care Center', $result['name']);
        $this->assertEquals('Healthcare', $result['type']);
    }

    #[Test]
    public function it_reproduces_logging_error_with_applied_mappings()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => [
                            'name' => 'CNCC Chiropractic Natural Care Center',
                            'type' => 'Healthcare'
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method with mapping that exists
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('updatePropertyValue');
        $method->setAccessible(true);

        // Simulate AI response that includes mapping for our value
        $mappingsWithCorrectKey = [
            'CNCC Chiropractic Natural Care Center' => 'Natural Care Center'
        ];

        $originalValue = [
            'name' => 'CNCC Chiropractic Natural Care Center',
            'type' => 'Healthcare'
        ];

        // Should successfully update value and not throw exception
        $result = $method->invoke($service, $originalValue, $mappingsWithCorrectKey);

        // Should return updated value
        $this->assertEquals('Natural Care Center', $result['name']);
        $this->assertEquals('Healthcare', $result['type']);
    }

    #[Test]
    public function it_handles_missing_mappings_for_string_properties_gracefully()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => 'CNCC Chiropractic Natural Care Center',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method with incomplete mappings
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('updatePropertyValue');
        $method->setAccessible(true);

        // Simulate AI response that doesn't include mapping for our value
        $incompleteMappings = [
            'Other Provider' => 'Normalized Provider'
            // Missing mapping for 'CNCC Chiropractic Natural Care Center'
        ];

        $originalValue = 'CNCC Chiropractic Natural Care Center';

        // Should not throw exception when mapping is missing
        $result = $method->invoke($service, $originalValue, $incompleteMappings);

        // Should return original value unchanged when no mapping exists
        $this->assertEquals($originalValue, $result);
    }
}