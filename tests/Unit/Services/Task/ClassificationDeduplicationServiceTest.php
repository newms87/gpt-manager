<?php

namespace Tests\Unit\Services\Task;

use App\Models\Agent\Agent;
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
        $service->deduplicateClassificationLabels($artifacts);

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
    public function it_extracts_classification_labels_from_artifacts()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category'    => 'Technology',
                        'subcategory' => 'Software',
                        'tags'        => ['programming', 'development'],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'TECHNOLOGY',
                        'type'     => 'Software',
                    ],
                ],
            ]),
        ]);

        // Create service instance for this test
        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        $this->assertContains('Technology', $labels);
        $this->assertContains('Software', $labels);
        $this->assertContains('programming', $labels);
        $this->assertContains('development', $labels);
        $this->assertContains('TECHNOLOGY', $labels);
        $this->assertContains('Software', $labels);
    }

    #[Test]
    public function it_normalizes_classification_labels_via_ai_agent()
    {
        // Create artifacts with classification data
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category'    => 'TECHNOLOGY',
                        'subcategory' => 'Software Development',
                        'tags'        => 'JavaScript, React, Node.js',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);

        // TestAI will return "Test AI Response Content" - so no normalization will occur
        // This tests that the service runs without errors
        $artifact       = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];

        // Original values should remain unchanged since TestAI response isn't valid JSON
        $this->assertEquals('TECHNOLOGY', $classification['category']);
        $this->assertEquals('Software Development', $classification['subcategory']);
        $this->assertEquals('JavaScript, React, Node.js', $classification['tags']);
    }

    #[Test]
    public function it_handles_nested_classification_structures()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'services' => [
                            'type'       => 'Web - Frontend Development',
                            'categories' => [
                                'framework' => 'REACT FRAMEWORK',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);

        // TestAI will return "Test AI Response Content" - so no normalization will occur
        $artifact       = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];

        // Original values should remain unchanged
        $this->assertEquals('Web - Frontend Development', $classification['services']['type']);
        $this->assertEquals('REACT FRAMEWORK', $classification['services']['categories']['framework']);
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
        foreach($artifacts as $artifact) {
            $this->assertArrayNotHasKey('classification', $artifact->fresh()->meta);
        }
    }

    #[Test]
    public function it_handles_service_execution_gracefully()
    {
        $originalClassification = [
            'category' => 'TECHNOLOGY',
            'type'     => 'Software Development',
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
                        'category' => 'TECHNOLOGY',
                        'tag'      => 'programming',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'EDUCATION',
                        'tag'      => 'learning',
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);

        // Both artifacts should remain unchanged since TestAI returns non-JSON
        $this->assertEquals('TECHNOLOGY', $artifacts->first()->fresh()->meta['classification']['category']);
        $this->assertEquals('EDUCATION', $artifacts->last()->fresh()->meta['classification']['category']);
    }

    #[Test]
    public function it_ignores_boolean_and_numeric_values()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'TECHNOLOGY',
                        'active'   => true,
                        'count'    => 5,
                        'score'    => 3.14,
                        'tags'     => ['programming', 'frontend'],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should only include strings from the tags array and category
        $this->assertContains('TECHNOLOGY', $labels);
        $this->assertContains('programming', $labels);
        $this->assertContains('frontend', $labels);

        // Should NOT include boolean or numeric values
        $this->assertNotContains(true, $labels);
        $this->assertNotContains(5, $labels);
        $this->assertNotContains(3.14, $labels);
    }

    #[Test]
    public function it_handles_nested_objects_with_name_property()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'developer' => [
                            'name' => 'John Smith',
                            'role' => 'Lead',
                        ],
                        'contact'   => [
                            'name' => 'Johnny Smith',
                            'role' => 'Primary',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should include only the name values from objects with name property
        $this->assertContains('John Smith', $labels);
        $this->assertContains('Johnny Smith', $labels);
    }

    #[Test]
    public function it_handles_nested_objects_with_id_property()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'developer' => [
                            'id'   => '123',
                            'name' => 'John Smith',
                            'role' => 'Lead',
                        ],
                        'contact'   => [
                            'id'   => '456',
                            'name' => 'Johnny Smith',
                            'role' => 'Primary',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should include only the id values from objects with id property (id takes precedence over name)
        $this->assertContains('123', $labels);
        $this->assertContains('456', $labels);
        // Should NOT include the name values when id is present
        $this->assertNotContains('John Smith', $labels);
        $this->assertNotContains('Johnny Smith', $labels);
    }

    #[Test]
    public function it_ignores_objects_without_id_or_name()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'metadata' => [
                            'type'  => 'config',
                            'value' => 'setting',
                        ],
                        'contact'  => [
                            'name' => 'John Smith',
                            'role' => 'Lead',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should include only the name value from object with name property
        $this->assertContains('John Smith', $labels);

        // Should NOT include values from objects without id or name
        $this->assertNotContains('config', $labels);
        $this->assertNotContains('setting', $labels);
    }

    #[Test]
    public function it_ignores_objects_with_null_id_or_name()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'nullName'  => [
                            'name' => null,
                            'role' => 'Lead',
                        ],
                        'nullId'    => [
                            'id'   => null,
                            'role' => 'Support',
                        ],
                        'validName' => [
                            'name' => 'Valid Name',
                            'role' => 'Primary',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should include only the name value from object with valid name
        $this->assertContains('Valid Name', $labels);

        // Should NOT include any values from objects with null name or id
        $this->assertNotContains('Lead', $labels);
        $this->assertNotContains('Support', $labels);
    }

    #[Test]
    public function it_differentiates_associative_vs_indexed_arrays()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'tags'      => ['programming', 'frontend', 'react'], // Indexed array
                        'developer' => [ // Associative array
                                         'name' => 'Jane Doe',
                                         'role' => 'Senior Developer',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Should include individual strings from indexed array
        $this->assertContains('programming', $labels);
        $this->assertContains('frontend', $labels);
        $this->assertContains('react', $labels);

        // Should include only the name value from associative array (has name property)
        $this->assertContains('Jane Doe', $labels);

        // Should NOT include the indexed array as JSON
        $this->assertNotContains('["programming","frontend","react"]', $labels);
    }

    #[Test]
    public function it_handles_mixed_data_types_in_classification()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'category' => 'TECHNOLOGY',
                        'active'   => true,
                        'priority' => 1,
                        'details'  => [
                            'developer' => 'Jane Doe',
                            'verified'  => false,
                        ],
                        'tags'     => ['programming', 'react'],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);
        $service->deduplicateClassificationLabels($artifacts);

        // Original structure should be maintained (TestAI returns non-JSON)
        $artifact       = $artifacts->first()->fresh();
        $classification = $artifact->meta['classification'];

        $this->assertEquals('TECHNOLOGY', $classification['category']);
        $this->assertTrue($classification['active']);
        $this->assertEquals(1, $classification['priority']);
        $this->assertEquals('Jane Doe', $classification['details']['developer']);
        $this->assertFalse($classification['details']['verified']);
        $this->assertEquals(['programming', 'react'], $classification['tags']);
    }

    #[Test]
    public function it_tests_shouldDeduplicateObject_method()
    {
        $service    = app(ClassificationDeduplicationService::class);
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('shouldDeduplicateObject');
        $method->setAccessible(true);

        // Test object with id
        $objectWithId = ['id' => '123', 'name' => 'Test', 'other' => 'data'];
        $this->assertTrue($method->invoke($service, $objectWithId));

        // Test object with name only
        $objectWithName = ['name' => 'Test Name', 'other' => 'data'];
        $this->assertTrue($method->invoke($service, $objectWithName));

        // Test object with both id and name
        $objectWithBoth = ['id' => '456', 'name' => 'Test', 'other' => 'data'];
        $this->assertTrue($method->invoke($service, $objectWithBoth));

        // Test object without id or name
        $objectWithoutIdOrName = ['type' => 'config', 'value' => 'setting'];
        $this->assertFalse($method->invoke($service, $objectWithoutIdOrName));

        // Test object with null id
        $objectWithNullId = ['id' => null, 'name' => 'Test', 'other' => 'data'];
        $this->assertTrue($method->invoke($service, $objectWithNullId)); // Has valid name

        // Test object with null name
        $objectWithNullName = ['name' => null, 'other' => 'data'];
        $this->assertFalse($method->invoke($service, $objectWithNullName));

        // Test object with both null
        $objectWithBothNull = ['id' => null, 'name' => null, 'other' => 'data'];
        $this->assertFalse($method->invoke($service, $objectWithBothNull));

        // Test empty object
        $emptyObject = [];
        $this->assertFalse($method->invoke($service, $emptyObject));
    }

    #[Test]
    public function it_demonstrates_full_object_deduplication_workflow()
    {
        // This test demonstrates the expected behavior of the deduplication service
        // when handling objects with id/name properties

        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company'    => 'APPLE',
                        'developers' => [
                            [
                                'id'   => 'dev-123',
                                'name' => 'John Smith',
                                'role' => 'Lead Developer',
                            ],
                            [
                                'id'   => 'dev-456',
                                'name' => 'Jane Doe',
                                'role' => 'Senior Developer',
                            ],
                        ],
                        'offices'    => [
                            [
                                'name'    => 'Main Office',
                                'address' => '123 main street',
                            ],
                            [
                                'name'    => 'Branch Office',
                                'address' => '456 Oak Ave',
                            ],
                        ],
                        'metadata'   => [
                            'type'    => 'config',
                            'version' => '1.0',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationDeduplicationService::class);

        // Use reflection to test label extraction
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('extractClassificationLabels');
        $method->setAccessible(true);

        $labels = $method->invoke($service, $artifacts);

        // Verify that we extract:
        // - Simple string values (company)
        $this->assertContains('APPLE', $labels);

        // - ID values from objects with id property (developers have id, so we extract id)
        $this->assertContains('dev-123', $labels);
        $this->assertContains('dev-456', $labels);

        // - Name values from objects without id property (offices have name, so we extract name)
        $this->assertContains('Main Office', $labels);
        $this->assertContains('Branch Office', $labels);

        // Verify that we DON'T extract:
        // - Names when ID is present (John Smith, Jane Doe should not be in labels)
        $this->assertNotContains('John Smith', $labels);
        $this->assertNotContains('Jane Doe', $labels);

        // - Other properties from objects that have id/name (we only extract the id/name)
        $this->assertNotContains('Lead Developer', $labels);
        $this->assertNotContains('Senior Developer', $labels);
        $this->assertNotContains('123 main street', $labels);
        $this->assertNotContains('456 Oak Ave', $labels);

        // - Values from objects without id/name properties
        $this->assertNotContains('config', $labels);
        $this->assertNotContains('1.0', $labels);

        // The total should be exactly 5 values
        $this->assertCount(5, $labels);
    }
}
