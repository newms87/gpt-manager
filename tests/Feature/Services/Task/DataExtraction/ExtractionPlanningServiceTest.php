<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Services\Task\DataExtraction\ExtractionPlanningService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionPlanningServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractionPlanningService $planningService;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->planningService = app(ExtractionPlanningService::class);
    }

    #[Test]
    public function getCachedPlan_returns_null_when_no_plan_exists(): void
    {
        // Given: TaskDefinition with no cached plan
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => [],
        ]);

        // When: Getting cached plan
        $cachedPlan = $this->planningService->getCachedPlan($taskDefinition);

        // Then: Returns null
        $this->assertNull($cachedPlan);
    }

    #[Test]
    public function getCachedPlan_returns_plan_when_valid_cache_exists(): void
    {
        // Given: TaskDefinition with valid cached plan
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [],
        ]);

        $plan = [
            'levels' => [
                [
                    'level'  => 0,
                    'groups' => [
                        ['name' => 'Test Group'],
                    ],
                ],
            ],
        ];

        $cacheKey = $this->planningService->computeCacheKey($taskDefinition);

        $taskDefinition->meta = [
            'extraction_plan'           => $plan,
            'extraction_plan_cache_key' => $cacheKey,
        ];
        $taskDefinition->save();

        // When: Getting cached plan
        $cachedPlan = $this->planningService->getCachedPlan($taskDefinition);

        // Then: Returns cached plan
        $this->assertNotNull($cachedPlan);
        $this->assertEquals($plan, $cachedPlan);
    }

    #[Test]
    public function getCachedPlan_invalidates_cache_when_schema_changes(): void
    {
        // Given: TaskDefinition with cached plan
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [],
        ]);

        $plan = [
            'levels' => [
                [
                    'level'  => 0,
                    'groups' => [
                        ['name' => 'Test Group'],
                    ],
                ],
            ],
        ];

        // Store plan with old cache key
        $taskDefinition->meta = [
            'extraction_plan'           => $plan,
            'extraction_plan_cache_key' => 'old_cache_key',
        ];
        $taskDefinition->save();

        // When: Schema changes (which changes cache key)
        $schemaDefinition->schema = [
            'type'       => 'object',
            'properties' => [
                'client_name'   => ['type' => 'string'],
                'accident_date' => ['type' => 'string', 'format' => 'date'],
            ],
        ];
        $schemaDefinition->save();
        $taskDefinition->refresh();

        $cachedPlan = $this->planningService->getCachedPlan($taskDefinition);

        // Then: Cache is invalidated, returns null
        $this->assertNull($cachedPlan);
    }

    #[Test]
    public function computeCacheKey_changes_when_schema_changes(): void
    {
        // Given: TaskDefinition with schema
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [],
        ]);

        $originalKey = $this->planningService->computeCacheKey($taskDefinition);

        // When: Schema changes
        $schemaDefinition->schema = [
            'type'       => 'object',
            'properties' => [
                'client_name'   => ['type' => 'string'],
                'accident_date' => ['type' => 'string'],
            ],
        ];
        $schemaDefinition->save();
        $taskDefinition->refresh();

        $newKey = $this->planningService->computeCacheKey($taskDefinition);

        // Then: Cache key changes
        $this->assertNotEquals($originalKey, $newKey);
    }

    #[Test]
    public function computeCacheKey_changes_when_config_changes(): void
    {
        // Given: TaskDefinition with config
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [
                'group_max_points' => 10,
            ],
        ]);

        $originalKey = $this->planningService->computeCacheKey($taskDefinition);

        // When: Config changes
        $taskDefinition->task_runner_config = [
            'group_max_points' => 20,
        ];
        $taskDefinition->save();

        $newKey = $this->planningService->computeCacheKey($taskDefinition);

        // Then: Cache key changes
        $this->assertNotEquals($originalKey, $newKey);
    }

    #[Test]
    public function cachePlan_stores_plan_in_task_definition_meta(): void
    {
        // Given: TaskDefinition and plan
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                ],
            ],
        ]);

        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'schema_definition_id' => $schemaDefinition->id,
            'task_runner_config'   => [],
        ]);

        $plan = [
            'levels' => [
                [
                    'level'  => 0,
                    'groups' => [
                        ['name' => 'Test Group'],
                    ],
                ],
            ],
        ];

        // When: Caching plan
        $this->planningService->cachePlan($taskDefinition, $plan);

        // Then: Plan is stored in meta with cache key and timestamp
        $taskDefinition->refresh();
        $this->assertArrayHasKey('extraction_plan', $taskDefinition->meta);
        $this->assertArrayHasKey('extraction_plan_cache_key', $taskDefinition->meta);
        $this->assertArrayHasKey('extraction_plan_generated_at', $taskDefinition->meta);
        $this->assertEquals($plan, $taskDefinition->meta['extraction_plan']);
    }
}
