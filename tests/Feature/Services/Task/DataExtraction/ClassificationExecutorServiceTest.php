<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Services\Task\DataExtraction\ClassificationExecutorService;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ClassificationExecutorServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ClassificationExecutorService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(ClassificationExecutorService::class);
    }

    #[Test]
    public function compute_schema_hash_is_consistent(): void
    {
        // Given: A schema definition
        $schema = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
                'billing'         => [
                    'type'        => 'boolean',
                    'description' => 'Page contains billing information',
                ],
            ],
        ];

        // When: Computing hash multiple times
        $hash1 = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema]);
        $hash2 = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema]);

        // Then: Hash is consistent
        $this->assertEquals($hash1, $hash2);
        $this->assertIsString($hash1);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 produces 64-character hex string
    }

    #[Test]
    public function different_schemas_produce_different_hashes(): void
    {
        // Given: Two different schemas
        $schema1 = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $schema2 = [
            'type'       => 'object',
            'properties' => [
                'billing' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains billing information',
                ],
            ],
        ];

        // When: Computing hashes
        $hash1 = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema1]);
        $hash2 = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema2]);

        // Then: Hashes are different
        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function cache_miss_stores_result_in_stored_file_meta(): void
    {
        // Given: A StoredFile with no cached classifications
        $storedFile = StoredFile::factory()->create([
            'meta' => null,
        ]);

        $schema = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $classificationResult = [
            'diagnosis_codes' => true,
        ];

        $schemaDefinitionId = 12345;
        $schemaHash         = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema]);

        // When: Storing classification in cache (keyed by schema definition ID)
        $this->invokeProtectedMethod(
            $this->service,
            'storeCachedClassification',
            [$storedFile, $schemaDefinitionId, $schemaHash, $classificationResult]
        );

        // Then: Result is stored in StoredFile.meta['classifications'][schemaDefinitionId]
        $storedFile->refresh();
        $this->assertIsArray($storedFile->meta);
        $this->assertArrayHasKey('classifications', $storedFile->meta);

        $cacheKey = (string)$schemaDefinitionId;
        $this->assertArrayHasKey($cacheKey, $storedFile->meta['classifications']);

        $cached = $storedFile->meta['classifications'][$cacheKey];
        $this->assertEquals($schemaDefinitionId, $cached['schema_definition_id']);
        $this->assertEquals($schemaHash, $cached['schema_hash']);
        $this->assertEquals($classificationResult, $cached['result']);
        $this->assertArrayHasKey('classified_at', $cached);
    }

    #[Test]
    public function cache_hit_returns_stored_result(): void
    {
        // Given: A StoredFile with cached classification (keyed by schema definition ID)
        $schema = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $cachedResult = [
            'diagnosis_codes' => true,
        ];

        $schemaDefinitionId = 12345;
        $schemaHash         = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema]);

        $storedFile = StoredFile::factory()->create([
            'meta' => [
                'classifications' => [
                    (string)$schemaDefinitionId => [
                        'schema_definition_id' => $schemaDefinitionId,
                        'schema_hash'          => $schemaHash,
                        'classified_at'        => now()->toIso8601String(),
                        'result'               => $cachedResult,
                    ],
                ],
            ],
        ]);

        // When: Getting cached classification
        $result = $this->service->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash);

        // Then: Returns cached result
        $this->assertEquals($cachedResult, $result);
    }

    #[Test]
    public function cache_miss_returns_null(): void
    {
        // Given: A StoredFile with no cached classifications
        $storedFile = StoredFile::factory()->create([
            'meta' => null,
        ]);

        $schema = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $schemaDefinitionId = 12345;
        $schemaHash         = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema]);

        // When: Attempting to get cached classification
        $result = $this->service->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash);

        // Then: Returns null
        $this->assertNull($result);
    }

    #[Test]
    public function different_schema_definitions_create_separate_cache_entries(): void
    {
        // Given: A StoredFile with one cached classification
        $schema1 = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $result1 = [
            'diagnosis_codes' => true,
        ];

        $schemaDefinitionId1 = 12345;
        $schemaHash1         = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema1]);

        $storedFile = StoredFile::factory()->create([
            'meta' => null,
        ]);

        // Store first classification (schema definition ID 1)
        $this->invokeProtectedMethod(
            $this->service,
            'storeCachedClassification',
            [$storedFile, $schemaDefinitionId1, $schemaHash1, $result1]
        );

        $storedFile->refresh();
        $this->assertCount(1, $storedFile->meta['classifications']);

        // When: Storing a classification for a different schema definition ID
        $schema2 = [
            'type'       => 'object',
            'properties' => [
                'billing' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains billing information',
                ],
            ],
        ];

        $result2 = [
            'billing' => false,
        ];

        $schemaDefinitionId2 = 67890;
        $schemaHash2         = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema2]);

        $this->invokeProtectedMethod(
            $this->service,
            'storeCachedClassification',
            [$storedFile, $schemaDefinitionId2, $schemaHash2, $result2]
        );

        // Then: Both cache entries exist (keyed by different schema definition IDs)
        $storedFile->refresh();
        $this->assertCount(2, $storedFile->meta['classifications']);

        // Verify both entries are retrievable
        $cachedResult1 = $this->service->getCachedClassification($storedFile, $schemaDefinitionId1, $schemaHash1);
        $cachedResult2 = $this->service->getCachedClassification($storedFile, $schemaDefinitionId2, $schemaHash2);

        $this->assertEquals($result1, $cachedResult1);
        $this->assertEquals($result2, $cachedResult2);
    }

    #[Test]
    public function schema_change_with_same_definition_id_busts_cache(): void
    {
        // Given: A StoredFile with cached classification for a schema definition
        $schema1 = [
            'type'       => 'object',
            'properties' => [
                'diagnosis_codes' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains diagnosis codes',
                ],
            ],
        ];

        $result1 = [
            'diagnosis_codes' => true,
        ];

        $schemaDefinitionId = 12345;
        $schemaHash1        = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema1]);

        $storedFile = StoredFile::factory()->create([
            'meta' => [
                'classifications' => [
                    (string)$schemaDefinitionId => [
                        'schema_definition_id' => $schemaDefinitionId,
                        'schema_hash'          => $schemaHash1,
                        'classified_at'        => now()->toIso8601String(),
                        'result'               => $result1,
                    ],
                ],
            ],
        ]);

        // When: Schema changes (new hash) but same schema definition ID
        $schema2 = [
            'type'       => 'object',
            'properties' => [
                'billing' => [
                    'type'        => 'boolean',
                    'description' => 'Page contains billing information',
                ],
            ],
        ];

        $schemaHash2 = $this->invokeProtectedMethod($this->service, 'computeSchemaHash', [$schema2]);

        // Then: Cache returns null (hash mismatch = cache busted)
        $cachedResult = $this->service->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash2);
        $this->assertNull($cachedResult, 'Cache should be busted when schema hash changes');
    }

    #[Test]
    public function getArtifactsForCategory_filters_by_classification(): void
    {
        // Given: Artifacts with different classifications
        $artifact1 = Artifact::create([
            'name'               => 'Page 1',
            'task_definition_id' => null,
            'task_run_id'        => null,
            'team_id'            => $this->user->currentTeam->id,
            'meta'               => [
                'classification' => [
                    'diagnosis_codes' => true,
                    'billing'         => false,
                ],
            ],
        ]);

        $artifact2 = Artifact::create([
            'name'               => 'Page 2',
            'task_definition_id' => null,
            'task_run_id'        => null,
            'team_id'            => $this->user->currentTeam->id,
            'meta'               => [
                'classification' => [
                    'diagnosis_codes' => false,
                    'billing'         => true,
                ],
            ],
        ]);

        $artifact3 = Artifact::create([
            'name'               => 'Page 3',
            'task_definition_id' => null,
            'task_run_id'        => null,
            'team_id'            => $this->user->currentTeam->id,
            'meta'               => [
                'classification' => [
                    'diagnosis_codes' => true,
                    'billing'         => true,
                ],
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When: Filtering for diagnosis_codes
        $diagnosisArtifacts = $this->service->getArtifactsForCategory($artifacts, 'diagnosis_codes');

        // Then: Only artifacts with diagnosis_codes=true are returned
        $this->assertCount(2, $diagnosisArtifacts);
        $this->assertTrue($diagnosisArtifacts->contains($artifact1));
        $this->assertFalse($diagnosisArtifacts->contains($artifact2));
        $this->assertTrue($diagnosisArtifacts->contains($artifact3));
    }

    #[Test]
    public function getArtifactsForCategory_excludes_artifacts_without_classification(): void
    {
        // Given: Artifacts with and without classification
        $artifact1 = Artifact::create([
            'name'               => 'Page 1',
            'task_definition_id' => null,
            'task_run_id'        => null,
            'team_id'            => $this->user->currentTeam->id,
            'meta'               => [
                'classification' => [
                    'diagnosis_codes' => true,
                ],
            ],
        ]);

        $artifact2 = Artifact::create([
            'name'               => 'Page 2',
            'task_definition_id' => null,
            'task_run_id'        => null,
            'team_id'            => $this->user->currentTeam->id,
            'meta'               => null, // No classification
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When: Filtering for diagnosis_codes
        $diagnosisArtifacts = $this->service->getArtifactsForCategory($artifacts, 'diagnosis_codes');

        // Then: Only artifact with classification is returned
        $this->assertCount(1, $diagnosisArtifacts);
        $this->assertTrue($diagnosisArtifacts->contains($artifact1));
    }

    /**
     * Helper to invoke protected methods for testing internal caching logic
     */
    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
