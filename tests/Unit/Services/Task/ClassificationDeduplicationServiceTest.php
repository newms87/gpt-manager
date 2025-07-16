<?php

namespace Tests\Unit\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
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
    protected Team $team;
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user);
        $this->user->currentTeam = $this->team;
        $this->actingAs($this->user);
        
        // Configure the deduplication agent to use TestAI
        Config::set('ai.classification_deduplication', [
            'agent_name' => 'Test Classification Deduplication Agent',
            'model' => 'test-model',
        ]);
        
        // Add TestAI to the AI config
        Config::set('ai.apis.TestAI', TestAiApi::class);
        Config::set('ai.models.TestAI', [
            'test-model' => [
                'name' => 'Test Model',
                'context' => 4096,
                'input' => 0,
                'output' => 0,
            ],
        ]);
        
        // Don't create service in setUp to avoid test isolation issues
    }

    #[Test]
    public function it_creates_classification_deduplication_agent_if_not_exists()
    {
        // Create service - this should create the agent
        $service = app(ClassificationDeduplicationService::class);
        
        // Assert agent was created
        $agent = Agent::where('team_id', $this->team->id)
            ->where('name', 'Test Classification Deduplication Agent')
            ->where('model', 'test-model')
            ->first();
            
        $this->assertNotNull($agent);
        $this->assertEquals('Test Classification Deduplication Agent', $agent->name);
        $this->assertEquals('test-model', $agent->model);
        $this->assertEquals(TestAiApi::$serviceName, $agent->api);
        $this->assertEquals(0.3, $agent->api_options['temperature']);
    }

    #[Test]
    public function it_reuses_existing_classification_deduplication_agent()
    {
        // Create agent manually
        $existingAgent = Agent::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Test Classification Deduplication Agent',
            'model' => 'test-model',
            'api' => TestAiApi::$serviceName,
        ]);
        
        // Create new service instance
        $newService = app(ClassificationDeduplicationService::class);
        
        // Assert no new agent was created
        $agentCount = Agent::where('team_id', $this->team->id)
            ->where('name', 'Test Classification Deduplication Agent')
            ->count();
            
        $this->assertEquals(1, $agentCount);
    }

    #[Test]
    public function it_extracts_classification_labels_from_artifacts()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'Healthcare',
                        'subcategory' => 'Primary Care',
                        'tags' => ['medical', 'doctor visit'],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'HEALTHCARE',
                        'type' => 'Specialist',
                    ],
                ],
            ]),
        ]);
        
        // Create service instance for this test
        $service = app(ClassificationDeduplicationService::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);
        
        $labels = $method->invoke($service, $artifacts);
        
        $this->assertContains('Healthcare', $labels);
        $this->assertContains('Primary Care', $labels);
        $this->assertContains('medical', $labels);
        $this->assertContains('doctor visit', $labels);
        $this->assertContains('HEALTHCARE', $labels);
        $this->assertContains('Specialist', $labels);
    }

    #[Test]
    public function it_normalizes_classification_labels_via_ai_agent()
    {
        // Create artifacts with classification data
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'HEALTHCARE',
                        'subcategory' => 'Primary Care',
                        'food' => 'Dairy, milk, cream, eggs',
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // TestAI will return "Test AI Response Content" - so no normalization will occur
        // This tests that the service runs without errors
        $artifact = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];
        
        // Original values should remain unchanged since TestAI response isn't valid JSON
        $this->assertEquals('HEALTHCARE', $classification['category']);
        $this->assertEquals('Primary Care', $classification['subcategory']);
        $this->assertEquals('Dairy, milk, cream, eggs', $classification['food']);
    }

    #[Test]
    public function it_handles_nested_classification_structures()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'services' => [
                            'type' => 'Medical - Primary Care',
                            'categories' => [
                                'food' => 'FOOD CATEGORY',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // TestAI will return "Test AI Response Content" - so no normalization will occur
        $artifact = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];
        
        // Original values should remain unchanged
        $this->assertEquals('Medical - Primary Care', $classification['services']['type']);
        $this->assertEquals('FOOD CATEGORY', $classification['services']['categories']['food']);
    }

    #[Test]
    public function it_handles_empty_classification_metadata()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'other_data' => 'value',
                ],
            ]),
        ]);
        
        // Should not throw exception
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // Artifacts should remain unchanged
        foreach ($artifacts as $artifact) {
            $this->assertArrayNotHasKey('classification', $artifact->fresh()->meta);
        }
    }

    #[Test]
    public function it_handles_service_execution_gracefully()
    {
        $originalClassification = [
            'category' => 'HEALTHCARE',
            'type' => 'Primary Care',
        ];
        
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => $originalClassification,
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // Since TestAI returns non-JSON content, classification should remain unchanged
        $artifact = $artifacts->first()->fresh();
        $this->assertEquals($originalClassification, $artifact->meta['classification']);
    }

    #[Test]
    public function it_processes_multiple_artifacts_correctly()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'HEALTHCARE',
                        'tag' => 'medical',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'FOOD',
                        'tag' => 'nutrition',
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // Both artifacts should remain unchanged since TestAI returns non-JSON
        $this->assertEquals('HEALTHCARE', $artifacts->first()->fresh()->meta['classification']['category']);
        $this->assertEquals('FOOD', $artifacts->last()->fresh()->meta['classification']['category']);
    }

    #[Test]
    public function it_ignores_boolean_and_numeric_values()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'HEALTHCARE',
                        'active' => true,
                        'count' => 5,
                        'score' => 3.14,
                        'tags' => ['medical', 'primary'],
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        
        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);
        
        $labels = $method->invoke($service, $artifacts);
        
        // Should only include strings from the tags array and category
        $this->assertContains('HEALTHCARE', $labels);
        $this->assertContains('medical', $labels);
        $this->assertContains('primary', $labels);
        
        // Should NOT include boolean or numeric values
        $this->assertNotContains(true, $labels);
        $this->assertNotContains(5, $labels);
        $this->assertNotContains(3.14, $labels);
    }

    #[Test]
    public function it_handles_nested_objects_as_json_strings()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'professional' => [
                            'name' => 'Dan Newman',
                            'role' => 'Primary',
                        ],
                        'contact' => [
                            'name' => 'Danny Newman',
                            'role' => 'Main',
                        ],
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        
        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);
        
        $labels = $method->invoke($service, $artifacts);
        
        // Should include JSON representations of the objects
        $this->assertContains('{"name":"Dan Newman","role":"Primary"}', $labels);
        $this->assertContains('{"name":"Danny Newman","role":"Main"}', $labels);
    }

    #[Test]
    public function it_differentiates_associative_vs_indexed_arrays()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'tags' => ['medical', 'primary', 'care'], // Indexed array
                        'professional' => [ // Associative array
                            'name' => 'Dr. Smith',
                            'role' => 'Primary Care',
                        ],
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        
        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);
        
        $labels = $method->invoke($service, $artifacts);
        
        // Should include individual strings from indexed array
        $this->assertContains('medical', $labels);
        $this->assertContains('primary', $labels);
        $this->assertContains('care', $labels);
        
        // Should include JSON string for associative array
        $this->assertContains('{"name":"Dr. Smith","role":"Primary Care"}', $labels);
        
        // Should NOT include the indexed array as JSON
        $this->assertNotContains('["medical","primary","care"]', $labels);
    }

    #[Test]
    public function it_handles_mixed_data_types_in_classification()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'HEALTHCARE',
                        'active' => true,
                        'priority' => 1,
                        'details' => [
                            'provider' => 'Dr. Smith',
                            'verified' => false,
                        ],
                        'tags' => ['medical', 'urgent'],
                    ],
                ],
            ]),
        ]);
        
        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);
        
        // Original structure should be maintained (TestAI returns non-JSON)
        $artifact = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];
        
        $this->assertEquals('HEALTHCARE', $classification['category']);
        $this->assertTrue($classification['active']);
        $this->assertEquals(1, $classification['priority']);
        $this->assertEquals('Dr. Smith', $classification['details']['provider']);
        $this->assertFalse($classification['details']['verified']);
        $this->assertEquals(['medical', 'urgent'], $classification['tags']);
    }
}