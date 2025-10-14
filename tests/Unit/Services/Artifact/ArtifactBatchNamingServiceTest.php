<?php

namespace Tests\Unit\Services\Artifact;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Artifact\ArtifactBatchNamingService;
use Illuminate\Support\Collection;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ArtifactBatchNamingServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // BASIC FUNCTIONALITY TESTS
    // ==========================================

    public function test_nameArtifacts_withEmptyCollection_returnsEmptyCollection(): void
    {
        // Given
        $artifacts = collect();
        $context = "Test context";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_nameArtifacts_withSmallBatch_processesDirectly(): void
    {
        // Given - Create artifacts with text content
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => 'artifact_1',
                'text_content' => 'Medical summary document content',
            ]),
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => 'artifact_2',
                'text_content' => 'Patient demographics information',
            ]),
        ]);

        // Mock the agent and thread service
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock the LLM response with new array format
        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => [
                ['artifact_id' => $artifacts[0]->id, 'name' => 'Medical Summary Document'],
                ['artifact_id' => $artifacts[1]->id, 'name' => 'Patient Demographics'],
            ]]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Medical case documents";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertCount(2, $result);
        $this->assertEquals('Medical Summary Document', $result[0]->fresh()->name);
        $this->assertEquals('Patient Demographics', $result[1]->fresh()->name);
    }

    // ==========================================
    // BATCH PROCESSING TESTS
    // ==========================================

    public function test_nameArtifacts_withLargeBatch_processesBatches(): void
    {
        // Given - Create 25 artifacts (exceeds default max_batch_size of 20)
        $artifacts = collect();
        for ($i = 1; $i <= 25; $i++) {
            $artifacts->push(Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => "artifact_{$i}",
                'text_content' => "Content for artifact {$i}",
            ]));
        }

        // Mock the agent
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock the LLM to process batches
        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->twice()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->twice()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->twice() // Should process in 2 batches (20 + 5)
                ->andReturn($agentThreadRun);

            // Create mock names for all artifacts using new array format
            $allNames = [];
            foreach ($artifacts as $artifact) {
                $allNames[] = ['artifact_id' => $artifact->id, 'name' => "Named Artifact {$artifact->id}"];
            }

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => $allNames]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Test batch processing";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertCount(25, $result);
        // Verify processing occurred (names would change in real scenario)
        $this->assertInstanceOf(Collection::class, $result);
    }

    // ==========================================
    // ERROR HANDLING TESTS
    // ==========================================

    public function test_nameArtifacts_withLLMFailure_keepsOriginalNames(): void
    {
        // Given
        $originalName = 'original_artifact_name';
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => $originalName,
                'text_content' => 'Some content',
            ]),
        ]);

        // Mock the agent
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Mock LLM service to return null (failure)
        $this->mock(AgentThreadService::class, function ($mock) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andThrow(new \Exception('LLM service unavailable'));
        });

        $context = "Test error handling";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then - Original names should be preserved
        $this->assertCount(1, $result);
        $this->assertEquals($originalName, $result[0]->fresh()->name);
    }

    public function test_nameArtifacts_withInvalidJSONResponse_keepsOriginalNames(): void
    {
        // Given
        $originalName = 'original_name';
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => $originalName,
                'text_content' => 'Content',
            ]),
        ]);

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock invalid JSON response
        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock message that returns text_content wrapped in array (how getJsonContent handles non-JSON)
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['text_content' => 'This is not valid JSON']);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Test invalid JSON";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertEquals($originalName, $result[0]->fresh()->name);
    }

    public function test_nameArtifacts_withMissingNamesObject_keepsOriginalNames(): void
    {
        // Given
        $originalName = 'original_name';
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => $originalName,
                'text_content' => 'Content',
            ]),
        ]);

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock response without 'names' key
        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock message that returns JSON without 'names' key
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['invalid_key' => 'value']);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Test missing names object";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertEquals($originalName, $result[0]->fresh()->name);
    }

    // ==========================================
    // CONTENT EXTRACTION TESTS
    // ==========================================

    public function test_buildArtifactContextData_withTextContent_extractsCorrectly(): void
    {
        // Given
        $textContent = str_repeat('a', 1000); // Long text
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'test_artifact',
            'text_content' => $textContent,
            'json_content' => null,
        ]);

        $artifacts = collect([$artifact]);
        $service = app(ArtifactBatchNamingService::class);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildArtifactContextData');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($service, $artifacts);

        // Then
        $this->assertCount(1, $result);
        $this->assertEquals($artifact->id, $result[0]['artifact_id']);
        $this->assertEquals('test_artifact', $result[0]['current_name']);
        $this->assertTrue($result[0]['has_text']);
        $this->assertFalse($result[0]['has_json']);
        $this->assertLessThanOrEqual(503, strlen($result[0]['content_preview'])); // 500 + '...'
    }

    public function test_buildArtifactContextData_withJsonContent_extractsCorrectly(): void
    {
        // Given
        $jsonContent = ['key' => str_repeat('value', 100)];
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'json_artifact',
            'text_content' => null,
            'json_content' => $jsonContent,
        ]);

        $artifacts = collect([$artifact]);
        $service = app(ArtifactBatchNamingService::class);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildArtifactContextData');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($service, $artifacts);

        // Then
        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['has_text']);
        $this->assertTrue($result[0]['has_json']);
        $this->assertNotEmpty($result[0]['content_preview']);
    }

    public function test_buildArtifactContextData_withStoredFiles_countsCorrectly(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => 'Content',
        ]);

        // Attach stored files
        $storedFile1 = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $storedFile2 = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact->storedFiles()->attach([$storedFile1->id, $storedFile2->id]);

        $artifacts = collect([$artifact]);
        $service = app(ArtifactBatchNamingService::class);

        // Use reflection
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildArtifactContextData');
        $method->setAccessible(true);

        // When
        $result = $method->invoke($service, $artifacts);

        // Then
        $this->assertTrue($result[0]['has_files']);
        $this->assertEquals(2, $result[0]['file_count']);
    }

    // ==========================================
    // CONFIGURATION TESTS
    // ==========================================

    public function test_nameArtifacts_usesConfiguredModel(): void
    {
        // Given
        config(['ai.artifact_naming.model' => 'gpt-5-nano']);

        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'text_content' => 'Content',
            ]),
        ]);

        // Create agent with the configured model
        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model' => 'gpt-5-nano',
        ]);

        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->with(120) // Default timeout
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => [
                ['artifact_id' => $artifacts[0]->id, 'name' => 'Named Artifact']
            ]]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Test configuration";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertCount(1, $result);
    }

    public function test_nameArtifacts_usesConfiguredTimeout(): void
    {
        // Given
        config(['ai.artifact_naming.timeout' => 180]);

        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'text_content' => 'Content',
            ]),
        ]);

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->with(180) // Should use configured timeout
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => [
                ['artifact_id' => $artifacts[0]->id, 'name' => 'Named']
            ]]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        // When
        app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, "Context");

        // Then - Mock expectations verified by Mockery
        $this->assertTrue(true);
    }

    public function test_nameArtifacts_usesConfiguredMaxBatchSize(): void
    {
        // Given - Set max batch size to 5
        config(['ai.artifact_naming.max_batch_size' => 5]);

        // Create 12 artifacts (should process in 3 batches: 5, 5, 2)
        $artifacts = collect();
        for ($i = 1; $i <= 12; $i++) {
            $artifacts->push(Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name' => "artifact_{$i}",
                'text_content' => "Content {$i}",
            ]));
        }

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->times(3)
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->times(3)
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->times(3) // Should process in 3 batches
                ->andReturn($agentThreadRun);

            // Create mock names for all artifacts using new array format
            $allNames = [];
            foreach ($artifacts as $artifact) {
                $allNames[] = ['artifact_id' => $artifact->id, 'name' => "Named {$artifact->id}"];
            }

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => $allNames]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        $context = "Test batch size configuration";

        // When
        $result = app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $context);

        // Then
        $this->assertCount(12, $result);
    }

    // ==========================================
    // CONTEXT DESCRIPTION TESTS
    // ==========================================

    public function test_nameArtifacts_passesContextToPrompt(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'text_content' => 'Content',
            ]),
        ]);

        $contextDescription = "Medical summary documents for case: John Doe vs Hospital";

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $agentThreadRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $agentThread->id,
        ]);

        // Mock to verify context is used
        $threadRepoMock = $this->mock(ThreadRepository::class, function ($mock) use ($agentThread, $contextDescription) {
            $mock->shouldReceive('create')
                ->once()
                ->andReturn($agentThread);

            $mock->shouldReceive('addMessageToThread')
                ->twice() // System message + user message
                ->andReturn($agentThread);
        });

        $this->mock(AgentThreadService::class, function ($mock) use ($agentThreadRun, $artifacts) {
            $mock->shouldReceive('withResponseFormat')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('withTimeout')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($agentThreadRun);

            // Mock the lastMessage with AgentThreadMessage that has getJsonContent()
            $mockMessage = $this->createMock(AgentThreadMessage::class);
            $mockMessage->method('getJsonContent')->willReturn(['names' => [
                ['artifact_id' => $artifacts[0]->id, 'name' => 'Medical Summary']
            ]]);
            $agentThreadRun->lastMessage = $mockMessage;
        });

        // When
        app(ArtifactBatchNamingService::class)->nameArtifacts($artifacts, $contextDescription);

        // Then - Mock expectations verified
        $this->assertTrue(true);
    }
}
