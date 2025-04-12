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
        ]);
    }

    /**
     * Test filtering artifacts with AND condition
     */
    public function test_filter_artifacts_with_and_condition()
    {
        // Given we have 3 artifacts
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
                            'field'    => 'json_content',
                            'path'     => 'category',
                            'operator' => 'equals',
                            'value'    => 'report',
                        ],
                        [
                            'field'    => 'meta',
                            'path'     => 'status',
                            'operator' => 'equals',
                            'value'    => 'active',
                        ],
                    ],
                ],
            ],
        ]);

        // When we run the filter task
        $this->taskProcess->getRunner()->run();

        // Then only artifact1 should be in the output
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
                            'field'    => 'json_content',
                            'path'     => 'category',
                            'operator' => 'equals',
                            'value'    => 'report',
                        ],
                        [
                            'field'    => 'text_content',
                            'operator' => 'contains',
                            'value'    => 'another',
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
                            'operator'   => 'AND',
                            'conditions' => [
                                [
                                    'field'    => 'json_content',
                                    'path'     => 'category',
                                    'operator' => 'equals',
                                    'value'    => 'report',
                                ],
                                [
                                    'field'    => 'json_content',
                                    'path'     => 'priority',
                                    'operator' => 'equals',
                                    'value'    => 'high',
                                ],
                            ],
                        ],
                        [
                            'operator'   => 'AND',
                            'conditions' => [
                                [
                                    'field'    => 'json_content',
                                    'path'     => 'category',
                                    'operator' => 'equals',
                                    'value'    => 'note',
                                ],
                                [
                                    'field'    => 'json_content',
                                    'path'     => 'priority',
                                    'operator' => 'equals',
                                    'value'    => 'medium',
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
                            'field'    => 'text_content',
                            'operator' => 'regex',
                            'value'    => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
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
}
