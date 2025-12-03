<?php

namespace Tests\Feature\Services\Task;

use App\Services\Task\FileOrganization\DuplicateGroupDetector;
use Tests\TestCase;

class DuplicateGroupDetectorTest extends TestCase
{
    protected DuplicateGroupDetector $detector;

    public function setUp(): void
    {
        parent::setUp();
        $this->detector = new DuplicateGroupDetector();
    }

    public function test_identifies_location_variant_duplicates()
    {
        $groups = [
            ['name' => 'ME Physical Therapy', 'description' => 'PT services'],
            ['name' => 'ME Physical Therapy (Northglenn)', 'description' => 'PT services'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(1, $candidates);
        $this->assertEquals('ME Physical Therapy', $candidates[0]['group1']);
        $this->assertEquals('ME Physical Therapy (Northglenn)', $candidates[0]['group2']);
        // Location variants get high similarity (>= 0.7 threshold, actual ~0.85-0.95)
        $this->assertGreaterThanOrEqual(0.7, $candidates[0]['similarity']);
    }

    public function test_identifies_substring_duplicates()
    {
        $groups = [
            ['name' => 'ABC Medical Center', 'description' => 'Medical services'],
            ['name' => 'ABC Medical', 'description' => 'Medical services'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(1, $candidates);
        $this->assertGreaterThanOrEqual(0.7, $candidates[0]['similarity']);
    }

    public function test_identifies_very_similar_names()
    {
        $groups = [
            ['name' => 'Mountain View Pain Clinic', 'description' => 'Pain management'],
            ['name' => 'Mountain View Pain Specialists', 'description' => 'Pain management'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(1, $candidates);
        // Very similar names with shared substring should trigger detection
        $this->assertGreaterThanOrEqual(0.7, $candidates[0]['similarity']);
    }

    public function test_does_not_flag_completely_different_names()
    {
        $groups = [
            ['name' => 'Mountain View Pain Specialists', 'description' => 'Pain management'],
            ['name' => 'Ivo DPT', 'description' => 'Physical therapy'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(0, $candidates);
    }

    public function test_handles_multiple_potential_duplicates()
    {
        $groups = [
            ['name' => 'ABC Medical', 'description' => 'Medical services'],
            ['name' => 'ABC Medical (Denver)', 'description' => 'Medical services'],
            ['name' => 'XYZ Clinic', 'description' => 'Clinic services'],
            ['name' => 'XYZ Clinic (Boulder)', 'description' => 'Clinic services'],
            ['name' => 'Unrelated Provider', 'description' => 'Different services'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Should find ABC Medical pair and XYZ Clinic pair
        $this->assertGreaterThanOrEqual(2, count($candidates));

        // Check that ABC Medical variants are paired
        $abcPair = collect($candidates)->first(function ($c) {
            return (str_contains($c['group1'], 'ABC') && str_contains($c['group2'], 'ABC'));
        });
        $this->assertNotNull($abcPair);

        // Check that XYZ Clinic variants are paired
        $xyzPair = collect($candidates)->first(function ($c) {
            return (str_contains($c['group1'], 'XYZ') && str_contains($c['group2'], 'XYZ'));
        });
        $this->assertNotNull($xyzPair);
    }

    public function test_handles_empty_group_names()
    {
        $groups = [
            ['name' => '', 'description' => 'Empty group'],
            ['name' => 'Valid Group', 'description' => 'Valid group'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        // Empty names should be skipped
        $this->assertCount(0, $candidates);
    }

    public function test_prepares_duplicate_for_resolution_with_sample_files()
    {
        $candidate = [
            'group1'     => 'ME Physical Therapy',
            'group2'     => 'ME Physical Therapy (Northglenn)',
            'similarity' => 0.95,
        ];

        $finalGroups = [
            [
                'name'               => 'ME Physical Therapy',
                'description'        => 'PT services',
                'files'              => [1, 2, 3],
                'confidence_summary' => ['avg' => 4.5, 'min' => 4, 'max' => 5],
            ],
            [
                'name'               => 'ME Physical Therapy (Northglenn)',
                'description'        => 'PT services at Northglenn location',
                'files'              => [4, 5],
                'confidence_summary' => ['avg' => 4.0, 'min' => 4, 'max' => 4],
            ],
        ];

        $fileToGroup = [
            1 => ['page_number' => 1, 'description' => 'PT intake form', 'confidence' => 5],
            2 => ['page_number' => 2, 'description' => 'PT notes', 'confidence' => 4],
            3 => ['page_number' => 3, 'description' => 'PT exercises', 'confidence' => 4],
            4 => ['page_number' => 4, 'description' => 'PT evaluation', 'confidence' => 4],
            5 => ['page_number' => 5, 'description' => 'PT plan', 'confidence' => 4],
        ];

        $prepared = $this->detector->prepareDuplicateForResolution($candidate, $finalGroups, $fileToGroup);

        $this->assertArrayHasKey('group1', $prepared);
        $this->assertArrayHasKey('group2', $prepared);
        $this->assertArrayHasKey('similarity', $prepared);

        $this->assertEquals('ME Physical Therapy', $prepared['group1']['name']);
        $this->assertEquals(3, $prepared['group1']['file_count']);
        $this->assertCount(2, $prepared['group1']['sample_files']);

        $this->assertEquals('ME Physical Therapy (Northglenn)', $prepared['group2']['name']);
        $this->assertEquals(2, $prepared['group2']['file_count']);
        $this->assertCount(2, $prepared['group2']['sample_files']);
    }

    public function test_case_insensitive_matching()
    {
        $groups = [
            ['name' => 'ABC Medical', 'description' => 'Medical services'],
            ['name' => 'abc medical', 'description' => 'Medical services'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(1, $candidates);
        // Should be exact match after normalization
        $this->assertEquals(1.0, $candidates[0]['similarity']);
    }

    public function test_punctuation_normalization()
    {
        $groups = [
            ['name' => 'ABC Medical, LLC', 'description' => 'Medical services'],
            ['name' => 'ABC Medical LLC', 'description' => 'Medical services'],
        ];

        $candidates = $this->detector->identifyDuplicateCandidates($groups);

        $this->assertCount(1, $candidates);
        $this->assertGreaterThanOrEqual(0.9, $candidates[0]['similarity']);
    }
}
