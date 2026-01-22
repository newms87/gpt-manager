<?php

namespace Tests\Unit\Traits;

use App\Traits\MergesExtractionResults;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for MergesExtractionResults trait.
 *
 * Verifies that batch extraction merging preserves non-null values from earlier batches
 * when later batches return null/empty data for fields that don't appear on those pages.
 */
class MergesExtractionResultsTest extends TestCase
{
    /**
     * Create a test object that uses the trait.
     */
    protected function createTestObject(): object
    {
        return new class
        {
            use MergesExtractionResults;

            public function testMergeExtractionResults(array $existing, array $new): array
            {
                return $this->mergeExtractionResults($existing, $new);
            }

            public function testMergeExtractionResultsWithTracking(array $existing, array $new, string $prefix = ''): array
            {
                return $this->mergeExtractionResultsWithTracking($existing, $new, $prefix);
            }

            public function testMergeExtractionResultsWithConflicts(
                array $existing,
                array $new,
                array $existingPageSources = [],
                array $newPageSources = [],
                string $prefix = ''
            ): array {
                return $this->mergeExtractionResultsWithConflicts(
                    $existing,
                    $new,
                    $existingPageSources,
                    $newPageSources,
                    $prefix
                );
            }

            public function testMergePageSourcesForUpdatedFields(array $existing, array $new, array $updatedFields): array
            {
                return $this->mergePageSourcesForUpdatedFields($existing, $new, $updatedFields);
            }

            public function testExtractFieldNameFromPath(string $path): string
            {
                return $this->extractFieldNameFromPath($path);
            }

            public function testIsMeaningfulValue(mixed $value): bool
            {
                return $this->isMeaningfulValue($value);
            }

            public function testIsAssociativeArray(array $array): bool
            {
                return $this->isAssociativeArray($array);
            }

            public function testValuesAreDifferent(mixed $a, mixed $b): bool
            {
                return $this->valuesAreDifferent($a, $b);
            }

            public function testLookupPageSource(array $pageSources, string $fieldPath, string $fieldName): ?int
            {
                return $this->lookupPageSource($pageSources, $fieldPath, $fieldName);
            }
        };
    }

    #[Test]
    public function merge_preserves_existing_value_when_new_is_null(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['incident_description' => 'Patient was in an accident'];
        $new      = ['incident_description' => null];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('Patient was in an accident', $result['incident_description']);
    }

    #[Test]
    public function merge_preserves_existing_value_when_new_is_null_string(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['incident_description' => 'Patient was in an accident'];
        $new      = ['incident_description' => 'null'];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('Patient was in an accident', $result['incident_description']);
    }

    #[Test]
    public function merge_preserves_existing_value_when_new_is_empty_string(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['name' => 'John Smith'];
        $new      = ['name' => ''];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('John Smith', $result['name']);
    }

    #[Test]
    public function merge_preserves_existing_value_when_new_is_empty_array(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['diagnoses' => ['Fracture', 'Concussion']];
        $new      = ['diagnoses' => []];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals(['Fracture', 'Concussion'], $result['diagnoses']);
    }

    #[Test]
    public function merge_overwrites_when_new_value_is_meaningful(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['status' => 'pending'];
        $new      = ['status' => 'completed'];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('completed', $result['status']);
    }

    #[Test]
    public function merge_adds_new_fields(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['name' => 'John Smith'];
        $new      = ['date' => '2024-01-15'];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('John Smith', $result['name']);
        $this->assertEquals('2024-01-15', $result['date']);
    }

    #[Test]
    public function merge_recursively_merges_nested_objects(): void
    {
        $testObject = $this->createTestObject();

        $existing = [
            'patient' => [
                'name'    => 'John Smith',
                'address' => '123 Main St',
            ],
        ];

        $new = [
            'patient' => [
                'name'  => 'null', // Should be skipped
                'phone' => '555-1234',
            ],
        ];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('John Smith', $result['patient']['name']);
        $this->assertEquals('123 Main St', $result['patient']['address']);
        $this->assertEquals('555-1234', $result['patient']['phone']);
    }

    #[Test]
    public function merge_accumulates_sequential_arrays_with_unique_items(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['items' => ['apple', 'banana']];
        $new      = ['items' => ['cherry', 'date', 'elderberry']];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        // Sequential arrays should be accumulated to preserve items from different batches
        // This prevents losing data when Batch 1 returns ["provider A"] and Batch 2 returns ["provider B"]
        $this->assertEquals(['apple', 'banana', 'cherry', 'date', 'elderberry'], $result['items']);
    }

    #[Test]
    public function merge_accumulates_sequential_arrays_of_objects_with_deduplication(): void
    {
        $testObject = $this->createTestObject();

        // Simulates extraction where Batch 1 finds provider "SYNERGY CHIROPRACTIC" and Batch 2 finds "Richard A. Lewellen"
        $existing = [
            'provider' => [
                ['name' => 'SYNERGY CHIROPRACTIC CLINICS', 'address' => '123 Main St', 'phone' => '555-1234'],
            ],
        ];
        $new = [
            'provider' => [
                ['name' => 'Richard A. Lewellen, DC'],
            ],
        ];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        // Both providers should be accumulated - this is the core bug fix
        $this->assertCount(2, $result['provider']);
        $this->assertEquals('SYNERGY CHIROPRACTIC CLINICS', $result['provider'][0]['name']);
        $this->assertEquals('Richard A. Lewellen, DC', $result['provider'][1]['name']);
    }

    #[Test]
    public function merge_accumulates_sequential_arrays_deduplicates_by_name(): void
    {
        $testObject = $this->createTestObject();

        // Both batches found the same provider (with slight case difference)
        $existing = [
            'provider' => [
                ['name' => 'John Smith', 'phone' => '555-1234'],
            ],
        ];
        $new = [
            'provider' => [
                ['name' => 'john smith', 'phone' => '555-5678'], // Same name, different phone
            ],
        ];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        // Should NOT duplicate - names are case-insensitively equivalent
        $this->assertCount(1, $result['provider']);
        $this->assertEquals('John Smith', $result['provider'][0]['name']);
    }

    #[Test]
    public function merge_accumulates_sequential_arrays_deduplicates_by_id(): void
    {
        $testObject = $this->createTestObject();

        $existing = [
            'items' => [
                ['id' => 1, 'value' => 'first'],
            ],
        ];
        $new = [
            'items' => [
                ['id' => 1, 'value' => 'updated'], // Same ID
                ['id' => 2, 'value' => 'second'],  // Different ID - should be added
            ],
        ];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        // ID 1 should NOT be duplicated, ID 2 should be added
        $this->assertCount(2, $result['items']);
        $this->assertEquals(1, $result['items'][0]['id']);
        $this->assertEquals('first', $result['items'][0]['value']); // Original kept
        $this->assertEquals(2, $result['items'][1]['id']);
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_null(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue(null));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_null_string(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('null'));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_empty_string(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue(''));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_empty_array(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue([]));
    }

    #[Test]
    public function is_meaningful_value_returns_true_for_zero(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsMeaningfulValue(0));
    }

    #[Test]
    public function is_meaningful_value_returns_true_for_false(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsMeaningfulValue(false));
    }

    #[Test]
    public function is_meaningful_value_returns_true_for_non_empty_string(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsMeaningfulValue('hello'));
    }

    #[Test]
    public function is_meaningful_value_returns_true_for_non_empty_array(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsMeaningfulValue(['item']));
    }

    #[Test]
    public function is_associative_array_returns_true_for_string_keys(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsAssociativeArray(['name' => 'John', 'age' => 30]));
    }

    #[Test]
    public function is_associative_array_returns_false_for_sequential_keys(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsAssociativeArray(['apple', 'banana', 'cherry']));
    }

    #[Test]
    public function is_associative_array_returns_false_for_empty_array(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsAssociativeArray([]));
    }

    #[Test]
    public function is_associative_array_returns_true_for_non_sequential_integer_keys(): void
    {
        $testObject = $this->createTestObject();

        $this->assertTrue($testObject->testIsAssociativeArray([0 => 'a', 2 => 'b', 5 => 'c']));
    }

    #[Test]
    public function merge_handles_deeply_nested_structures(): void
    {
        $testObject = $this->createTestObject();

        $existing = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'original',
                    ],
                ],
            ],
        ];

        $new = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'null', // Should be skipped
                        'other' => 'added',
                    ],
                ],
            ],
        ];

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('original', $result['level1']['level2']['level3']['value']);
        $this->assertEquals('added', $result['level1']['level2']['level3']['other']);
    }

    #[Test]
    public function merge_handles_real_world_batch_scenario(): void
    {
        $testObject = $this->createTestObject();

        // Batch 1: Page 1 has incident description
        $batch1 = [
            'incident_description' => 'Patient was driving when struck by another vehicle',
            'accident_date'        => '2024-01-15',
        ];

        // Batch 2: Pages 3-4 have medical info but not incident details
        $batch2 = [
            'incident_description' => 'null',  // Not found on these pages
            'diagnoses'            => ['Lumbar strain', 'Cervical sprain'],
        ];

        // Batch 3: Pages 5-6 have more medical info
        $batch3 = [
            'incident_description' => '',     // Empty - not found
            'accident_date'        => null,   // Not found
            'provider_name'        => 'Dr. Smith',
        ];

        // Simulate cumulative merging
        $result = $testObject->testMergeExtractionResults([], $batch1);
        $result = $testObject->testMergeExtractionResults($result, $batch2);
        $result = $testObject->testMergeExtractionResults($result, $batch3);

        // Verify all good data is preserved
        $this->assertEquals('Patient was driving when struck by another vehicle', $result['incident_description']);
        $this->assertEquals('2024-01-15', $result['accident_date']);
        $this->assertEquals(['Lumbar strain', 'Cervical sprain'], $result['diagnoses']);
        $this->assertEquals('Dr. Smith', $result['provider_name']);
    }

    #[Test]
    public function merge_overwrites_with_better_data_when_meaningful(): void
    {
        $testObject = $this->createTestObject();

        // If a later batch has more complete/correct data, it should overwrite
        $existing = ['name' => 'J. Smith'];
        $new      = ['name' => 'John Smith']; // More complete

        $result = $testObject->testMergeExtractionResults($existing, $new);

        $this->assertEquals('John Smith', $result['name']);
    }

    #[Test]
    public function merge_with_tracking_returns_merged_data_and_updated_fields(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['incident_description' => 'Original incident'];
        $new      = [
            'accident_date' => '2024-01-15',
            'provider_name' => 'Dr. Smith',
        ];

        $result = $testObject->testMergeExtractionResultsWithTracking($existing, $new);

        // Verify merged data
        $this->assertEquals('Original incident', $result['merged']['incident_description']);
        $this->assertEquals('2024-01-15', $result['merged']['accident_date']);
        $this->assertEquals('Dr. Smith', $result['merged']['provider_name']);

        // Verify only new fields are tracked as updated
        $this->assertContains('accident_date', $result['updated_fields']);
        $this->assertContains('provider_name', $result['updated_fields']);
        $this->assertNotContains('incident_description', $result['updated_fields']);
    }

    #[Test]
    public function merge_with_tracking_does_not_track_null_values(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['incident_description' => 'Patient was in accident'];
        $new      = ['incident_description' => null];

        $result = $testObject->testMergeExtractionResultsWithTracking($existing, $new);

        // Data should be preserved
        $this->assertEquals('Patient was in accident', $result['merged']['incident_description']);

        // No fields should be marked as updated
        $this->assertEmpty($result['updated_fields']);
    }

    #[Test]
    public function merge_with_tracking_tracks_overwrites(): void
    {
        $testObject = $this->createTestObject();

        $existing = ['name' => 'J. Smith'];
        $new      = ['name' => 'John Smith'];

        $result = $testObject->testMergeExtractionResultsWithTracking($existing, $new);

        // Data should be overwritten
        $this->assertEquals('John Smith', $result['merged']['name']);

        // Overwrite should be tracked
        $this->assertContains('name', $result['updated_fields']);
    }

    #[Test]
    public function merge_with_tracking_handles_nested_objects(): void
    {
        $testObject = $this->createTestObject();

        $existing = [
            'patient' => [
                'name' => 'John Smith',
            ],
        ];

        $new = [
            'patient' => [
                'name'  => 'null',       // Should not update
                'phone' => '555-1234',   // Should update
            ],
        ];

        $result = $testObject->testMergeExtractionResultsWithTracking($existing, $new);

        // Verify data
        $this->assertEquals('John Smith', $result['merged']['patient']['name']);
        $this->assertEquals('555-1234', $result['merged']['patient']['phone']);

        // Only phone should be tracked (with full path)
        $this->assertContains('patient.phone', $result['updated_fields']);
        $this->assertNotContains('patient.name', $result['updated_fields']);
    }

    #[Test]
    public function merge_page_sources_only_for_updated_fields(): void
    {
        $testObject = $this->createTestObject();

        $existingPageSources = ['incident_description' => 1];
        $newPageSources      = ['incident_description' => 4, 'accident_date' => 3];
        $updatedFields       = ['accident_date']; // incident_description was NOT updated

        $result = $testObject->testMergePageSourcesForUpdatedFields($existingPageSources, $newPageSources, $updatedFields);

        // incident_description should keep page 1 (was not updated)
        $this->assertEquals(1, $result['incident_description']);

        // accident_date should be page 3 (was updated)
        $this->assertEquals(3, $result['accident_date']);
    }

    #[Test]
    public function merge_page_sources_preserves_existing_when_no_updates(): void
    {
        $testObject = $this->createTestObject();

        $existingPageSources = ['field_a' => 1, 'field_b' => 2];
        $newPageSources      = ['field_a' => 5, 'field_b' => 6];
        $updatedFields       = []; // No fields were updated

        $result = $testObject->testMergePageSourcesForUpdatedFields($existingPageSources, $newPageSources, $updatedFields);

        // All original page sources should be preserved
        $this->assertEquals(1, $result['field_a']);
        $this->assertEquals(2, $result['field_b']);
    }

    #[Test]
    public function merge_page_sources_handles_nested_field_paths(): void
    {
        $testObject = $this->createTestObject();

        $existingPageSources = ['name' => 1];
        $newPageSources      = ['name' => 4];
        $updatedFields       = ['care_summary.name']; // Full path from nested merge

        $result = $testObject->testMergePageSourcesForUpdatedFields($existingPageSources, $newPageSources, $updatedFields);

        // Should update 'name' page source because care_summary.name was updated
        $this->assertEquals(4, $result['name']);
    }

    #[Test]
    public function extract_field_name_from_path_handles_simple_paths(): void
    {
        $testObject = $this->createTestObject();

        $this->assertEquals('incident_description', $testObject->testExtractFieldNameFromPath('incident_description'));
    }

    #[Test]
    public function extract_field_name_from_path_handles_nested_paths(): void
    {
        $testObject = $this->createTestObject();

        $this->assertEquals('name', $testObject->testExtractFieldNameFromPath('care_summary.name'));
        $this->assertEquals('value', $testObject->testExtractFieldNameFromPath('level1.level2.level3.value'));
    }

    #[Test]
    public function extract_field_name_from_path_handles_array_notation(): void
    {
        $testObject = $this->createTestObject();

        $this->assertEquals('name', $testObject->testExtractFieldNameFromPath('providers[0].name'));
        $this->assertEquals('date', $testObject->testExtractFieldNameFromPath('diagnoses[1].date'));
    }

    #[Test]
    public function page_sources_merge_real_world_scenario(): void
    {
        $testObject = $this->createTestObject();

        // Simulate the bug scenario from the task description
        // Batch 1: Page 1 has incident_description
        $cumulativeData        = [];
        $cumulativePageSources = [];

        $batch1Data        = ['incident_description' => 'Patient was in an accident'];
        $batch1PageSources = ['incident_description' => 1];

        $mergeResult1      = $testObject->testMergeExtractionResultsWithTracking($cumulativeData, $batch1Data);
        $cumulativeData    = $mergeResult1['merged'];
        $cumulativePageSources = $testObject->testMergePageSourcesForUpdatedFields(
            $cumulativePageSources,
            $batch1PageSources,
            $mergeResult1['updated_fields']
        );

        // After batch 1: page_sources should show page 1 for incident_description
        $this->assertEquals(1, $cumulativePageSources['incident_description']);

        // Batch 2: Pages 3-4 return empty incident_description but have other data
        $batch2Data        = ['incident_description' => '', 'diagnosis' => 'Whiplash'];
        $batch2PageSources = ['incident_description' => 4, 'diagnosis' => 3];

        $mergeResult2      = $testObject->testMergeExtractionResultsWithTracking($cumulativeData, $batch2Data);
        $cumulativeData    = $mergeResult2['merged'];
        $cumulativePageSources = $testObject->testMergePageSourcesForUpdatedFields(
            $cumulativePageSources,
            $batch2PageSources,
            $mergeResult2['updated_fields']
        );

        // After batch 2:
        // - incident_description data should be preserved from batch 1
        $this->assertEquals('Patient was in an accident', $cumulativeData['incident_description']);
        // - incident_description page_source should STILL be page 1 (NOT page 4)
        $this->assertEquals(1, $cumulativePageSources['incident_description']);
        // - diagnosis should show page 3
        $this->assertEquals(3, $cumulativePageSources['diagnosis']);
    }

    // ===========================================
    // Tests for mergeExtractionResultsWithConflicts
    // ===========================================

    #[Test]
    public function merge_with_conflicts_detects_different_meaningful_values(): void
    {
        $testObject = $this->createTestObject();

        $existing            = ['name' => 'Treatment for headaches'];
        $new                 = ['name' => 'Cervical sprains treatment'];
        $existingPageSources = ['name' => 1];
        $newPageSources      = ['name' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // Should have one conflict
        $this->assertCount(1, $result['conflicts']);

        // Conflict should contain both values and page sources
        $conflict = $result['conflicts'][0];
        $this->assertEquals('name', $conflict['field_name']);
        $this->assertEquals('Treatment for headaches', $conflict['existing_value']);
        $this->assertEquals(1, $conflict['existing_page']);
        $this->assertEquals('Cervical sprains treatment', $conflict['new_value']);
        $this->assertEquals(3, $conflict['new_page']);

        // Existing value should be preserved (not overwritten) until conflict is resolved
        $this->assertEquals('Treatment for headaches', $result['merged']['name']);

        // No fields should be marked as updated since we have a conflict
        $this->assertNotContains('name', $result['updated_fields']);
    }

    #[Test]
    public function merge_with_conflicts_does_not_detect_same_values(): void
    {
        $testObject = $this->createTestObject();

        $existing            = ['name' => 'John Smith'];
        $new                 = ['name' => 'john smith']; // Same value, different case
        $existingPageSources = ['name' => 1];
        $newPageSources      = ['name' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // Should have NO conflicts (values are the same after normalization)
        $this->assertEmpty($result['conflicts']);

        // Value should be updated (overwritten) since they're the same
        $this->assertContains('name', $result['updated_fields']);
    }

    #[Test]
    public function merge_with_conflicts_skips_null_new_values(): void
    {
        $testObject = $this->createTestObject();

        $existing            = ['name' => 'John Smith'];
        $new                 = ['name' => null];
        $existingPageSources = ['name' => 1];
        $newPageSources      = ['name' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // No conflict - null values are not meaningful
        $this->assertEmpty($result['conflicts']);

        // Value should be preserved
        $this->assertEquals('John Smith', $result['merged']['name']);

        // No update tracked
        $this->assertEmpty($result['updated_fields']);
    }

    #[Test]
    public function merge_with_conflicts_updates_when_existing_is_null(): void
    {
        $testObject = $this->createTestObject();

        $existing            = ['name' => null];
        $new                 = ['name' => 'John Smith'];
        $existingPageSources = [];
        $newPageSources      = ['name' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // No conflict - existing is not meaningful
        $this->assertEmpty($result['conflicts']);

        // New value should be used
        $this->assertEquals('John Smith', $result['merged']['name']);

        // Update tracked
        $this->assertContains('name', $result['updated_fields']);
    }

    #[Test]
    public function merge_with_conflicts_handles_nested_structures(): void
    {
        $testObject = $this->createTestObject();

        $existing = [
            'care_summary' => [
                'name'        => 'Original care',
                'description' => 'Original description',
            ],
        ];

        $new = [
            'care_summary' => [
                'name'        => 'Different care', // Conflict
                'description' => '', // Not meaningful - should not conflict
            ],
        ];

        $existingPageSources = ['name' => 1, 'description' => 1];
        $newPageSources      = ['name' => 3, 'description' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // Should have one conflict for name (nested field path is care_summary.name)
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals('care_summary.name', $result['conflicts'][0]['field_path']);
        $this->assertEquals('name', $result['conflicts'][0]['field_name']);

        // Original values should be preserved
        $this->assertEquals('Original care', $result['merged']['care_summary']['name']);
        $this->assertEquals('Original description', $result['merged']['care_summary']['description']);
    }

    #[Test]
    public function merge_with_conflicts_tracks_page_sources_correctly(): void
    {
        $testObject = $this->createTestObject();

        // Batch 1: Page 1 has name and description
        $batch1Data        = ['name' => 'Treatment A', 'description' => 'Desc 1'];
        $batch1PageSources = ['name' => 1, 'description' => 1];

        // Batch 2: Page 3 has different name (conflict) but same description
        $batch2Data        = ['name' => 'Treatment B', 'description' => 'Desc 1'];
        $batch2PageSources = ['name' => 3, 'description' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $batch1Data,
            $batch2Data,
            $batch1PageSources,
            $batch2PageSources
        );

        // Conflict for name
        $this->assertCount(1, $result['conflicts']);
        $this->assertEquals('name', $result['conflicts'][0]['field_name']);
        $this->assertEquals(1, $result['conflicts'][0]['existing_page']);
        $this->assertEquals(3, $result['conflicts'][0]['new_page']);

        // description should be updated (same value, no conflict)
        $this->assertContains('description', $result['updated_fields']);
    }

    // ===========================================
    // Tests for valuesAreDifferent
    // ===========================================

    #[Test]
    public function values_are_different_normalizes_strings(): void
    {
        $testObject = $this->createTestObject();

        // Same content, different case
        $this->assertFalse($testObject->testValuesAreDifferent('John Smith', 'john smith'));

        // Same content, with whitespace
        $this->assertFalse($testObject->testValuesAreDifferent('  John Smith  ', 'John Smith'));

        // Actually different
        $this->assertTrue($testObject->testValuesAreDifferent('John Smith', 'Jane Doe'));
    }

    #[Test]
    public function values_are_different_compares_arrays(): void
    {
        $testObject = $this->createTestObject();

        // Same array
        $this->assertFalse($testObject->testValuesAreDifferent(['a', 'b'], ['a', 'b']));

        // Different array
        $this->assertTrue($testObject->testValuesAreDifferent(['a', 'b'], ['a', 'c']));

        // Different order (considered different)
        $this->assertTrue($testObject->testValuesAreDifferent(['a', 'b'], ['b', 'a']));
    }

    #[Test]
    public function values_are_different_handles_mixed_types(): void
    {
        $testObject = $this->createTestObject();

        // Different types
        $this->assertTrue($testObject->testValuesAreDifferent('1', 1));
        $this->assertTrue($testObject->testValuesAreDifferent('true', true));

        // Same integers
        $this->assertFalse($testObject->testValuesAreDifferent(42, 42));

        // Different integers
        $this->assertTrue($testObject->testValuesAreDifferent(42, 43));
    }

    #[Test]
    public function merge_with_conflicts_real_world_scenario(): void
    {
        $testObject = $this->createTestObject();

        // Simulate the conflict scenario from the task description
        // Batch 1 (Page 1): name extracted as one value
        $cumulativeData        = [];
        $cumulativePageSources = [];
        $cumulativeConflicts   = [];

        $batch1Data        = ['name' => 'Treatment for headaches, neck, upper back...'];
        $batch1PageSources = ['name' => 1];

        $mergeResult1          = $testObject->testMergeExtractionResultsWithConflicts(
            $cumulativeData,
            $batch1Data,
            $cumulativePageSources,
            $batch1PageSources
        );
        $cumulativeData        = $mergeResult1['merged'];
        $cumulativePageSources = $testObject->testMergePageSourcesForUpdatedFields(
            $cumulativePageSources,
            $batch1PageSources,
            $mergeResult1['updated_fields']
        );
        $cumulativeConflicts = array_merge($cumulativeConflicts, $mergeResult1['conflicts']);

        // After batch 1: no conflicts yet
        $this->assertEmpty($cumulativeConflicts);
        $this->assertEquals('Treatment for headaches, neck, upper back...', $cumulativeData['name']);

        // Batch 2 (Page 3): name extracted as different value
        $batch2Data        = ['name' => 'Cervical, thoracic, and lumbar sprains...'];
        $batch2PageSources = ['name' => 3];

        $mergeResult2          = $testObject->testMergeExtractionResultsWithConflicts(
            $cumulativeData,
            $batch2Data,
            $cumulativePageSources,
            $batch2PageSources
        );
        $cumulativeData        = $mergeResult2['merged'];
        $cumulativeConflicts   = array_merge($cumulativeConflicts, $mergeResult2['conflicts']);

        // After batch 2: should have a conflict
        $this->assertCount(1, $cumulativeConflicts);

        $conflict = $cumulativeConflicts[0];
        $this->assertEquals('name', $conflict['field_name']);
        $this->assertEquals('Treatment for headaches, neck, upper back...', $conflict['existing_value']);
        $this->assertEquals(1, $conflict['existing_page']);
        $this->assertEquals('Cervical, thoracic, and lumbar sprains...', $conflict['new_value']);
        $this->assertEquals(3, $conflict['new_page']);

        // Original value should be preserved until conflict resolution
        $this->assertEquals('Treatment for headaches, neck, upper back...', $cumulativeData['name']);
    }

    // ===========================================
    // Tests for lookupPageSource
    // ===========================================

    #[Test]
    public function lookup_page_source_finds_by_full_path(): void
    {
        $testObject = $this->createTestObject();

        $pageSources = ['care_summary.name' => 1, 'name' => 5];

        // Should find by full path (more specific)
        $result = $testObject->testLookupPageSource($pageSources, 'care_summary.name', 'name');

        $this->assertEquals(1, $result);
    }

    #[Test]
    public function lookup_page_source_falls_back_to_field_name(): void
    {
        $testObject = $this->createTestObject();

        $pageSources = ['name' => 3];

        // Should find by field name when full path not present
        $result = $testObject->testLookupPageSource($pageSources, 'care_summary.name', 'name');

        $this->assertEquals(3, $result);
    }

    #[Test]
    public function lookup_page_source_returns_null_when_not_found(): void
    {
        $testObject = $this->createTestObject();

        $pageSources = ['other_field' => 3];

        // Should return null when neither path nor name found
        $result = $testObject->testLookupPageSource($pageSources, 'care_summary.name', 'name');

        $this->assertNull($result);
    }

    #[Test]
    public function lookup_page_source_prefers_full_path_over_field_name(): void
    {
        $testObject = $this->createTestObject();

        // Both full path and field name have values
        $pageSources = ['care_summary.name' => 1, 'name' => 10];

        // Should prefer full path (1) over field name (10)
        $result = $testObject->testLookupPageSource($pageSources, 'care_summary.name', 'name');

        $this->assertEquals(1, $result);
    }

    // ===========================================
    // Tests for isMeaningfulValue - placeholder strings
    // ===========================================

    #[Test]
    public function is_meaningful_value_returns_false_for_angle_bracket_null(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('<null>'));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_na_placeholders(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('N/A'));
        $this->assertFalse($testObject->testIsMeaningfulValue('n/a'));
        $this->assertFalse($testObject->testIsMeaningfulValue('na'));
        $this->assertFalse($testObject->testIsMeaningfulValue('NA'));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_none_unknown(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('none'));
        $this->assertFalse($testObject->testIsMeaningfulValue('NONE'));
        $this->assertFalse($testObject->testIsMeaningfulValue('unknown'));
        $this->assertFalse($testObject->testIsMeaningfulValue('UNKNOWN'));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_dash_placeholders(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('-'));
        $this->assertFalse($testObject->testIsMeaningfulValue('--'));
    }

    #[Test]
    public function is_meaningful_value_returns_false_for_whitespace_only(): void
    {
        $testObject = $this->createTestObject();

        $this->assertFalse($testObject->testIsMeaningfulValue('   '));
        $this->assertFalse($testObject->testIsMeaningfulValue("\t"));
        $this->assertFalse($testObject->testIsMeaningfulValue("\n"));
        $this->assertFalse($testObject->testIsMeaningfulValue("  \t\n  "));
    }

    #[Test]
    public function is_meaningful_value_returns_true_for_real_content(): void
    {
        $testObject = $this->createTestObject();

        // Real values should be meaningful
        $this->assertTrue($testObject->testIsMeaningfulValue('John Smith'));
        $this->assertTrue($testObject->testIsMeaningfulValue('Not applicable to this case'));
        $this->assertTrue($testObject->testIsMeaningfulValue('N/A - see notes'));
    }

    // ===========================================
    // Tests for conflict detection with placeholder values
    // ===========================================

    #[Test]
    public function merge_with_conflicts_does_not_conflict_with_placeholder_values(): void
    {
        $testObject = $this->createTestObject();

        $existing            = ['name' => 'Real Name'];
        $new                 = ['name' => '<null>']; // Placeholder that LLM returns when no data
        $existingPageSources = ['name' => 1];
        $newPageSources      = ['name' => 3];

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // No conflict - <null> is not meaningful
        $this->assertEmpty($result['conflicts']);

        // Original value should be preserved
        $this->assertEquals('Real Name', $result['merged']['name']);
    }

    #[Test]
    public function merge_with_conflicts_uses_correct_page_sources_with_full_paths(): void
    {
        $testObject = $this->createTestObject();

        // Page sources keyed by full path
        $existing            = ['care_summary' => ['name' => 'Treatment A']];
        $new                 = ['care_summary' => ['name' => 'Treatment B']];
        $existingPageSources = ['care_summary.name' => 1]; // Full path keying
        $newPageSources      = ['care_summary.name' => 4]; // Full path keying

        $result = $testObject->testMergeExtractionResultsWithConflicts(
            $existing,
            $new,
            $existingPageSources,
            $newPageSources
        );

        // Should have a conflict with correct page sources
        $this->assertCount(1, $result['conflicts']);
        $conflict = $result['conflicts'][0];
        $this->assertEquals('care_summary.name', $conflict['field_path']);
        $this->assertEquals(1, $conflict['existing_page']);
        $this->assertEquals(4, $conflict['new_page']);
    }
}
