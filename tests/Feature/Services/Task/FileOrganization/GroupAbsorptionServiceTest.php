<?php

namespace Tests\Feature\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Services\Task\FileOrganizationMergeService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GroupAbsorptionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private FileOrganizationMergeService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(FileOrganizationMergeService::class);
    }

    #[Test]
    public function does_not_absorb_entire_group_when_only_boundary_files_conflict(): void
    {
        // This test reproduces the bug where an entire group gets absorbed when only a boundary file conflicts.
        //
        // Bug Scenario:
        // - "ME Physical Therapy" group has max confidence 5
        // - "Mountain View Pain Specialists" group has max confidence 5
        // - At page 73 (a boundary file): ME PT has conf 5, MVPS has conf 4
        // - ME PT wins the boundary (5 > 4)
        // - BUG: The entire MVPS group (including pages 127-136 which have NOTHING to do with the boundary)
        //   gets absorbed into ME PT
        // - EXPECTED: Only pages adjacent to the boundary that are part of the same window should be absorbed.
        //   Pages 127-136 should remain as "Mountain View Pain Specialists" because they have confidence 5
        //   and never appeared in any ME PT window.

        // Given: Three windows with specific conflict patterns
        $artifacts = new Collection([
            // Window 1: Pages 71-75 - ME PT group with confidence 5 on all pages
            $this->createWindowArtifact(
                windowStart: 71,
                windowEnd: 75,
                windowFiles: [
                    ['file_id' => 71, 'page_number' => 71],
                    ['file_id' => 72, 'page_number' => 72],
                    ['file_id' => 73, 'page_number' => 73],
                    ['file_id' => 74, 'page_number' => 74],
                    ['file_id' => 75, 'page_number' => 75],
                ],
                groups: [
                    [
                        'name'        => 'ME Physical Therapy',
                        'description' => 'ME PT clinic',
                        'files'       => [
                            ['page_number' => 71, 'confidence' => 5, 'explanation' => 'High confidence ME PT'],
                            ['page_number' => 72, 'confidence' => 5, 'explanation' => 'High confidence ME PT'],
                            ['page_number' => 73, 'confidence' => 5, 'explanation' => 'High confidence ME PT'],
                            ['page_number' => 74, 'confidence' => 5, 'explanation' => 'High confidence ME PT'],
                            ['page_number' => 75, 'confidence' => 5, 'explanation' => 'High confidence ME PT'],
                        ],
                    ],
                ]
            ),
            // Window 2: Pages 73-77 - MVPS group, but page 73 has lower confidence (4) than ME PT (5)
            $this->createWindowArtifact(
                windowStart: 73,
                windowEnd: 77,
                windowFiles: [
                    ['file_id' => 73, 'page_number' => 73],
                    ['file_id' => 74, 'page_number' => 74],
                    ['file_id' => 75, 'page_number' => 75],
                    ['file_id' => 76, 'page_number' => 76],
                    ['file_id' => 77, 'page_number' => 77],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'MVPS clinic',
                        'files'       => [
                            ['page_number' => 73, 'confidence' => 4, 'explanation' => 'Lower confidence MVPS'],
                            ['page_number' => 74, 'confidence' => 4, 'explanation' => 'Lower confidence MVPS'],
                            ['page_number' => 75, 'confidence' => 4, 'explanation' => 'Lower confidence MVPS'],
                            ['page_number' => 76, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 77, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                        ],
                    ],
                ]
            ),
            // Window 3: Pages 127-136 - MVPS group with confidence 5 on ALL pages
            // These pages NEVER appeared in any ME PT window and should NOT be absorbed
            $this->createWindowArtifact(
                windowStart: 127,
                windowEnd: 136,
                windowFiles: [
                    ['file_id' => 127, 'page_number' => 127],
                    ['file_id' => 128, 'page_number' => 128],
                    ['file_id' => 129, 'page_number' => 129],
                    ['file_id' => 130, 'page_number' => 130],
                    ['file_id' => 131, 'page_number' => 131],
                    ['file_id' => 132, 'page_number' => 132],
                    ['file_id' => 133, 'page_number' => 133],
                    ['file_id' => 134, 'page_number' => 134],
                    ['file_id' => 135, 'page_number' => 135],
                    ['file_id' => 136, 'page_number' => 136],
                ],
                groups: [
                    [
                        'name'        => 'Mountain View Pain Specialists',
                        'description' => 'MVPS clinic',
                        'files'       => [
                            ['page_number' => 127, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 128, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 129, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 130, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 131, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 132, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 133, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 134, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 135, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                            ['page_number' => 136, 'confidence' => 5, 'explanation' => 'High confidence MVPS'],
                        ],
                    ],
                ]
            ),
        ]);

        // When
        $mergeResult = $this->service->mergeWindowResults($artifacts);
        $groups      = $mergeResult['groups'];
        $fileMapping = $mergeResult['file_to_group_mapping'];

        // Then: Verify expected behavior
        // 1. Both groups should still exist
        $this->assertCount(2, $groups, 'Expected 2 groups: ME PT and MVPS should remain separate');

        // 2. Find the groups by name
        $mePtGroup   = collect($groups)->firstWhere('name', 'ME Physical Therapy');
        $mvpsGroup   = collect($groups)->firstWhere('name', 'Mountain View Pain Specialists');

        $this->assertNotNull($mePtGroup, 'ME Physical Therapy group should exist');
        $this->assertNotNull($mvpsGroup, 'Mountain View Pain Specialists group should exist');

        // 3. ME PT should have pages 71-75 (won the boundary at page 73)
        $this->assertEquals([71, 72, 73, 74, 75], $mePtGroup['files'], 'ME PT should have pages 71-75');

        // 4. MVPS should have pages 76-77 (from window 2) and 127-136 (from window 3)
        // Pages 73-75 lost to ME PT due to lower confidence at boundary
        $expectedMvpsFiles = array_merge([76, 77], range(127, 136));
        $this->assertEquals(
            $expectedMvpsFiles,
            $mvpsGroup['files'],
            'MVPS should keep pages 76-77 and 127-136 (pages 127-136 were never in conflict with ME PT)'
        );

        // 5. Specifically verify pages 127-136 are still in MVPS
        foreach (range(127, 136) as $pageNumber) {
            $this->assertEquals(
                'Mountain View Pain Specialists',
                $fileMapping[$pageNumber]['group_name'],
                "Page $pageNumber should remain in MVPS (never conflicted with ME PT)"
            );
        }

        // 6. Verify confidence levels are preserved
        foreach (range(127, 136) as $pageNumber) {
            $this->assertEquals(
                5,
                $fileMapping[$pageNumber]['confidence'],
                "Page $pageNumber should maintain confidence 5"
            );
        }
    }

    /**
     * Helper method to create a window artifact for testing.
     */
    private function createWindowArtifact(int $windowStart, int $windowEnd, array $windowFiles, array $groups): Artifact
    {
        return Artifact::factory()->create([
            'json_content' => [
                'groups' => $groups,
            ],
            'meta'         => [
                'window_start' => $windowStart,
                'window_end'   => $windowEnd,
                'window_files' => $windowFiles,
            ],
        ]);
    }
}
