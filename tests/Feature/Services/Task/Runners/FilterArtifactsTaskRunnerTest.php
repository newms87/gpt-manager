<?php

namespace Tests\Feature\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\FilterArtifactsTaskRunner;
use Tests\AuthenticatedTestCase;

class FilterArtifactsTaskRunnerTest extends AuthenticatedTestCase
{
    protected TaskDefinition            $taskDefinition;
    protected TaskRun                   $taskRun;
    protected TaskProcess               $taskProcess;
    protected FilterArtifactsTaskRunner $taskRunner;

    public function setUp(): void
    {
        parent::setUp();

        // Create test task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'name'             => 'Test Filter Artifacts Task',
            'task_runner_name' => FilterArtifactsTaskRunner::RUNNER_NAME,
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'name'               => 'Test Filter Run',
        ]);

        // Create task process
        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'activity'    => 'Testing filter artifacts',
        ]);
    }

    /**
     * Test filtering artifacts with AND condition
     */
    public function test_filter_artifacts_with_and_condition()
    {
        // Given
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'This is a test document with important information',
            'json_content' => ['category' => 'report', 'priority' => 'high'],
            'meta'         => ['status' => 'active', 'tags' => ['important', 'report']],
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'This is another document with some details',
            'json_content' => ['category' => 'note', 'priority' => 'medium'],
            'meta'         => ['status' => 'active', 'tags' => ['note']],
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => 'Low priority information',
            'json_content' => ['category' => 'report', 'priority' => 'low'],
            'meta'         => ['status' => 'inactive', 'tags' => ['report']],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with AND condition
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'AND',
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'category' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'report',
                        ],
                        [
                            'type'              => 'condition',
                            'field'             => 'meta',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'status' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'active',
                        ],
                    ],
                ],
            ],
        ]);

        // When
        $this->taskProcess->getRunner()->run();

        // Then
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering artifacts with OR condition
     */
    public function test_filter_artifacts_with_or_condition()
    {
        // Given we have 3 artifacts
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'This is a test document with important information',
            'json_content' => ['category' => 'report', 'priority' => 'high'],
            'meta'         => ['status' => 'active'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'This is another document with some details',
            'json_content' => ['category' => 'note', 'priority' => 'medium'],
            'meta'         => ['status' => 'active'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => 'Low priority information',
            'json_content' => ['category' => 'report', 'priority' => 'low'],
            'meta'         => ['status' => 'inactive'],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with OR condition
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'OR',
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'category' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'report',
                        ],
                        [
                            'type'              => 'condition',
                            'field'             => 'text_content',
                            'fragment_selector' => [
                                'type' => 'string',
                            ],
                            'operator'          => 'contains',
                            'value'             => 'another',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then artifacts 1, 2, and 3 should be in the output (1 and 3 match report category, 2 matches text contains)
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(3, $outputArtifacts);
    }

    /**
     * Test filtering with nested condition groups
     */
    public function test_filter_artifacts_with_nested_conditions()
    {
        // Given we have 4 artifacts
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'This is a high priority report',
            'json_content' => ['category' => 'report', 'priority' => 'high'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'This is a medium priority note',
            'json_content' => ['category' => 'note', 'priority' => 'medium'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => 'This is a low priority report',
            'json_content' => ['category' => 'report', 'priority' => 'low'],
        ]);

        $artifact4 = Artifact::factory()->create([
            'text_content' => 'This is a high priority note',
            'json_content' => ['category' => 'note', 'priority' => 'high'],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id, $artifact4->id]);

        // Set up filter config with nested conditions
        // We want: (category=report AND priority=high) OR (category=note AND priority=medium)
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'OR',
                    'conditions' => [
                        [
                            'type'       => 'condition_group',
                            'operator'   => 'AND',
                            'conditions' => [
                                [
                                    'type'              => 'condition',
                                    'field'             => 'json_content',
                                    'fragment_selector' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'category' => ['type' => 'string'],
                                        ],
                                    ],
                                    'operator'          => 'equals',
                                    'value'             => 'report',
                                ],
                                [
                                    'type'              => 'condition',
                                    'field'             => 'json_content',
                                    'fragment_selector' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'priority' => ['type' => 'string'],
                                        ],
                                    ],
                                    'operator'          => 'equals',
                                    'value'             => 'high',
                                ],
                            ],
                        ],
                        [
                            'type'       => 'condition_group',
                            'operator'   => 'AND',
                            'conditions' => [
                                [
                                    'type'              => 'condition',
                                    'field'             => 'json_content',
                                    'fragment_selector' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'category' => ['type' => 'string'],
                                        ],
                                    ],
                                    'operator'          => 'equals',
                                    'value'             => 'note',
                                ],
                                [
                                    'type'              => 'condition',
                                    'field'             => 'json_content',
                                    'fragment_selector' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'priority' => ['type' => 'string'],
                                        ],
                                    ],
                                    'operator'          => 'equals',
                                    'value'             => 'medium',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact1 and artifact2 should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with regex condition
     */
    public function test_filter_artifacts_with_regex_condition()
    {
        // Given we have 3 artifacts
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'Email: user1@example.com',
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'Contact: user2@example.org',
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => 'Website: www.example.com',
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with regex condition to match emails
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'text_content',
                            'fragment_selector' => [
                                'type' => 'string',
                            ],
                            'operator'          => 'regex',
                            'value'             => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifacts with emails should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with empty condition (should pass all artifacts)
     */
    public function test_filter_artifacts_with_empty_conditions()
    {
        // Given we have 3 artifacts
        $artifact1 = Artifact::factory()->create();
        $artifact2 = Artifact::factory()->create();
        $artifact3 = Artifact::factory()->create();

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with empty conditions array
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then all artifacts should be in the output since conditions are empty
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(3, $outputArtifacts);
    }

    /**
     * Test filtering with numeric comparison operators (greater_than, less_than)
     */
    public function test_filter_artifacts_with_numeric_comparisons()
    {
        // Given we have 3 artifacts with numeric values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['value' => 10],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['value' => 50],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['value' => 100],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Test greater_than operator
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'value' => ['type' => 'number'],
                                ],
                            ],
                            'operator'          => 'greater_than',
                            'value'             => 30,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifacts with value > 30 should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
        $this->assertContains($artifact3->id, $outputArtifactIds);

        // Reset output artifacts
        $this->taskProcess->outputArtifacts()->detach();

        // Test less_than operator
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'value' => ['type' => 'number'],
                                ],
                            ],
                            'operator'          => 'less_than',
                            'value'             => 75,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task again
        $this->taskProcess->getRunner()->run();

        // Then only artifacts with value < 75 should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with case sensitivity
     */
    public function test_filter_artifacts_with_case_sensitivity()
    {
        // Given we have 3 artifacts with text values
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'This document contains IMPORTANT information',
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'This document contains important details',
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => 'This document has no matching content',
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Test case-sensitive search
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'text_content',
                            'fragment_selector' => [
                                'type' => 'string',
                            ],
                            'operator'          => 'contains',
                            'value'             => 'IMPORTANT',
                            'case_sensitive'    => true,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with exact case match should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);

        // Reset output artifacts
        $this->taskProcess->outputArtifacts()->detach();

        // Test case-insensitive search (default)
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'text_content',
                            'fragment_selector' => [
                                'type' => 'string',
                            ],
                            'operator'          => 'contains',
                            'value'             => 'important',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task again
        $this->taskProcess->getRunner()->run();

        // Then both artifacts with case-insensitive matches should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with 'exists' operator
     */
    public function test_filter_artifacts_with_exists_operator()
    {
        // Given we have 3 artifacts with different fields present/absent
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['optional_field' => 'value1'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['different_field' => 'value2'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => null,
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with exists operator
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'optional_field' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'exists',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with the optional_field should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with array values in meta fields
     */
    public function test_filter_artifacts_with_array_values()
    {
        // Given we have artifacts with array values in meta fields
        $artifact1 = Artifact::factory()->create([
            'meta' => ['tags' => ['important', 'document', 'critical']],
        ]);

        $artifact2 = Artifact::factory()->create([
            'meta' => ['tags' => ['normal', 'document']],
        ]);

        $artifact3 = Artifact::factory()->create([
            'meta' => ['tags' => ['low-priority']],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config searching for a specific tag value
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'meta',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'tags' => ['type' => 'array'],
                                ],
                            ],
                            'operator'          => 'contains',
                            'value'             => 'document',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifacts with 'document' in tags should be in the output
        $outputArtifactIds = $this->taskProcess->fresh()->outputArtifacts->pluck('id')->toArray();
        $this->assertCount(2, $outputArtifactIds);
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test validation error for invalid filter configuration
     */
    public function test_validation_error_for_invalid_filter_config()
    {
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);

        // Given we have one artifact
        $artifact = Artifact::factory()->create();
        $this->taskProcess->inputArtifacts()->attach([$artifact->id]);

        // Set up an invalid filter config (missing field in condition)
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'     => 'condition',
                            // Missing 'field'
                            'operator' => 'contains',
                            'value'    => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task, it should throw a ValidationError
        $this->taskProcess->getRunner()->run();
    }

    /**
     * Test validation error for invalid operator in filter configuration
     */
    public function test_validation_error_for_invalid_operator()
    {
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);

        // Given we have one artifact
        $artifact = Artifact::factory()->create();
        $this->taskProcess->inputArtifacts()->attach([$artifact->id]);

        // Set up an invalid filter config (invalid operator)
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'text_content',
                            'fragment_selector' => [
                                'type' => 'string',
                            ],
                            'operator'          => 'not_a_valid_operator', // Invalid operator
                            'value'             => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task, it should throw a ValidationError
        $this->taskProcess->getRunner()->run();
    }

    /**
     * Test filtering with fragment selectors for JSON content
     */
    public function test_filter_artifacts_with_json_fragment_selector()
    {
        // Given we have 3 artifacts with different JSON content
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'user'     => [
                    'name' => 'John',
                    'role' => 'admin',
                ],
                'settings' => [
                    'notifications' => true,
                ],
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'user'     => [
                    'name' => 'Alice',
                    'role' => 'editor',
                ],
                'settings' => [
                    'notifications' => false,
                ],
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => [
                'user'     => [
                    'name' => 'Bob',
                    'role' => 'viewer',
                ],
                'settings' => [
                    'notifications' => true,
                ],
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with fragment selector
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'AND',
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'user' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'role' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'admin',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact1 should be in the output (admin role)
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with fragment selectors for meta data
     */
    public function test_filter_artifacts_with_meta_fragment_selector()
    {
        // Given we have 3 artifacts with different meta data
        $artifact1 = Artifact::factory()->create([
            'meta' => [
                'tags'   => ['important', 'finance'],
                'status' => 'active',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'meta' => [
                'tags'   => ['normal', 'marketing'],
                'status' => 'active',
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'meta' => [
                'tags'   => ['important', 'hr'],
                'status' => 'inactive',
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with meta fragment selector for tags
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'AND',
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'meta',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'tags' => ['type' => 'array'],
                                ],
                            ],
                            'operator'          => 'contains',
                            'value'             => 'important',
                        ],
                        [
                            'type'              => 'condition',
                            'field'             => 'meta',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'status' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'active',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact1 should be in the output (has 'important' tag and is 'active')
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with fragment selectors for top-level fields
     */
    public function test_filter_artifacts_with_top_level_fragment_selector()
    {
        // Given we have 3 artifacts with different JSON content
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'category' => 'report',
                'priority' => 'high',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'category' => 'note',
                'priority' => 'medium',
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => [
                'category' => 'report',
                'priority' => 'low',
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with fragment selector for top-level field
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'operator'   => 'AND',
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'category' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'report',
                        ],
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'priority' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => 'high',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact1 should be in the output (report with high priority)
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with boolean values
     */
    public function test_filter_artifacts_with_boolean_values()
    {
        // Given we have 3 artifacts with different boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['active' => true],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['active' => false],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['active' => null],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config to keep only 'active' = true
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'active' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => true,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only the artifact with active=true should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with date values
     */
    public function test_filter_artifacts_with_date_values()
    {
        // Given we have 3 artifacts with different date values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['created_at' => '2025-01-01T00:00:00Z'],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['created_at' => '2025-02-15T00:00:00Z'],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['created_at' => '2025-03-30T00:00:00Z'],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config to keep dates after 2025-02-01
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'created_at' => ['type' => 'string'],
                                ],
                            ],
                            'operator'          => 'greater_than',
                            'value'             => '2025-02-01T00:00:00Z',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifacts after 2025-02-01 should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);
        $outputArtifactIds = $outputArtifacts->pluck('id')->toArray();
        $this->assertContains($artifact2->id, $outputArtifactIds);
        $this->assertContains($artifact3->id, $outputArtifactIds);
    }

    /**
     * Test filtering with nested arrays
     */
    public function test_filter_artifacts_with_nested_arrays()
    {
        // Given we have artifacts with nested array structures
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'tags' => ['important', 'urgent', 'review'],
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'tags' => ['medium', 'review'],
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => [
                'tags' => ['low', 'optional'],
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config to find arrays containing 'review'
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'tags' => ['type' => 'array'],
                                ],
                            ],
                            'operator'          => 'contains',
                            'value'             => 'review',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifacts with 'review' tag should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);
        $outputArtifactIds = $outputArtifacts->pluck('id')->toArray();
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with complex nested JSON structures
     */
    public function test_filter_artifacts_with_complex_json()
    {
        // Given we have artifacts with deeply nested JSON
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'user' => [
                    'profile' => [
                        'settings' => [
                            'notifications' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'user' => [
                    'profile' => [
                        'settings' => [
                            'notifications' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => [
                'user' => [
                    'profile' => [],
                ],
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config to find deeply nested property using fragment selector
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'user' => [
                                        'type'     => 'object',
                                        'children' => [
                                            'profile' => [
                                                'type'     => 'object',
                                                'children' => [
                                                    'settings' => [
                                                        'type'     => 'object',
                                                        'children' => [
                                                            'notifications' => ['type' => 'boolean'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => true,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only the artifact with notifications=true should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with mixed data types
     */
    public function test_filter_artifacts_with_mixed_data_types()
    {
        // Given we have artifacts with mixed data types
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'id'     => 1,
                'name'   => 'Test Item',
                'active' => true,
                'tags'   => ['important', 'document', 'critical'],
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'id'     => '2', // String instead of numeric
                'name'   => 'Another Item',
                'active' => 1, // Numeric 1 instead of boolean
                'tags'   => 'important, active', // String instead of array
            ],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => [
                'id'     => 3,
                'name'   => 'Inactive Item',
                'active' => false,
                'tags'   => [],
            ],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Set up filter config with numeric comparison
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'active' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'equals',
                            'value'             => true,
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Check the output - should match both active=true and active=1
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);
        $outputArtifactIds = $outputArtifacts->pluck('id')->toArray();
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with null values
     */
    public function test_filter_artifacts_with_null_values()
    {
        // Given we have 3 artifacts with null values
        $artifact1 = Artifact::factory()->create([
            'text_content' => 'This is a document with content',
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => null,
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => '',
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Test filtering for artifacts with null field value
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'     => 'condition',
                            'field'    => 'text_content',
                            'operator' => 'exists',
                        ],
                    ],
                    'action'     => 'discard', // Keep artifacts where text_content doesn't exist
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with null value should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact2->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with is_true boolean operator
     */
    public function test_filter_artifacts_with_is_true_operator()
    {
        // Given we have 3 artifacts with different boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['active' => true],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['active' => false],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['active' => null],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Configure filter with is_true operator
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'active' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'is_true',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with active=true should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact1->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with is_false boolean operator
     */
    public function test_filter_artifacts_with_is_false_operator()
    {
        // Given we have 3 artifacts with different boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['active' => true],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['active' => false],
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['active' => null],
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Configure filter with is_false operator
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'active' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'is_false',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with active=false should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact2->id, $outputArtifacts->first()->id);
    }

    /**
     * Test filtering with is_true operator on boolean array values
     */
    public function test_filter_artifacts_with_boolean_array_is_true()
    {
        // Given we have artifacts with arrays of boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, false, true]], // Contains true values
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['flags' => [false, false, false]], // Contains only false values
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, true, true]], // Contains only true values
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Filter for true should match artifacts containing at least one true
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'flags' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'is_true',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then artifacts with at least one true should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);
        $outputArtifactIds = $outputArtifacts->pluck('id')->toArray();
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact3->id, $outputArtifactIds);
        $this->assertNotContains($artifact2->id, $outputArtifactIds);
    }

    /**
     * Test filtering with is_false operator on boolean array values
     */
    public function test_filter_artifacts_with_boolean_array_is_false()
    {
        // Given we have artifacts with arrays of boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, false, true]], // Contains false values
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['flags' => [false, false, false]], // Contains only false values
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, true, true]], // Contains no false values
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Filter for false should match artifacts containing at least one false
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'flags' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'is_false',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then artifacts with at least one false should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(2, $outputArtifacts);
        $outputArtifactIds = $outputArtifacts->pluck('id')->toArray();
        $this->assertContains($artifact1->id, $outputArtifactIds);
        $this->assertContains($artifact2->id, $outputArtifactIds);
        $this->assertNotContains($artifact3->id, $outputArtifactIds);
    }

    /**
     * Test filtering with is_true operator on boolean array values with discard action
     */
    public function test_filter_artifacts_with_boolean_array_discard_action()
    {
        // Given we have artifacts with arrays of boolean values
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, false, true]], // Contains true values
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => ['flags' => [false, false, false]], // Contains only false values
        ]);

        $artifact3 = Artifact::factory()->create([
            'json_content' => ['flags' => [true, true, true]], // Contains only true values
        ]);

        // Attach artifacts to the task process input
        $this->taskProcess->inputArtifacts()->attach([$artifact1->id, $artifact2->id, $artifact3->id]);

        // Filter with discard action - discard artifacts that contain true values
        $this->taskDefinition->update([
            'task_runner_config' => [
                'filter_config' => [
                    'conditions' => [
                        [
                            'type'              => 'condition',
                            'field'             => 'json_content',
                            'fragment_selector' => [
                                'type'     => 'object',
                                'children' => [
                                    'flags' => ['type' => 'boolean'],
                                ],
                            ],
                            'operator'          => 'is_true',
                        ],
                    ],
                    'action'     => 'discard', // Discard artifacts where condition is true
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact with all false values should be in the output
        $outputArtifacts = $this->taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact2->id, $outputArtifacts->first()->id);
    }
}
