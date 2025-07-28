<?php

namespace Tests\Unit\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\Task\ClassificationVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class ClassificationVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClassificationVerificationService $service;
    protected Team                              $team;
    protected User                              $user;

    /**
     * Mock the AI response for verification
     */
    protected function mockVerificationResponse(array $corrections): void
    {
        $response = ['corrections' => []];

        foreach ($corrections as $artifactId => $correction) {
            $response['corrections'][] = [
                'artifact_id' => $artifactId,
                'corrected_value' => $correction['value'],
                'reason' => $correction['reason'],
            ];
        }

        TestAiCompletionResponse::setMockResponse(json_encode($response));
    }

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

        // Configure the verification agent to use TestAI
        Config::set('ai.classification_verification', [
            'agent_name' => 'Test Classification Verification Agent',
            'model'      => 'test-model',
        ]);
    }

    #[Test]
    public function it_creates_classification_verification_agent_if_not_exists()
    {
        // Create test artifacts with discrepancies to trigger agent creation
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['provider' => 'Test Provider']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['provider' => 'Different Provider']]]),
        ]);

        // Mock verification response to avoid AI call
        $this->mockVerificationResponse([]);

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'provider');

        // Assert agent was created
        $agent = Agent::where('name', 'Test Classification Verification Agent')
            ->where('model', 'test-model')
            ->first();

        $this->assertNotNull($agent);
        $this->assertEquals('Test Classification Verification Agent', $agent->name);
        $this->assertEquals('test-model', $agent->model);
        $this->assertEquals(0, $agent->api_options['temperature']);
    }

    #[Test]
    public function it_builds_verification_groups_with_context_window()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Google']]]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should create groups where values differ from adjacent artifacts
        $this->assertGreaterThan(0, count($groups));

        // Verify group structure
        foreach ($groups as $group) {
            $this->assertArrayHasKey('focus_artifact_id', $group);
            $this->assertArrayHasKey('focus_position', $group);
            $this->assertArrayHasKey('context', $group);
            $this->assertIsArray($group['context']);
        }
    }

    #[Test]
    public function it_identifies_discrepancies_in_adjacent_artifacts()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]), // Different from adjacent
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should create a group for the middle artifact that differs
        $this->assertCount(1, $groups);
        $this->assertEquals($artifacts[1]->id, $groups[0]['focus_artifact_id']);
        $this->assertEquals(1, $groups[0]['focus_position']);
    }

    #[Test]
    public function it_skips_artifacts_without_discrepancies()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should not create any groups since all values are the same
        $this->assertCount(0, $groups);
    }

    #[Test]
    public function it_includes_context_window_previous_2_current_next_1()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]), // Focus - outlier since neighbors agree
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Find the group for the focus artifact (index 2)
        $focusGroup = null;
        foreach ($groups as $group) {
            if ($group['focus_artifact_id'] === $artifacts[2]->id) {
                $focusGroup = $group;
                break;
            }
        }

        $this->assertNotNull($focusGroup);

        // Should have 4 items in context: previous 2, current, next 1
        $this->assertCount(4, $focusGroup['context']);

        // Verify positions
        $positions = array_column($focusGroup['context'], 'position');
        $this->assertContains('previous', $positions);
        $this->assertContains('current', $positions);
        $this->assertContains('next', $positions);

        // Count position types
        $positionCounts = array_count_values($positions);
        $this->assertEquals(2, $positionCounts['previous']);
        $this->assertEquals(1, $positionCounts['current']);
        $this->assertEquals(1, $positionCounts['next']);
    }

    #[Test]
    public function it_applies_verification_corrections_to_artifacts()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]),
        ]);

        // Mock verification response with correction
        $this->mockVerificationResponse([
            $artifacts[1]->id => [
                'value' => 'Apple Inc',
                'reason' => 'Inconsistent casing, should match adjacent artifacts',
            ],
        ]);

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'company');

        // Verify correction was applied
        $artifact1 = $artifacts[0]->fresh();
        $artifact2 = $artifacts[1]->fresh();

        $this->assertEquals('Apple Inc', $artifact1->meta['classification']['company']);
        $this->assertEquals('Apple Inc', $artifact2->meta['classification']['company']);
    }

    #[Test]
    public function it_handles_empty_corrections_response()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]),
        ]);

        // Mock empty verification response
        TestAiCompletionResponse::setMockResponse(json_encode(['corrections' => []]));

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'company');

        // Verify no changes were made
        $artifact1 = $artifacts[0]->fresh();
        $artifact2 = $artifacts[1]->fresh();

        $this->assertEquals('Apple Inc', $artifact1->meta['classification']['company']);
        $this->assertEquals('APPLE', $artifact2->meta['classification']['company']);
    }

    #[Test]
    public function it_creates_verification_processes_for_task_run_with_verify_config()
    {
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_config' => [
                'verify' => ['company', 'location'],
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $artifacts = collect([
            Artifact::factory()->create([
                'position' => 1,
                'meta' => [
                    'classification' => [
                        'company' => 'Apple Inc',
                        'location' => 'Cupertino',
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'position' => 2,
                'meta' => [
                    'classification' => [
                        'company' => 'Google Inc', // Different company (outlier)
                        'location' => 'Mountain View', // Different location (outlier)
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'position' => 3,
                'meta' => [
                    'classification' => [
                        'company' => 'Apple Inc', // Back to Apple
                        'location' => 'Cupertino', // Back to Cupertino
                        'category' => 'Technology',
                    ],
                ],
            ]),
        ]);

        // Associate artifact as output
        foreach ($artifacts as $artifact) {
            $taskRun->outputArtifacts()->attach($artifact->id);
        }

        $service = app(ClassificationVerificationService::class);
        $service->createVerificationProcessesForTaskRun($taskRun);

        // Should create 2 verification processes (company, location) - category not in verify config
        $processes = $taskRun->taskProcesses()->get();
        $this->assertCount(2, $processes);

        $processProperties = $processes->pluck('meta.classification_verification_property')->toArray();
        $this->assertContains('company', $processProperties);
        $this->assertContains('location', $processProperties);
        $this->assertNotContains('category', $processProperties);

        // Verify process names
        foreach ($processes as $process) {
            $this->assertStringContainsString('Classification Verification:', $process->name);
            $this->assertArrayHasKey('classification_verification_property', $process->meta);
        }
    }

    #[Test]
    public function it_skips_verification_processes_when_no_verify_config()
    {
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_config' => [], // No verify config
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'company' => 'Apple Inc',
                    ],
                ],
            ]),
        ]);

        foreach ($artifacts as $artifact) {
            $taskRun->outputArtifacts()->attach($artifact->id);
        }

        $service = app(ClassificationVerificationService::class);
        $service->createVerificationProcessesForTaskRun($taskRun);

        // Should not create any processes
        $processes = $taskRun->taskProcesses()->get();
        $this->assertCount(0, $processes);
    }

    #[Test]
    public function it_handles_object_property_values_in_verification()
    {
        $artifacts = collect([
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => ['name' => 'Apple Medical', 'type' => 'Healthcare'],
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'meta' => [
                    'classification' => [
                        'provider' => ['name' => 'apple medical', 'type' => 'Healthcare'],
                    ],
                ],
            ]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test property value extraction
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getPropertyValue');
        $method->setAccessible(true);

        $value1 = $method->invoke($service, $artifacts[0], 'provider');
        $value2 = $method->invoke($service, $artifacts[1], 'provider');

        $this->assertEquals('Apple Medical', $value1);
        $this->assertEquals('apple medical', $value2);
    }

    #[Test]
    public function it_handles_artifacts_with_missing_classification_property()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple']]]),
            Artifact::factory()->create(['meta' => ['other' => 'data']]), // No classification
            Artifact::factory()->create(['meta' => ['classification' => ['location' => 'Cupertino']]]), // No company
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should not create groups for artifacts without the property
        $this->assertCount(0, $groups);
    }

    #[Test]
    public function it_handles_edge_case_at_beginning_of_artifact_list()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]), // Focus (different from next)
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should create group for first artifact
        $this->assertCount(1, $groups);
        $this->assertEquals($artifacts[0]->id, $groups[0]['focus_artifact_id']);

        // Context should only include current and next (no previous available)
        $this->assertCount(2, $groups[0]['context']);
        $positions = array_column($groups[0]['context'], 'position');
        $this->assertContains('current', $positions);
        $this->assertContains('next', $positions);
        $this->assertNotContains('previous', $positions);
    }

    #[Test]
    public function it_handles_edge_case_at_end_of_artifact_list()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]), // Focus (different from previous)
        ]);

        $service = app(ClassificationVerificationService::class);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildVerificationGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $artifacts, 'company');

        // Should create group for last artifact
        $this->assertCount(1, $groups);
        $this->assertEquals($artifacts[2]->id, $groups[0]['focus_artifact_id']);

        // Context should include previous 2 and current (no next available)
        $this->assertCount(3, $groups[0]['context']);
        $positions = array_column($groups[0]['context'], 'position');
        $this->assertContains('current', $positions);
        $this->assertContains('previous', $positions);
        $this->assertNotContains('next', $positions);

        // Should have 2 previous artifacts
        $positionCounts = array_count_values($positions);
        $this->assertEquals(2, $positionCounts['previous']);
        $this->assertEquals(1, $positionCounts['current']);
    }

    #[Test]
    public function it_skips_correction_when_artifact_already_has_correct_value()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        // Mock verification response trying to "correct" to the same value
        $this->mockVerificationResponse([
            $artifacts[0]->id => [
                'value' => 'Apple Inc', // Same as current value
                'reason' => 'Already correct',
            ],
        ]);

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'company');

        // Verify no database update occurred (value stays the same)
        $artifact = $artifacts[0]->fresh();
        $this->assertEquals('Apple Inc', $artifact->meta['classification']['company']);
    }

    #[Test]
    public function it_handles_invalid_verification_response_format()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'APPLE']]]),
        ]);

        // Mock invalid verification response (missing required fields)
        TestAiCompletionResponse::setMockResponse(json_encode([
            'corrections' => [
                ['artifact_id' => $artifacts[1]->id], // Missing corrected_value and reason
            ],
        ]));

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'company');

        // Verify no changes were made due to invalid format
        $artifact1 = $artifacts[0]->fresh();
        $artifact2 = $artifacts[1]->fresh();

        $this->assertEquals('Apple Inc', $artifact1->meta['classification']['company']);
        $this->assertEquals('APPLE', $artifact2->meta['classification']['company']);
    }

    #[Test]
    public function it_handles_verification_for_nonexistent_artifact_id()
    {
        $artifacts = collect([
            Artifact::factory()->create(['meta' => ['classification' => ['company' => 'Apple Inc']]]),
        ]);

        // Mock verification response with nonexistent artifact ID
        $this->mockVerificationResponse([
            99999 => [ // Nonexistent artifact ID
                'value' => 'Apple Inc',
                'reason' => 'Correction for nonexistent artifact',
            ],
        ]);

        $service = app(ClassificationVerificationService::class);
        $service->verifyClassificationProperty($artifacts, 'company');

        // Should not throw exception and original artifact should remain unchanged
        $artifact = $artifacts[0]->fresh();
        $this->assertEquals('Apple Inc', $artifact->meta['classification']['company']);
    }

    #[Test]
    public function it_only_verifies_properties_matching_verify_config()
    {
        $taskDefinition = TaskDefinition::factory()->create([
            'task_runner_config' => [
                'verify' => ['company'], // Only verify company, not location
            ],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $artifacts = collect([
            Artifact::factory()->create([
                'position' => 1,
                'meta' => [
                    'classification' => [
                        'company' => 'Apple Inc',
                        'location' => 'Cupertino',
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'position' => 2,
                'meta' => [
                    'classification' => [
                        'company' => 'Google Inc', // Different company (outlier to test)
                        'location' => 'Mountain View', // Different location (but not in verify config)
                        'category' => 'Technology',
                    ],
                ],
            ]),
            Artifact::factory()->create([
                'position' => 3,
                'meta' => [
                    'classification' => [
                        'company' => 'Apple Inc', // Back to Apple
                        'location' => 'Cupertino', // Back to Cupertino
                        'category' => 'Technology',
                    ],
                ],
            ]),
        ]);

        foreach ($artifacts as $artifact) {
            $taskRun->outputArtifacts()->attach($artifact->id);
        }

        $service = app(ClassificationVerificationService::class);
        $service->createVerificationProcessesForTaskRun($taskRun);

        // Should only create verification process for company
        $processes = $taskRun->taskProcesses()->get();
        $this->assertCount(1, $processes);

        $process = $processes->first();
        $this->assertEquals('company', $process->meta['classification_verification_property']);
        $this->assertStringContainsString('Classification Verification: company', $process->name);
    }
}