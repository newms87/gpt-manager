<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\DataExtraction\PerObjectPlanningService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class PerObjectPlanningServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected PerObjectPlanningService $service;

    protected TaskDefinition $taskDefinition;

    protected TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->service = app(PerObjectPlanningService::class);

        // Create test task definition with agent
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Agent',
        ]);

        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'name'        => ['type' => 'string', 'title' => 'Name'],
                    'description' => ['type' => 'string', 'title' => 'Description'],
                    'date'        => ['type' => 'string', 'title' => 'Date'],
                ],
            ],
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [
                'group_max_points'   => 10,
                'global_search_mode' => 'intelligent',
            ],
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    #[Test]
    public function createIdentityPlanningProcesses_creates_process_for_each_object_type(): void
    {
        // Given
        $objectTypes = [
            [
                'name'          => 'Demand',
                'path'          => '',
                'level'         => 0,
                'parent_type'   => null,
                'is_array'      => false,
                'simple_fields' => [
                    'name'        => ['title' => 'Name'],
                    'description' => ['title' => 'Description'],
                ],
            ],
            [
                'name'          => 'Care Summary',
                'path'          => 'care_summaries',
                'level'         => 1,
                'parent_type'   => 'Demand',
                'is_array'      => true,
                'simple_fields' => [
                    'name'       => ['title' => 'Name'],
                    'start_date' => ['title' => 'Start Date'],
                ],
            ],
        ];

        // When
        $processes = $this->service->createIdentityPlanningProcesses($this->taskRun, $objectTypes);

        // Then
        $this->assertCount(2, $processes);
        $this->assertEquals('Plan: Identify Demand', $processes[0]->name);
        $this->assertEquals('Plan: Identify Care Summary', $processes[1]->name);
        $this->assertEquals('Plan: Identify', $processes[0]->operation);
        $this->assertTrue($processes[0]->is_ready);

        // Verify meta data
        $this->assertEquals('Demand', $processes[0]->meta['object_type']);
        $this->assertEquals(0, $processes[0]->meta['level']);
        $this->assertEquals('Care Summary', $processes[1]->meta['object_type']);
        $this->assertEquals(1, $processes[1]->meta['level']);

        // Verify database
        $this->assertDatabaseHas('task_processes', [
            'task_run_id' => $this->taskRun->id,
            'name'        => 'Plan: Identify Demand',
            'operation'   => 'Plan: Identify',
        ]);
    }

    #[Test]
    public function storePerObjectPlan_stores_plan_in_task_run_meta(): void
    {
        // Given
        $objectType = 'Demand';
        $planData   = [
            'object_type'    => 'Demand',
            'path'           => '',
            'level'          => 0,
            'identity_group' => [
                'identity_fields' => ['name'],
                'skim_fields'     => ['name', 'description'],
                'search_mode'     => 'skim',
            ],
            'has_remaining_fields' => true,
            'remaining_fields'     => ['date' => ['title' => 'Date']],
        ];

        // When
        $this->service->storePerObjectPlan($this->taskRun, $objectType, $planData);

        // Then
        $this->taskRun->refresh();
        $storedPlans = $this->taskRun->meta['per_object_plans'] ?? [];
        $this->assertArrayHasKey('Demand', $storedPlans);
        $this->assertEquals('Demand', $storedPlans['Demand']['object_type']);
        $this->assertEquals(['name'], $storedPlans['Demand']['identity_group']['identity_fields']);
        $this->assertTrue($storedPlans['Demand']['has_remaining_fields']);
    }

    #[Test]
    public function getPerObjectPlans_retrieves_stored_plans(): void
    {
        // Given
        $planData = [
            'Demand'       => [
                'object_type'    => 'Demand',
                'level'          => 0,
                'identity_group' => ['identity_fields' => ['name']],
            ],
            'Care Summary' => [
                'object_type'    => 'Care Summary',
                'level'          => 1,
                'identity_group' => ['identity_fields' => ['name', 'start_date']],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $planData];
        $this->taskRun->save();

        // When
        $retrievedPlans = $this->service->getPerObjectPlans($this->taskRun);

        // Then
        $this->assertCount(2, $retrievedPlans);
        $this->assertArrayHasKey('Demand', $retrievedPlans);
        $this->assertArrayHasKey('Care Summary', $retrievedPlans);
        $this->assertEquals(['name'], $retrievedPlans['Demand']['identity_group']['identity_fields']);
    }

    #[Test]
    public function getPerObjectPlans_returns_empty_array_when_no_plans(): void
    {
        // When
        $plans = $this->service->getPerObjectPlans($this->taskRun);

        // Then
        $this->assertIsArray($plans);
        $this->assertEmpty($plans);
    }

    #[Test]
    public function createRemainingProcesses_only_creates_for_objects_with_remaining_fields(): void
    {
        // Given
        $perObjectPlans = [
            'Demand'       => [
                'object_type'          => 'Demand',
                'has_remaining_fields' => true,
                'remaining_fields'     => ['date' => ['title' => 'Date']],
            ],
            'Care Summary' => [
                'object_type'          => 'Care Summary',
                'has_remaining_fields' => false,
                'remaining_fields'     => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When
        $processes = $this->service->createRemainingProcesses($this->taskRun);

        // Then
        $this->assertCount(1, $processes);
        $this->assertEquals('Plan: Remaining Demand', $processes[0]->name);
        $this->assertEquals('Plan: Remaining', $processes[0]->operation);
        $this->assertTrue($processes[0]->is_ready);
        $this->assertEquals('Demand', $processes[0]->meta['object_type']);
    }

    #[Test]
    public function createRemainingProcesses_returns_empty_array_when_no_remaining_fields(): void
    {
        // Given
        $perObjectPlans = [
            'Demand' => [
                'object_type'          => 'Demand',
                'has_remaining_fields' => false,
                'remaining_fields'     => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When
        $processes = $this->service->createRemainingProcesses($this->taskRun);

        // Then
        $this->assertEmpty($processes);
    }

    #[Test]
    public function compileFinalPlan_produces_correct_structure(): void
    {
        // Given
        $perObjectPlans = [
            'Demand'       => [
                'object_type'    => 'Demand',
                'path'           => '',
                'level'          => 0,
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name', 'description'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => true,
                'extraction_groups'    => [
                    [
                        'name'        => 'Dates',
                        'fields'      => ['date'],
                        'search_mode' => 'intelligent',
                    ],
                ],
            ],
            'Care Summary' => [
                'object_type'    => 'Care Summary',
                'path'           => 'care_summaries',
                'level'          => 1,
                'identity_group' => [
                    'identity_fields' => ['name', 'start_date'],
                    'skim_fields'     => ['name', 'start_date'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then
        $this->assertArrayHasKey('levels', $plan);
        $this->assertCount(2, $plan['levels']); // 2 levels (0 and 1)

        // Level 0 (Demand)
        $level0 = $plan['levels'][0];
        $this->assertEquals(0, $level0['level']);
        $this->assertCount(1, $level0['identities']);
        $this->assertEquals('Demand', $level0['identities'][0]['object_type']);
        $this->assertEquals(['name'], $level0['identities'][0]['identity_fields']);
        $this->assertEquals(['name', 'description'], $level0['identities'][0]['skim_fields']);

        $this->assertCount(1, $level0['remaining']);
        $this->assertEquals('Dates', $level0['remaining'][0]['name']);
        $this->assertEquals(['date'], $level0['remaining'][0]['fields']);
        $this->assertEquals('intelligent', $level0['remaining'][0]['search_mode']);

        // Level 1 (Care Summary)
        $level1 = $plan['levels'][1];
        $this->assertEquals(1, $level1['level']);
        $this->assertCount(1, $level1['identities']);
        $this->assertEquals('Care Summary', $level1['identities'][0]['object_type']);
        $this->assertEquals(['name', 'start_date'], $level1['identities'][0]['identity_fields']);

        $this->assertEmpty($level1['remaining']); // No remaining groups
    }

    #[Test]
    public function compileFinalPlan_includes_fragment_selectors(): void
    {
        // Given
        $perObjectPlans = [
            'Demand' => [
                'object_type'    => 'Demand',
                'path'           => '',
                'level'          => 0,
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then
        $this->assertArrayHasKey('fragment_selector', $plan['levels'][0]['identities'][0]);
        $fragmentSelector = $plan['levels'][0]['identities'][0]['fragment_selector'];
        $this->assertEquals('object', $fragmentSelector['type']);
        $this->assertArrayHasKey('children', $fragmentSelector);
        $this->assertArrayHasKey('name', $fragmentSelector['children']);
    }

    #[Test]
    public function compileFinalPlan_returns_empty_levels_when_no_plans(): void
    {
        // When
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then
        $this->assertArrayHasKey('levels', $plan);
        $this->assertEmpty($plan['levels']);
    }

    // ==========================================
    // FIELD COVERAGE VALIDATION TESTS
    // ==========================================

    #[Test]
    public function executeRemainingPlanning_validates_complete_field_coverage(): void
    {
        // Given: TaskProcess with remaining fields and per-object plan stored
        $objectType      = 'Demand';
        $remainingFields = [
            'address'      => ['title' => 'Address', 'description' => 'Street address'],
            'city'         => ['title' => 'City'],
            'postal_code'  => ['title' => 'Postal Code'],
        ];

        // Store the per-object plan with identity fields already set
        $this->taskRun->meta = [
            'per_object_plans' => [
                $objectType => [
                    'object_type'    => $objectType,
                    'path'           => '',
                    'level'          => 0,
                    'identity_group' => [
                        'identity_fields' => ['name'],
                        'skim_fields'     => ['name'],
                        'search_mode'     => 'skim',
                    ],
                ],
            ],
        ];
        $this->taskRun->save();

        $taskProcess = $this->taskRun->taskProcesses()->create([
            'operation' => 'Plan: Remaining',
            'meta'      => [
                'object_type'      => $objectType,
                'remaining_fields' => $remainingFields,
            ],
            'is_ready'  => true,
        ]);

        // Mock LLM response that covers ALL fields in first attempt
        $this->mockAgentThreadServiceWithResponse([
            'extraction_groups' => [
                [
                    'name'        => 'Location Details',
                    'fields'      => ['address', 'city', 'postal_code'],
                    'search_mode' => 'exhaustive',
                ],
            ],
        ]);

        // When: Execute remaining planning
        $this->service->executeRemainingPlanning($this->taskRun, $taskProcess);

        // Then: No follow-up prompt sent, all fields in extraction groups
        $this->taskRun->refresh();
        $plan = $this->taskRun->meta['per_object_plans'][$objectType];

        $this->assertArrayHasKey('extraction_groups', $plan);
        $this->assertCount(1, $plan['extraction_groups']);
        $this->assertEquals(['address', 'city', 'postal_code'], $plan['extraction_groups'][0]['fields']);

        // Assert attempt_history shows only 1 attempt
        $taskProcess->refresh();
        $this->assertEquals(1, $taskProcess->meta['total_attempts']);
        $this->assertCount(1, $taskProcess->meta['attempt_history']);
        $this->assertEquals(1, $taskProcess->meta['attempt_history'][0]['attempt']);
        $this->assertEmpty($taskProcess->meta['attempt_history'][0]['missing_fields']);
    }

    #[Test]
    public function executeRemainingPlanning_sends_followup_for_missing_fields(): void
    {
        // Given: TaskProcess with remaining fields
        $objectType      = 'Demand';
        $remainingFields = [
            'address'     => ['title' => 'Address'],
            'city'        => ['title' => 'City'],
            'postal_code' => ['title' => 'Postal Code'],
        ];

        $this->taskRun->meta = [
            'per_object_plans' => [
                $objectType => [
                    'object_type'    => $objectType,
                    'path'           => '',
                    'level'          => 0,
                    'identity_group' => [
                        'identity_fields' => ['name'],
                        'skim_fields'     => ['name'],
                        'search_mode'     => 'skim',
                    ],
                ],
            ],
        ];
        $this->taskRun->save();

        $taskProcess = $this->taskRun->taskProcesses()->create([
            'operation' => 'Plan: Remaining',
            'meta'      => [
                'object_type'      => $objectType,
                'remaining_fields' => $remainingFields,
            ],
        ]);

        // Mock two LLM responses: first missing 'postal_code', second covering it
        $this->mockAgentThreadServiceWithMultipleResponses([
            [
                'extraction_groups' => [
                    [
                        'name'        => 'Location',
                        'fields'      => ['address', 'city'],
                        'search_mode' => 'skim',
                    ],
                ],
            ],
            [
                'extraction_groups' => [
                    [
                        'name'        => 'Postal Info',
                        'fields'      => ['postal_code'],
                        'search_mode' => 'exhaustive',
                    ],
                ],
            ],
        ]);

        // When: Execute remaining planning
        $this->service->executeRemainingPlanning($this->taskRun, $taskProcess);

        // Then: Two attempts recorded
        $taskProcess->refresh();
        $this->assertEquals(2, $taskProcess->meta['total_attempts']);
        $this->assertCount(2, $taskProcess->meta['attempt_history']);

        // First attempt should show missing 'postal_code'
        $this->assertEquals(['postal_code'], $taskProcess->meta['attempt_history'][0]['missing_fields']);

        // Second attempt should show no missing fields
        $this->assertEmpty($taskProcess->meta['attempt_history'][1]['missing_fields']);

        // All fields should be in final extraction groups
        $this->taskRun->refresh();
        $plan        = $this->taskRun->meta['per_object_plans'][$objectType];
        $allFields   = [];
        foreach ($plan['extraction_groups'] as $group) {
            $allFields = array_merge($allFields, $group['fields']);
        }
        $this->assertEqualsCanonicalizing(['address', 'city', 'postal_code'], $allFields);
    }

    #[Test]
    public function executeRemainingPlanning_deduplicates_fields(): void
    {
        // Given: TaskProcess with remaining fields
        $objectType      = 'Demand';
        $remainingFields = [
            'address' => ['title' => 'Address'],
            'city'    => ['title' => 'City'],
        ];

        $this->taskRun->meta = [
            'per_object_plans' => [
                $objectType => [
                    'object_type'    => $objectType,
                    'path'           => '',
                    'level'          => 0,
                    'identity_group' => [
                        'identity_fields' => ['name'],
                        'skim_fields'     => ['name'],
                        'search_mode'     => 'skim',
                    ],
                ],
            ],
        ];
        $this->taskRun->save();

        $taskProcess = $this->taskRun->taskProcesses()->create([
            'operation' => 'Plan: Remaining',
            'meta'      => [
                'object_type'      => $objectType,
                'remaining_fields' => $remainingFields,
            ],
        ]);

        // Mock LLM response with 'city' appearing in multiple groups
        $this->mockAgentThreadServiceWithResponse([
            'extraction_groups' => [
                [
                    'name'        => 'Primary Location',
                    'fields'      => ['address', 'city'],
                    'search_mode' => 'skim',
                ],
                [
                    'name'        => 'Additional Info',
                    'fields'      => ['city'], // Duplicate!
                    'search_mode' => 'exhaustive',
                ],
            ],
        ]);

        // When: Execute remaining planning
        $this->service->executeRemainingPlanning($this->taskRun, $taskProcess);

        // Then: De-duplication keeps first occurrence, removes empty groups
        $this->taskRun->refresh();
        $plan = $this->taskRun->meta['per_object_plans'][$objectType];

        $this->assertCount(1, $plan['extraction_groups'], 'Empty group after deduplication should be removed');
        $this->assertEquals(['address', 'city'], $plan['extraction_groups'][0]['fields']);

        // Assert attempt_history shows duplicate was detected
        $taskProcess->refresh();
        $this->assertEquals(['city'], $taskProcess->meta['attempt_history'][0]['duplicate_fields']);
    }

    #[Test]
    public function executeRemainingPlanning_throws_error_after_max_attempts(): void
    {
        // Given: TaskProcess with remaining fields
        $objectType      = 'Demand';
        $remainingFields = [
            'address'     => ['title' => 'Address'],
            'city'        => ['title' => 'City'],
            'postal_code' => ['title' => 'Postal Code'],
        ];

        $this->taskRun->meta = [
            'per_object_plans' => [
                $objectType => [
                    'object_type'    => $objectType,
                    'path'           => '',
                    'level'          => 0,
                    'identity_group' => [
                        'identity_fields' => ['name'],
                        'skim_fields'     => ['name'],
                        'search_mode'     => 'skim',
                    ],
                ],
            ],
        ];
        $this->taskRun->save();

        $taskProcess = $this->taskRun->taskProcesses()->create([
            'operation' => 'Plan: Remaining',
            'meta'      => [
                'object_type'      => $objectType,
                'remaining_fields' => $remainingFields,
            ],
        ]);

        // Mock 3 LLM responses that always miss 'postal_code'
        $this->mockAgentThreadServiceWithMultipleResponses([
            [
                'extraction_groups' => [
                    ['name' => 'Location', 'fields' => ['address'], 'search_mode' => 'skim'],
                ],
            ],
            [
                'extraction_groups' => [
                    ['name' => 'City Info', 'fields' => ['city'], 'search_mode' => 'skim'],
                ],
            ],
            [
                'extraction_groups' => [
                    ['name' => 'More Location', 'fields' => ['address'], 'search_mode' => 'skim'],
                ],
            ],
        ]);

        // When/Then: Should throw ValidationError after 3 attempts
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('Failed to cover all fields after 3 attempts');
        $this->expectExceptionMessage('postal_code');

        $this->service->executeRemainingPlanning($this->taskRun, $taskProcess);
    }

    #[Test]
    public function executeRemainingPlanning_stores_attempt_history_in_taskprocess_meta(): void
    {
        // Given: TaskProcess with remaining fields
        $objectType      = 'Demand';
        $remainingFields = [
            'address' => ['title' => 'Address'],
            'city'    => ['title' => 'City'],
        ];

        $this->taskRun->meta = [
            'per_object_plans' => [
                $objectType => [
                    'object_type'    => $objectType,
                    'path'           => '',
                    'level'          => 0,
                    'identity_group' => [
                        'identity_fields' => ['name'],
                        'skim_fields'     => ['name'],
                        'search_mode'     => 'skim',
                    ],
                ],
            ],
        ];
        $this->taskRun->save();

        $taskProcess = $this->taskRun->taskProcesses()->create([
            'operation' => 'Plan: Remaining',
            'meta'      => [
                'object_type'      => $objectType,
                'remaining_fields' => $remainingFields,
            ],
        ]);

        // Mock LLM response with partial coverage
        $this->mockAgentThreadServiceWithMultipleResponses([
            [
                'extraction_groups' => [
                    ['name' => 'Location', 'fields' => ['address'], 'search_mode' => 'skim'],
                ],
            ],
            [
                'extraction_groups' => [
                    ['name' => 'City Info', 'fields' => ['city'], 'search_mode' => 'exhaustive'],
                ],
            ],
        ]);

        // When: Execute remaining planning
        $this->service->executeRemainingPlanning($this->taskRun, $taskProcess);

        // Then: TaskProcess.meta contains attempt_history with correct structure
        $taskProcess->refresh();
        $this->assertArrayHasKey('attempt_history', $taskProcess->meta);
        $this->assertArrayHasKey('total_attempts', $taskProcess->meta);
        $this->assertEquals(2, $taskProcess->meta['total_attempts']);

        $attemptHistory = $taskProcess->meta['attempt_history'];
        $this->assertCount(2, $attemptHistory);

        // Verify structure of first attempt
        $attempt1 = $attemptHistory[0];
        $this->assertEquals(1, $attempt1['attempt']);
        $this->assertArrayHasKey('fields_to_group', $attempt1);
        $this->assertArrayHasKey('groups_returned', $attempt1);
        $this->assertArrayHasKey('covered_fields', $attempt1);
        $this->assertArrayHasKey('missing_fields', $attempt1);
        $this->assertArrayHasKey('duplicate_fields', $attempt1);

        // First attempt should show only 'address' covered
        $this->assertEquals(['address'], $attempt1['covered_fields']);
        $this->assertEquals(['city'], $attempt1['missing_fields']);

        // Second attempt should show 'city' covered
        $attempt2 = $attemptHistory[1];
        $this->assertEquals(2, $attempt2['attempt']);
        $this->assertEquals(['city'], $attempt2['covered_fields']);
        $this->assertEmpty($attempt2['missing_fields']);
    }

    #[Test]
    public function validateFieldCoverage_identifies_missing_fields(): void
    {
        // Given: Remaining field keys and extraction groups
        $remainingFieldKeys = ['address', 'city', 'postal_code', 'country'];
        $extractionGroups   = [
            [
                'name'        => 'Primary Location',
                'fields'      => ['address', 'city'],
                'search_mode' => 'skim',
            ],
            [
                'name'        => 'Additional Info',
                'fields'      => ['country'],
                'search_mode' => 'exhaustive',
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('validateFieldCoverage');
        $method->setAccessible(true);

        // When: Validate field coverage
        $result = $method->invokeArgs($this->service, [$remainingFieldKeys, $extractionGroups]);

        // Then: Correctly identifies missing field
        $this->assertArrayHasKey('covered', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertArrayHasKey('duplicates', $result);

        $this->assertEqualsCanonicalizing(['address', 'city', 'country'], $result['covered']);
        $this->assertEquals(['postal_code'], $result['missing']);
        $this->assertEmpty($result['duplicates']);
    }

    #[Test]
    public function validateFieldCoverage_identifies_duplicate_fields(): void
    {
        // Given: Extraction groups with duplicate field
        $remainingFieldKeys = ['address', 'city'];
        $extractionGroups   = [
            [
                'name'        => 'Location 1',
                'fields'      => ['address', 'city'],
                'search_mode' => 'skim',
            ],
            [
                'name'        => 'Location 2',
                'fields'      => ['city', 'address'], // Both duplicates
                'search_mode' => 'exhaustive',
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('validateFieldCoverage');
        $method->setAccessible(true);

        // When: Validate field coverage
        $result = $method->invokeArgs($this->service, [$remainingFieldKeys, $extractionGroups]);

        // Then: Correctly identifies duplicates
        $this->assertEqualsCanonicalizing(['city', 'address'], $result['duplicates']);
        $this->assertEmpty($result['missing']);
    }

    #[Test]
    public function deduplicateFields_keeps_first_occurrence(): void
    {
        // Given: Extraction groups with duplicates
        $extractionGroups = [
            [
                'name'        => 'Primary',
                'fields'      => ['address', 'city', 'postal_code'],
                'search_mode' => 'skim',
            ],
            [
                'name'        => 'Secondary',
                'fields'      => ['city', 'country'], // 'city' is duplicate
                'search_mode' => 'exhaustive',
            ],
            [
                'name'        => 'Tertiary',
                'fields'      => ['postal_code'], // 'postal_code' is duplicate
                'search_mode' => 'skim',
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('deduplicateFields');
        $method->setAccessible(true);

        // When: De-duplicate fields
        $result = $method->invokeArgs($this->service, [$extractionGroups]);

        // Then: Only first occurrence kept, empty groups removed
        $this->assertCount(2, $result);

        // First group unchanged
        $this->assertEquals(['address', 'city', 'postal_code'], $result[0]['fields']);

        // Second group has only 'country' (city removed as duplicate)
        $this->assertEquals(['country'], $result[1]['fields']);

        // Third group removed entirely (all fields were duplicates)
    }

    // ==========================================
    // FRAGMENT SELECTOR TYPE MATCHING TESTS
    // ==========================================

    #[Test]
    public function buildFragmentSelectorFromFields_uses_array_type_for_array_properties(): void
    {
        // Given: A schema where 'provider' has type: array
        $schema = [
            'type'       => 'object',
            'title'      => 'Demand',
            'properties' => [
                'name'     => ['type' => 'string'],
                'provider' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'    => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Update schema definition with this schema
        $this->taskDefinition->schemaDefinition->update(['schema' => $schema]);

        // Store per-object plan for provider
        $perObjectPlans = [
            'Provider' => [
                'object_type'    => 'Provider',
                'path'           => 'provider',
                'level'          => 1,
                'is_array'       => true,
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name', 'address'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When: Compile final plan
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then: Fragment selector should have correct structure for array property
        $fragmentSelector = $plan['levels'][0]['identities'][0]['fragment_selector'];

        // The root wrapper type is 'object' (container for path parts)
        $this->assertEquals('object', $fragmentSelector['type']);

        // The provider path should be type 'array' (from schema type)
        $this->assertArrayHasKey('provider', $fragmentSelector['children']);
        $providerSelector = $fragmentSelector['children']['provider'];
        $this->assertEquals('array', $providerSelector['type']);
    }

    #[Test]
    public function buildFragmentSelectorFromFields_uses_object_type_for_object_properties(): void
    {
        // Given: A schema where 'client' has type: object
        $schema = [
            'type'       => 'object',
            'title'      => 'Demand',
            'properties' => [
                'name'   => ['type' => 'string'],
                'client' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'  => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        // Update schema definition with this schema
        $this->taskDefinition->schemaDefinition->update(['schema' => $schema]);

        // Store per-object plan for client
        $perObjectPlans = [
            'Client' => [
                'object_type'    => 'Client',
                'path'           => 'client',
                'level'          => 1,
                'is_array'       => false,
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name', 'email'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When: Compile final plan
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then: Fragment selector should have type 'object' for client path part
        $fragmentSelector = $plan['levels'][0]['identities'][0]['fragment_selector'];

        // The root wrapper should be type 'object'
        $this->assertEquals('object', $fragmentSelector['type']);

        // The client path should be type 'object' (from schema)
        $this->assertArrayHasKey('client', $fragmentSelector['children']);
        $clientSelector = $fragmentSelector['children']['client'];
        $this->assertEquals('object', $clientSelector['type']);
    }

    #[Test]
    public function buildFragmentSelectorFromFields_handles_nested_array_paths(): void
    {
        // Given: A schema with nested structure provider (array) -> care_summary (object)
        $schema = [
            'type'       => 'object',
            'title'      => 'Demand',
            'properties' => [
                'name'     => ['type' => 'string'],
                'provider' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'         => ['type' => 'string'],
                            'care_summary' => [
                                'type'       => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Update schema definition with this schema
        $this->taskDefinition->schemaDefinition->update(['schema' => $schema]);

        // Store per-object plan for care_summary (nested inside provider)
        $perObjectPlans = [
            'CareSummary' => [
                'object_type'    => 'CareSummary',
                'path'           => 'provider.care_summary',
                'level'          => 2,
                'is_array'       => false,
                'parent_type'    => 'Provider',
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When: Compile final plan
        $plan = $this->service->compileFinalPlan($this->taskRun);

        // Then: Fragment selector should have correct types for nested path
        $fragmentSelector = $plan['levels'][0]['identities'][0]['fragment_selector'];

        // The root wrapper type is 'object' (container for path parts)
        $this->assertEquals('object', $fragmentSelector['type']);

        // The provider path should be type 'array' (from schema: provider is an array)
        $this->assertArrayHasKey('provider', $fragmentSelector['children']);
        $providerSelector = $fragmentSelector['children']['provider'];
        $this->assertEquals('array', $providerSelector['type']);

        // The care_summary has type 'object' (from schema: care_summary is an object within provider items)
        $this->assertArrayHasKey('care_summary', $providerSelector['children']);
        $careSummarySelector = $providerSelector['children']['care_summary'];
        $this->assertEquals('object', $careSummarySelector['type']);
    }

    #[Test]
    public function fragment_selector_validates_against_schema_with_arrays(): void
    {
        // Given: A complete Demand schema with provider array
        $schema = [
            'type'       => 'object',
            'title'      => 'Demand',
            'properties' => [
                'name'     => ['type' => 'string'],
                'provider' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'    => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Update schema definition with this schema
        $this->taskDefinition->schemaDefinition->update(['schema' => $schema]);

        // Store per-object plan for provider
        $perObjectPlans = [
            'Provider' => [
                'object_type'    => 'Provider',
                'path'           => 'provider',
                'level'          => 1,
                'is_array'       => true,
                'identity_group' => [
                    'identity_fields' => ['name'],
                    'skim_fields'     => ['name', 'address'],
                    'search_mode'     => 'skim',
                ],
                'has_remaining_fields' => false,
                'extraction_groups'    => [],
            ],
        ];

        $this->taskRun->meta = ['per_object_plans' => $perObjectPlans];
        $this->taskRun->save();

        // When: Compile final plan and apply fragment selector via JsonSchemaService
        $plan             = $this->service->compileFinalPlan($this->taskRun);
        $fragmentSelector = $plan['levels'][0]['identities'][0]['fragment_selector'];

        // Then: applyFragmentSelector should NOT throw an exception
        $jsonSchemaService = app(\App\Services\JsonSchema\JsonSchemaService::class);
        $filteredSchema    = $jsonSchemaService->applyFragmentSelector($schema, $fragmentSelector);

        // The filtered schema should have the provider property
        $this->assertArrayHasKey('properties', $filteredSchema);
        $this->assertArrayHasKey('provider', $filteredSchema['properties']);
        $this->assertEquals('array', $filteredSchema['properties']['provider']['type']);
    }

    #[Test]
    public function buildPathTypeMap_correctly_maps_array_and_object_types(): void
    {
        // Given: A schema with mixed array and object types
        $schema = [
            'type'       => 'object',
            'properties' => [
                'provider' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'contacts' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('buildPathTypeMap');
        $method->setAccessible(true);

        // When: Build path type map for nested path
        $pathParts = ['provider', 'contacts'];
        $result    = $method->invokeArgs($this->service, [$pathParts, $schema]);

        // Then: Types should be correctly identified from schema
        $this->assertEquals('array', $result['provider']);
        $this->assertEquals('array', $result['contacts']);
    }

    #[Test]
    public function buildPathTypeMap_handles_union_types_with_null(): void
    {
        // Given: A schema with union types (e.g., ['array', 'null'])
        $schema = [
            'type'       => 'object',
            'properties' => [
                'providers' => [
                    'type'  => ['array', 'null'],
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('buildPathTypeMap');
        $method->setAccessible(true);

        // When: Build path type map
        $pathParts = ['providers'];
        $result    = $method->invokeArgs($this->service, [$pathParts, $schema]);

        // Then: Primary type (non-null) should be used
        $this->assertEquals('array', $result['providers']);
    }

    #[Test]
    public function getPrimaryType_returns_first_non_null_type(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('getPrimaryType');
        $method->setAccessible(true);

        // Test: array before null
        $result = $method->invokeArgs($this->service, [['array', 'null']]);
        $this->assertEquals('array', $result);

        // Test: null before object
        $result = $method->invokeArgs($this->service, [['null', 'object']]);
        $this->assertEquals('object', $result);

        // Test: string only
        $result = $method->invokeArgs($this->service, [['string']]);
        $this->assertEquals('string', $result);

        // Test: empty array (defaults to object)
        $result = $method->invokeArgs($this->service, [[]]);
        $this->assertEquals('object', $result);

        // Test: only null (defaults to object)
        $result = $method->invokeArgs($this->service, [['null']]);
        $this->assertEquals('object', $result);
    }

    // ==========================================
    // HELPER METHODS FOR MOCKING
    // ==========================================

    private function mockAgentThreadServiceWithResponse(array $jsonResponse): void
    {
        $this->mockAgentThreadServiceWithMultipleResponses([$jsonResponse]);
    }

    private function mockAgentThreadServiceWithMultipleResponses(array $jsonResponses): void
    {
        $agentThread = \App\Models\Agent\AgentThread::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->taskDefinition->agent_id,
        ]);

        $this->mock(\App\Services\AgentThread\AgentThreadService::class, function ($mock) use ($jsonResponses) {
            $mock->shouldReceive('withResponseFormat')
                ->times(count($jsonResponses))
                ->andReturnSelf();

            foreach ($jsonResponses as $jsonResponse) {
                // Create a partial mock of AgentThreadRun
                $mockThreadRun = $this->mock(\App\Models\Agent\AgentThreadRun::class)->makePartial();
                $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

                // Create a mock message with getJsonContent
                $mockMessage = $this->createMock(\App\Models\Agent\AgentThreadMessage::class);
                $mockMessage->method('getJsonContent')->willReturn($jsonResponse);

                // Set the mock message as property
                $mockThreadRun->lastMessage = $mockMessage;

                $mock->shouldReceive('run')
                    ->once()
                    ->andReturn($mockThreadRun);
            }
        });

        // Mock AgentThreadBuilderService
        $this->mock(\App\Services\AgentThread\AgentThreadBuilderService::class, function ($mock) use ($agentThread) {
            $mockBuilder = $this->createMock(\App\Services\AgentThread\AgentThreadBuilderService::class);
            $mockBuilder->method('named')->willReturnSelf();
            $mockBuilder->method('withSystemMessage')->willReturnSelf();
            $mockBuilder->method('withResponseSchema')->willReturnSelf();
            $mockBuilder->method('build')->willReturn($agentThread);

            $mock->shouldReceive('for')
                ->andReturn($mockBuilder);
        });
    }
}
