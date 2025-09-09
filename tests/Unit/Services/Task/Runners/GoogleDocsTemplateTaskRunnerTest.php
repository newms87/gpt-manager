<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\ContentSearchResult;
use App\Services\ContentSearch\ContentSearchService;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use Exception;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GoogleDocsTemplateTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TaskDefinition               $taskDefinition;
    protected TaskRun                      $taskRun;
    protected TaskProcess                  $taskProcess;
    protected GoogleDocsTemplateTaskRunner $runner;
    protected Agent                        $agent;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'agent_id'         => $this->agent->id,
            'task_runner_name' => GoogleDocsTemplateTaskRunner::RUNNER_NAME,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'name'   => 'Test Process',
            'status' => 'pending',
        ]);

        // Ensure the relationship is properly loaded
        $this->taskProcess->load('taskRun.taskDefinition');
        $this->taskRun->load('taskDefinition');

        $this->runner = (new GoogleDocsTemplateTaskRunner())
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess);
    }

    public function test_findGoogleDocStoredFile_withTemplateStoredFileIdInJsonContent_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id,
                'additional_instructions' => 'Test instructions',
                'other_data'              => 'Some other data',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile');

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }

    public function test_findGoogleDocStoredFile_withTemplateStoredFileIdInMeta_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['other_data' => 'Some other data'],
            'meta'         => [
                'template_stored_file_id' => $storedFile->id,
                'category'                => 'template',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile');

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }

    public function test_findGoogleDocStoredFile_withStoredFileUrlAsFullDocumentUrl_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit', // Full document URL
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id,
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile');

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }


    public function test_extractGoogleDocIdFromStoredFile_withValidUrl_returnsDocId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertEquals('1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg', $docId);
    }

    public function test_extractGoogleDocIdFromStoredFile_withDirectId_returnsNull(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => '1BxCtQqrAQXYZ2345abcdefghijklmnop', // Direct Google Doc ID - not supported by reverted method
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertNull($docId); // Reverted method only handles /document/d/ URLs
    }

    public function test_extractGoogleDocIdFromStoredFile_withInvalidUrl_returnsNull(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'local',
            'filename' => 'Test Template',
            'url'      => 'https://example.com/not-a-google-doc',
            'mime'     => 'application/pdf',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertNull($docId);
    }


    public function test_createOutputArtifact_withValidData_createsArtifactWithCorrectStructure(): void
    {
        // Given
        $newDocument = [
            'document_id' => 'test-doc-123',
            'url'         => 'https://docs.google.com/document/d/test-doc-123/edit',
            'title'       => 'Test Document',
            'created_at'  => now()->toIsoString(),
        ];

        $refinedMapping = [
            'title'     => 'Test Document',
            'variables' => [
                'client_name' => 'John Doe',
                'date'        => '2024-01-01',
            ],
            'reasoning' => 'Mapped based on available data',
        ];

        // When
        $artifact = $this->invokeMethod($this->runner, 'createOutputArtifact', [$newDocument, $refinedMapping]);

        // Then
        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertStringContainsString('Generated Google Doc: Test Document', $artifact->name);
        $this->assertStringContainsString('Successfully created Google Docs document', $artifact->text_content);
        $this->assertEquals($newDocument['url'], $artifact->meta['google_doc_url']);
        $this->assertEquals($newDocument['document_id'], $artifact->meta['google_doc_id']);
        $this->assertEquals($refinedMapping['variables'], $artifact->meta['variable_mapping']);
    }

    public function test_createGoogleDocsStoredFile_withValidData_createsStoredFileWithCorrectProperties(): void
    {
        // Given
        $newDocument = [
            'document_id' => 'new-google-doc-456',
            'url'         => 'https://docs.google.com/document/d/new-google-doc-456/edit',
            'title'       => 'Generated Document',
            'created_at'  => '2023-01-01T12:00:00Z',
        ];

        // When
        $storedFile = $this->invokeMethod($this->runner, 'createGoogleDocsStoredFile', [$newDocument]);

        // Then
        $this->assertInstanceOf(StoredFile::class, $storedFile);
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertEquals($this->user->id, $storedFile->user_id); // user() returns the authenticated user in test
        $this->assertEquals('external', $storedFile->disk);
        $this->assertEquals('Generated Document.gdoc', $storedFile->filename);
        $this->assertEquals('https://docs.google.com/document/d/new-google-doc-456/edit', $storedFile->url);
        $this->assertEquals('application/vnd.google-apps.document', $storedFile->mime);
        $this->assertEquals(0, $storedFile->size);
        $this->assertEquals('google_docs', $storedFile->meta['type']);
        $this->assertEquals('new-google-doc-456', $storedFile->meta['document_id']);
    }

    public function test_run_withInvalidStoredFileUrl_throwsException(): void
    {
        // Given - Create an invalid Google Doc StoredFile that can be found but has invalid URL
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://example.com/not-a-google-doc', // Invalid Google Docs URL
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id, // Direct reference to the StoredFile ID
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Then - This should fail when extracting Google Doc ID from the invalid URL
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not extract Google Doc ID from StoredFile URL');

        // When
        $this->runner->run();
    }

    public function test_run_withValidGoogleDocTemplate_mockGoogleDocsApi(): void
    {
        // Given - Create a valid Google Doc StoredFile that can be found by ContentSearchService
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
            'meta'    => [
                'template_variables' => [
                    'client_name' => 'Name of the client',
                    'date'        => 'Current date',
                ],
            ],
        ]);

        // Mock GoogleDocsApi for external API calls (this is a 3rd party service)
        $newDocument = [
            'document_id' => 'new-generated-doc-id',
            'url'         => 'https://docs.google.com/document/d/new-generated-doc-id/edit',
            'title'       => 'Generated Test Document',
            'created_at'  => '2024-01-01T12:00:00Z',
        ];

        $this->mock(GoogleDocsApi::class)
            ->shouldReceive('createDocumentFromTemplate')
            ->once()
            ->with(
                '1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg',
                \Mockery::type('array'), // variable mapping
                \Mockery::type('string')  // document title
            )
            ->andReturn($newDocument);

        // Create AgentThread and mock its execution (complex workflow, so partial mock is justified)
        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
        ]);

        // Mock agent response for variable mapping
        $agentResponse = [
            'title' => 'Generated Test Document',
            'variables' => [
                'client_name' => 'John Doe',
                'date'        => '2024-01-01',
            ],
            'reasoning' => 'Mapped variables based on available data',
        ];

        $responseArtifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => json_encode($agentResponse),
        ]);

        // Mock the complex agent workflow parts
        $runner = $this->mock(GoogleDocsTemplateTaskRunner::class)->makePartial();
        $runner->shouldAllowMockingProtectedMethods();
        $runner->shouldReceive('runAgentThread')
            ->once()
            ->andReturn($responseArtifact);
        $runner->shouldReceive('setupAgentThread')
            ->once()
            ->andReturn($agentThread);

        $runner->setTaskRun($this->taskRun);
        $runner->setTaskProcess($this->taskProcess);

        // Create input artifact with direct reference to StoredFile ID
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id, // ContentSearchService should find this
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $runner->run();

        // Then - Verify output artifacts were created
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts;
        
        $this->assertCount(1, $outputArtifacts);
        $outputArtifact = $outputArtifacts->first();
        
        $this->assertStringContainsString('Generated Test Document', $outputArtifact->name);
        $this->assertStringContainsString('Successfully created Google Docs document', $outputArtifact->text_content);
        $this->assertEquals($newDocument['url'], $outputArtifact->meta['google_doc_url']);
        $this->assertEquals($newDocument['document_id'], $outputArtifact->meta['google_doc_id']);
        
        // Verify StoredFile was attached
        $this->assertCount(1, $outputArtifact->storedFiles);
        $attachedStoredFile = $outputArtifact->storedFiles->first();
        $this->assertEquals($newDocument['url'], $attachedStoredFile->url);
        $this->assertEquals('Generated Test Document.gdoc', $attachedStoredFile->filename);
    }

    public function test_run_withNoTemplateFound_throwsException(): void
    {
        // Given - Create input artifact without template_stored_file_id (ContentSearchService won't find template)
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['some_other_data' => 'value'], // No template reference
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Then - ContentSearchService should not find any template
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No Google Doc template found in artifacts or text content.');

        // When
        $this->runner->run();
    }

    /**
     * Helper method to invoke protected/private methods for testing
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
