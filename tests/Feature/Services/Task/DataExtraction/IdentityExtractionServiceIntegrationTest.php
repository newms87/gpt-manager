<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\DataExtraction\DuplicateRecordResolver;
use App\Services\Task\DataExtraction\ExtractionArtifactBuilder;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\DataExtraction\IdentityExtractionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

/**
 * Integration test for IdentityExtractionService
 *
 * This test verifies that artifact content (including text transcodes from images)
 * actually flows through to the agent thread. Unlike the unit tests, we DO NOT mock
 * AgentThreadBuilderService - we let it run the real code and only mock
 * AgentThreadService to capture what messages are actually being sent.
 */
class IdentityExtractionServiceIntegrationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private IdentityExtractionService $service;

    private Agent $agent;

    private SchemaDefinition $schemaDefinition;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        Storage::fake('local');

        // Prevent any real HTTP calls - fail if any slip through the mocks
        Http::fake();

        $this->service = app(IdentityExtractionService::class);

        // Set up common test fixtures
        $this->agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'client_name' => ['type' => 'string'],
                    'client_id'   => ['type' => 'string'],
                ],
            ],
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $this->agent->id,
            'schema_definition_id' => $this->schemaDefinition->id,
            'task_runner_config'   => [
                'extraction_timeout' => 60,
            ],
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    // =========================================================================
    // Integration test - verify transcode content reaches the agent
    // =========================================================================

    #[Test]
    public function execute_includes_text_transcode_content_in_agent_thread_messages(): void
    {
        // Given: Artifact with NO text_content but HAS stored file with text transcode
        // This mimics artifact 33910 from production
        $transcodeContent = 'Provider: ABC Insurance\nPolicy Number: 12345\nClient: John Smith';
        $artifact         = $this->createArtifactWithTextTranscode($transcodeContent);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        // Capture the thread that gets passed to AgentThreadService
        $capturedThread = null;

        // Mock AgentThreadService - capture the thread and return mock success
        $this->mock(AgentThreadService::class, function (MockInterface $mock) use (&$capturedThread) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturnUsing(function (AgentThread $thread) use (&$capturedThread) {
                    $capturedThread = $thread;

                    return $this->createMockThreadRun([
                        'data'         => ['client_name' => 'John Smith'],
                        'search_query' => ['client_name' => '%John%Smith%'],
                    ]);
                });
        });

        // Mock DuplicateRecordResolver - return no match (new record)
        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        // Mock ExtractionProcessOrchestrator
        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        // Mock ExtractionArtifactBuilder
        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When: Execute identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: The thread should have been created and captured
        $this->assertNotNull($capturedThread, 'AgentThread should have been created and passed to run()');
        $this->assertInstanceOf(AgentThread::class, $capturedThread);

        // Get all messages from the thread
        $messages = $capturedThread->messages()->get();

        // Verify at least one message exists
        $this->assertGreaterThan(0, $messages->count(), 'Thread should have at least one message with artifact content');

        // Collect all message content
        $allContent = $messages->map(fn(AgentThreadMessage $m) => $m->content)->implode("\n");

        // The key assertion: transcode content should be in the messages
        // This is what we're testing - does the text transcode content actually reach the agent?
        $this->assertStringContainsString(
            'Provider: ABC Insurance',
            $allContent,
            "Thread messages should contain the text transcode content. Got: $allContent"
        );

        $this->assertStringContainsString(
            'Policy Number: 12345',
            $allContent,
            "Thread messages should contain the text transcode content. Got: $allContent"
        );
    }

    #[Test]
    public function execute_with_artifact_having_no_text_content_and_no_transcodes_has_empty_messages(): void
    {
        // Given: Artifact with NO text_content and NO transcodes (just an image file)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'text_content' => null,
            'json_content' => null,
            'meta'         => null,
        ]);

        // Create an image file but NO transcode
        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/image-' . uniqid() . '.jpg',
            'filename' => 'document-page.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);
        $artifact = $artifact->fresh(['storedFiles']);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        // Capture the thread
        $capturedThread = null;

        // Mock AgentThreadService
        $this->mock(AgentThreadService::class, function (MockInterface $mock) use (&$capturedThread) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturnUsing(function (AgentThread $thread) use (&$capturedThread) {
                    $capturedThread = $thread;

                    // Return empty response since there's no content
                    return $this->createMockThreadRun([
                        'data'         => ['client_name' => ''],
                        'search_query' => ['client_name' => '%%'],
                    ]);
                });
        });

        // When: Execute identity extraction
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then: Should return null because no data was extracted
        $this->assertNull($result);

        // The thread should exist but have no meaningful content
        // (no messages because artifact filtering returned null/empty)
        if ($capturedThread) {
            $messages   = $capturedThread->messages()->get();
            $allContent = $messages->map(fn(AgentThreadMessage $m) => $m->content)->implode("\n");

            // Content should be empty or not contain any document text
            $this->assertStringNotContainsString(
                'Provider',
                $allContent,
                'Empty artifact should not produce document content in messages'
            );
        }
    }

    #[Test]
    public function execute_with_text_content_includes_text_in_thread(): void
    {
        // Given: Artifact with text_content (simpler case)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'text_content' => 'Direct text content: Provider XYZ, Client: Jane Doe',
            'json_content' => null,
            'meta'         => null,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        $capturedThread = null;

        $this->mock(AgentThreadService::class, function (MockInterface $mock) use (&$capturedThread) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturnUsing(function (AgentThread $thread) use (&$capturedThread) {
                    $capturedThread = $thread;

                    return $this->createMockThreadRun([
                        'data'         => ['client_name' => 'Jane Doe'],
                        'search_query' => ['client_name' => '%Jane%Doe%'],
                    ]);
                });
        });

        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When
        $result = $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Then
        $this->assertNotNull($capturedThread);

        $messages   = $capturedThread->messages()->get();
        $allContent = $messages->map(fn(AgentThreadMessage $m) => $m->content)->implode("\n");

        $this->assertStringContainsString(
            'Direct text content',
            $allContent,
            "Thread messages should contain the artifact text_content. Got: $allContent"
        );
    }

    // =========================================================================
    // Diagnostic tests - verify actual content structure
    // =========================================================================

    #[Test]
    public function diagnostic_print_message_content_for_transcode_artifact(): void
    {
        // Given: Artifact with text transcode
        $transcodeContent = "Provider: ABC Insurance\nPolicy Number: 12345\nClient: John Smith";
        $artifact         = $this->createArtifactWithTextTranscode($transcodeContent);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($artifact->id);

        $identityGroup = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'identity_fields'   => ['client_name'],
            'fragment_selector' => [
                'children' => ['client_name' => ['type' => 'string']],
            ],
        ];

        $capturedThread = null;

        $this->mock(AgentThreadService::class, function (MockInterface $mock) use (&$capturedThread) {
            $mock->shouldReceive('withResponseFormat')->andReturnSelf();
            $mock->shouldReceive('withTimeout')->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturnUsing(function (AgentThread $thread) use (&$capturedThread) {
                    $capturedThread = $thread;

                    return $this->createMockThreadRun([
                        'data'         => ['client_name' => 'John Smith'],
                        'search_query' => ['client_name' => '%John%Smith%'],
                    ]);
                });
        });

        $this->mock(DuplicateRecordResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('findCandidates')->andReturn(collect());
        });

        $this->mock(ExtractionProcessOrchestrator::class, function (MockInterface $mock) {
            $mock->shouldReceive('storeResolvedObjectId')->once();
        });

        $this->mock(ExtractionArtifactBuilder::class, function (MockInterface $mock) {
            $mock->shouldReceive('buildIdentityArtifact')->once()->andReturn(
                Artifact::factory()->create(['team_id' => $this->user->currentTeam->id])
            );
        });

        // When
        $this->service->execute(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            level: 0
        );

        // Diagnostic output
        $this->assertNotNull($capturedThread);
        $messages = $capturedThread->messages()->get();

        // Output each message for debugging
        foreach ($messages as $index => $message) {
            // This helps diagnose what's actually in the messages
            $contentLength = strlen($message->content);
            $this->assertGreaterThan(0, $contentLength, "Message $index should have content");
        }

        // Verify structure
        $allContent = $messages->map(fn(AgentThreadMessage $m) => $m->content)->implode("\n");

        // The content should contain our transcode text
        $this->assertStringContainsString('ABC Insurance', $allContent);

        // Verify the text_transcodes key format is present
        $this->assertStringContainsString('=== File:', $allContent, 'Should have formatted file header from transcode');
    }

    #[Test]
    public function artifact_filter_in_identity_extraction_includes_text_transcodes_by_default(): void
    {
        // This test verifies the ArtifactFilter configuration used in IdentityExtractionService
        // The filter is: ArtifactFilter(includeFiles: false, includeJson: false, includeMeta: false)
        // Which means includeText=true and includeTextTranscodes=true by default

        $filter = new \App\Services\AgentThread\ArtifactFilter(
            includeFiles: false,
            includeJson: false,
            includeMeta: false
        );

        // Verify defaults
        $this->assertTrue($filter->includeText, 'includeText should default to true');
        $this->assertTrue($filter->includeTextTranscodes, 'includeTextTranscodes should default to true');

        // Verify explicit settings
        $this->assertFalse($filter->includeFiles, 'includeFiles should be false');
        $this->assertFalse($filter->includeJson, 'includeJson should be false');
        $this->assertFalse($filter->includeMeta, 'includeMeta should be false');
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Create an artifact with a stored file that has a text transcode.
     * This mimics the structure of artifact 33910 from production.
     */
    private function createArtifactWithTextTranscode(string $transcodeContent): Artifact
    {
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'text_content' => null,  // No direct text content
            'json_content' => null,
            'meta'         => null,
        ]);

        // Create original stored file (the image)
        $storedFile = StoredFile::factory()->create([
            'disk'     => 'local',
            'filepath' => 'test/image-' . uniqid() . '.jpg',
            'filename' => 'Page 1 -- document.jpg',
            'mime'     => 'image/jpeg',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        // Create transcode file and put content
        $transcodePath = 'test/transcode-' . uniqid() . '.txt';
        Storage::disk('local')->put($transcodePath, $transcodeContent);

        // Create the transcode StoredFile (text representation of the image)
        StoredFile::factory()->create([
            'disk'                    => 'local',
            'filepath'                => $transcodePath,
            'filename'                => 'Page 1 -- document.image-to-text-transcode.txt',
            'mime'                    => 'text/plain',
            'original_stored_file_id' => $storedFile->id,
            'transcode_name'          => 'Image To Text LLM',
        ]);

        return $artifact->fresh(['storedFiles']);
    }

    /**
     * Create a mock AgentThreadRun with the given response data.
     */
    private function createMockThreadRun(array $responseData): AgentThreadRun
    {
        $mockMessage = $this->createMock(AgentThreadMessage::class);
        $mockMessage->method('getJsonContent')->willReturn($responseData);

        $mockThreadRun              = $this->mock(AgentThreadRun::class)->makePartial();
        $mockThreadRun->lastMessage = $mockMessage;
        $mockThreadRun->shouldReceive('isCompleted')->andReturn(true);

        return $mockThreadRun;
    }
}
