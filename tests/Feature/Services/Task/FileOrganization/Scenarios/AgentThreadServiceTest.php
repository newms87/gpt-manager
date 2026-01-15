<?php

namespace Tests\Feature\Services\Task\FileOrganization\Scenarios;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\AgentThreadService;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Tests for AgentThreadService file organization thread setup.
 *
 * These tests verify that agent threads are created correctly with proper
 * message and file attachments for various file organization processes.
 */
class AgentThreadServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private AgentThreadService $agentThreadService;

    private Agent $agent;

    private TaskDefinition $taskDefinition;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->agentThreadService = app(AgentThreadService::class);

        // Create agent with valid model
        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => self::TEST_MODEL,
        ]);

        // Create task definition with agent
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
        ]);
    }

    #[Test]
    public function duplicate_group_resolution_attaches_sample_images_to_thread(): void
    {
        // GIVEN: A task run with input artifacts that have stored files with page numbers
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create 3 input artifacts with stored files that have page_number set
        $storedFiles = [];
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name'    => "Page $i",
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);

            $storedFiles[$i] = $storedFile;
        }

        // Create groups for deduplication that reference those page numbers
        // Group A has 2 sample files (pages 1 and 2), Group B has 1 sample file (page 3)
        $groupsForDeduplication = [
            [
                'name'         => 'Test Group A',
                'description'  => 'Test Group A (3 pages: 1-3)',
                'file_count'   => 3,
                'sample_files' => [
                    [
                        'page_number' => 1,
                        'confidence'  => 5,
                        'description' => 'Page 1 description',
                    ],
                    [
                        'page_number' => 2,
                        'confidence'  => 4,
                        'description' => 'Page 2 description',
                    ],
                ],
                'confidence'   => null,
            ],
            [
                'name'         => 'Test Group B',
                'description'  => 'Test Group B (1 page: 3)',
                'file_count'   => 1,
                'sample_files' => [
                    [
                        'page_number' => 3,
                        'confidence'  => 5,
                        'description' => 'Page 3 description',
                    ],
                ],
                'confidence'   => null,
            ],
        ];

        // WHEN: Setup the duplicate group resolution thread
        $agentThread = $this->agentThreadService->setupDuplicateGroupResolutionThread(
            $this->taskDefinition,
            $taskRun,
            $groupsForDeduplication
        );

        // THEN: Verify that messages with stored files were created
        // Reload the thread with messages and their stored files
        $agentThread->load('messages.storedFiles');

        // Get all messages that have stored files attached
        $messagesWithFiles = $agentThread->messages()
            ->whereHas('storedFiles')
            ->with('storedFiles')
            ->get();

        // Assert: We should have messages with attached files for the sample images
        $this->assertGreaterThan(
            0,
            $messagesWithFiles->count(),
            'Expected at least one message with attached stored files for sample images. ' .
            'The thread should include messages with the sample images from the groups.'
        );

        // More specific assertion: we expect exactly 3 messages with files
        // (2 samples from Group A + 1 sample from Group B)
        $totalSampleFiles = 0;
        foreach ($groupsForDeduplication as $group) {
            $totalSampleFiles += count($group['sample_files'] ?? []);
        }

        $this->assertEquals(
            $totalSampleFiles,
            $messagesWithFiles->count(),
            "Expected $totalSampleFiles messages with attached files (one per sample image). " .
            "Found {$messagesWithFiles->count()} messages. This indicates images are not being attached properly."
        );

        // Verify each message has exactly one stored file attached
        foreach ($messagesWithFiles as $message) {
            $this->assertEquals(
                1,
                $message->storedFiles->count(),
                'Each sample image message should have exactly 1 stored file attached. ' .
                "Message ID {$message->id} has {$message->storedFiles->count()} files."
            );
        }

        // Verify the correct stored files are attached (by page number)
        $attachedPageNumbers = $messagesWithFiles
            ->flatMap(fn($msg) => $msg->storedFiles->pluck('page_number'))
            ->sort()
            ->values()
            ->toArray();

        $expectedPageNumbers = [];
        foreach ($groupsForDeduplication as $group) {
            foreach ($group['sample_files'] ?? [] as $sample) {
                $expectedPageNumbers[] = $sample['page_number'];
            }
        }
        sort($expectedPageNumbers);

        $this->assertEquals(
            $expectedPageNumbers,
            $attachedPageNumbers,
            'The attached stored files should match the page numbers from sample_files. ' .
            'Expected pages: ' . implode(', ', $expectedPageNumbers) . '. ' .
            'Found pages: ' . implode(', ', $attachedPageNumbers) . '.'
        );
    }

    #[Test]
    public function comparison_window_thread_attaches_artifact_images(): void
    {
        // GIVEN: A task run with artifacts that have stored files
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        // Create 3 artifacts with stored files for comparison window
        $artifacts = collect();
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name'    => "Page $i",
            ]);

            $storedFile = StoredFile::factory()->create([
                'page_number' => $i,
                'filename'    => "page-$i.jpg",
                'filepath'    => "test/page-$i.jpg",
                'disk'        => 'public',
                'mime'        => 'image/jpeg',
            ]);

            $artifact->storedFiles()->attach($storedFile->id, ['category' => 'input']);
            $artifacts->push($artifact);
        }

        // WHEN: Setup comparison window thread
        $agentThread = $this->agentThreadService->setupComparisonWindowThread(
            $this->taskDefinition,
            $taskRun,
            $artifacts
        );

        // THEN: Verify messages with files were created
        $messagesWithFiles = $agentThread->messages()
            ->whereHas('storedFiles')
            ->with('storedFiles')
            ->get();

        $this->assertEquals(
            3,
            $messagesWithFiles->count(),
            'Expected 3 messages with attached files (one per artifact). ' .
            "Found {$messagesWithFiles->count()} messages."
        );

        // Verify each message has the correct stored file
        foreach ($messagesWithFiles as $index => $message) {
            $this->assertEquals(
                1,
                $message->storedFiles->count(),
                'Message should have exactly 1 attached file'
            );
        }
    }
}
