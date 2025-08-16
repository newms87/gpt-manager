<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Prompt\PromptDirective;
use App\Repositories\ThreadRepository;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GoogleDocsTemplateTaskRunnerTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

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

        $this->taskProcess = $this->taskRun->taskProcesses()->create([
            'name'   => 'Test Process',
            'status' => 'pending',
        ]);

        // Ensure the relationship is properly loaded
        $this->taskProcess->load('taskRun.taskDefinition');

        $this->runner = (new GoogleDocsTemplateTaskRunner())
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess);
    }

    public function test_findGoogleDocStoredFile_withTemplateStoredFileIdInJsonContent_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'https://docs.google.com/document/d/test-doc-id-123/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id,
                'other_data'              => 'some value',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }

    public function test_findGoogleDocStoredFile_withTemplateStoredFileIdInMeta_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'https://docs.google.com/document/d/test-doc-meta-456/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => [
                'template_stored_file_id' => $storedFile->id,
                'other_meta'              => 'some value',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }

    public function test_findGoogleDocStoredFile_withStoredFileUrlAsDirectId_returnsFileId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => '1234567890abcdefghijklmnopqrstuvwxyzABCDEF', // 25+ characters for valid Google Doc ID
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
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
        $result = $this->invokeMethod($this->runner, 'findGoogleDocStoredFile', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertInstanceOf(StoredFile::class, $result);
        $this->assertEquals($storedFile->id, $result->id);
    }

    public function test_findGoogleDocStoredFile_withInvalidStoredFileId_fallsBackToTextSearch(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => 99999, // Non-existent ID
            ],
            'text_content' => 'Please use this template: https://docs.google.com/document/d/1AbCdEf2GhIjKlMnOpQrStUvWxYz1234567890/edit',
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When - should fallback to text search without agent validation for this test
        $fileId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInArtifacts', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertEquals('1AbCdEf2GhIjKlMnOpQrStUvWxYz1234567890', $fileId);
    }

    public function test_extractGoogleDocIdFromStoredFile_withValidUrl_returnsDocId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'https://docs.google.com/document/d/1BxCtQqrAQXYZ2345abcdefghijklmnop/edit',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertEquals('1BxCtQqrAQXYZ2345abcdefghijklmnop', $docId);
    }

    public function test_extractGoogleDocIdFromStoredFile_withDirectId_returnsDocId(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => '1BxCtQqrAQXYZ2345abcdefghijklmnop', // Direct Google Doc ID
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertEquals('1BxCtQqrAQXYZ2345abcdefghijklmnop', $docId);
    }

    public function test_extractGoogleDocIdFromStoredFile_withInvalidUrl_returnsNull(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'https://example.com/not-a-google-doc',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'extractGoogleDocIdFromStoredFile', [$storedFile]);

        // Then
        $this->assertNull($docId);
    }

    public function test_searchForGoogleDocIdInArtifacts_withValidGoogleDocUrl_returnsDocId(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Please use this Google Doc: https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
            ]),
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInArtifacts', [$artifacts]);

        // Then
        $this->assertEquals('1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg', $docId);
    }

    public function test_searchForGoogleDocIdInArtifacts_withDriveUrl_returnsDocId(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Template: https://drive.google.com/file/d/2AbCdEf3GhIjKlMnOpQrStUvWxYz1234567890/view',
            ]),
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInArtifacts', [$artifacts]);

        // Then
        $this->assertEquals('2AbCdEf3GhIjKlMnOpQrStUvWxYz1234567890', $docId);
    }

    public function test_searchForGoogleDocIdInArtifacts_withNoValidId_returnsNull(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Some text without any valid Google Doc ID',
            ]),
        ]);

        // When
        $docId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInArtifacts', [$artifacts]);

        // Then
        $this->assertNull($docId);
    }

    public function test_searchForGoogleDocIdInDirectives_withValidGoogleDocUrl_returnsDocId(): void
    {
        // Given
        $directive = PromptDirective::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'directive_text' => 'Use this template: https://docs.google.com/document/d/1DirectiveDocId456789ABCDEF1234567890/edit for all documents',
        ]);

        $taskDirective = TaskDefinitionDirective::create([
            'task_definition_id' => $this->taskDefinition->id,
            'prompt_directive_id' => $directive->id,
            'section' => TaskDefinitionDirective::SECTION_TOP,
            'position' => 1,
        ]);

        $taskDirective->load('directive');
        $directives = collect([$taskDirective]);

        // When
        $docId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInDirectives', [$directives]);

        // Then
        $this->assertEquals('1DirectiveDocId456789ABCDEF1234567890', $docId);
    }

    public function test_searchForGoogleDocIdInDirectives_withNoValidId_returnsNull(): void
    {
        // Given
        $directive = PromptDirective::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'directive_text' => 'Some directive without any Google Doc ID',
        ]);

        $taskDirective = TaskDefinitionDirective::create([
            'task_definition_id' => $this->taskDefinition->id,
            'prompt_directive_id' => $directive->id,
            'section' => TaskDefinitionDirective::SECTION_TOP,
            'position' => 1,
        ]);

        $taskDirective->load('directive');
        $directives = collect([$taskDirective]);

        // When
        $docId = $this->invokeMethod($this->runner, 'searchForGoogleDocIdInDirectives', [$directives]);

        // Then
        $this->assertNull($docId);
    }

    public function test_validateGoogleDocIdWithAgent_withValidAgent_returnsAgentResponse(): void
    {
        // Given
        $googleDocId = 'test-doc-id-123';
        $artifacts   = collect();

        // Mock ThreadRepository and agent response
        $agentThread = AgentThread::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
        ]);

        $responseArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'YES',
        ]);

        $mockThreadRepo = $this->mock(ThreadRepository::class);
        $mockThreadRepo->shouldReceive('create')
            ->once()
            ->andReturn($agentThread);

        $mockThreadRepo->shouldReceive('addMessageToThread')
            ->once();

        // Mock the runAgentThread method to return our response artifact
        $runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['runAgentThread'])
            ->getMock();

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->with($agentThread)
            ->willReturn($responseArtifact);

        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        // When
        $isValid = $this->invokeMethod($runner, 'validateGoogleDocIdWithAgent', [$googleDocId, $artifacts, 'artifacts']);

        // Then
        $this->assertTrue($isValid);
    }

    public function test_validateGoogleDocIdWithAgent_withNoResponse_returnsTrue(): void
    {
        // Given
        $googleDocId = 'test-doc-id-123';
        $artifacts   = collect();

        // Mock agent response failure
        $agentThread = AgentThread::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
        ]);

        $mockThreadRepo = $this->mock(ThreadRepository::class);
        $mockThreadRepo->shouldReceive('create')
            ->once()
            ->andReturn($agentThread);

        $mockThreadRepo->shouldReceive('addMessageToThread')
            ->once();

        // Mock the runAgentThread method to return null (failure)
        $runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['runAgentThread'])
            ->getMock();

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->willReturn(null);

        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        // When
        $isValid = $this->invokeMethod($runner, 'validateGoogleDocIdWithAgent', [$googleDocId, $artifacts, 'artifacts']);

        // Then
        $this->assertTrue($isValid); // Should default to true on failure
    }

    public function test_validateGoogleDocIdWithAgent_withNoAgentConfigured_returnsTrue(): void
    {
        // Given
        $googleDocId = 'test-doc-id-123';
        $artifacts   = collect();

        // Create task definition without agent
        $taskDefinitionWithoutAgent = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'agent_id'         => null,
            'task_runner_name' => GoogleDocsTemplateTaskRunner::RUNNER_NAME,
        ]);

        $runner = (new GoogleDocsTemplateTaskRunner())
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess);

        // Override the task definition
        $this->setProperty($runner, 'taskDefinition', $taskDefinitionWithoutAgent);

        // When
        $isValid = $this->invokeMethod($runner, 'validateGoogleDocIdWithAgent', [$googleDocId, $artifacts, 'artifacts']);

        // Then
        $this->assertTrue($isValid);
    }

    public function test_findOrCreateStoredFileForGoogleDoc_createsNewStoredFile(): void
    {
        // Given
        $googleDocId = 'new-doc-id-123';

        // When
        $storedFile = $this->invokeMethod($this->runner, 'findOrCreateStoredFileForGoogleDoc', [$googleDocId]);

        // Then
        $this->assertInstanceOf(StoredFile::class, $storedFile);
        $this->assertEquals('google', $storedFile->disk);
        $this->assertEquals("Google Doc Template: {$googleDocId}", $storedFile->filename);
        $this->assertEquals("https://docs.google.com/document/d/{$googleDocId}/edit", $storedFile->url);
        $this->assertEquals('application/vnd.google-apps.document', $storedFile->mime);
        $this->assertEquals(0, $storedFile->size);
    }

    public function test_findOrCreateStoredFileForGoogleDoc_findsExistingStoredFile(): void
    {
        // Given
        $googleDocId = 'existing-doc-id-123';
        $filename    = "Google Doc Template: {$googleDocId}";

        $existingStoredFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => $filename,
            'url'      => "https://docs.google.com/document/d/{$googleDocId}/edit",
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // When
        $storedFile = $this->invokeMethod($this->runner, 'findOrCreateStoredFileForGoogleDoc', [$googleDocId]);

        // Then
        $this->assertEquals($existingStoredFile->id, $storedFile->id);
        $this->assertEquals($existingStoredFile->filename, $storedFile->filename);
    }

    public function test_collectDataFromArtifacts_withMultipleArtifacts_combinesAllData(): void
    {
        // Given
        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Customer Data',
            'json_content' => [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
            ],
            'meta'         => [
                'created_date' => '2023-01-01',
                'priority'     => 'high',
            ],
            'text_content' => 'Customer information for processing',
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Order Data',
            'json_content' => [
                'order_id' => '12345',
                'amount'   => 99.99,
            ],
            'meta'         => [
                'status' => 'pending',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $templateData = $this->invokeMethod($this->runner, 'collectDataFromArtifacts', [$artifacts]);

        // Then
        $expected = [
            'name'         => 'John Doe',
            'email'        => 'john@example.com',
            'created_date' => '2023-01-01',
            'priority'     => 'high',
            'content'      => 'Customer information for processing',
            'artifact_name' => 'Order Data', // Last one wins
            'order_id'     => '12345',
            'amount'       => 99.99,
            'status'       => 'pending',
        ];

        $this->assertEquals($expected, $templateData);
    }

    public function test_collectDataFromArtifacts_withEmptyArtifacts_returnsEmptyArray(): void
    {
        // Given
        $artifacts = collect();

        // When
        $templateData = $this->invokeMethod($this->runner, 'collectDataFromArtifacts', [$artifacts]);

        // Then
        $this->assertEquals([], $templateData);
    }

    public function test_mapDataToVariables_withDirectMatches_mapsPerfectly(): void
    {
        // Given
        $templateVariables = ['name', 'email', 'order_id'];
        $templateData      = [
            'name'        => 'John Doe',
            'email'       => 'john@example.com',
            'order_id'    => '12345',
            'extra_field' => 'ignored',
        ];

        // When
        $mappedData = $this->invokeMethod($this->runner, 'mapDataToVariables', [$templateVariables, $templateData]);

        // Then
        $expected = [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'order_id' => '12345',
        ];

        $this->assertEquals($expected, $mappedData);
    }

    public function test_mapDataToVariables_withCaseInsensitiveMatch_mapsCorrectly(): void
    {
        // Given
        $templateVariables = ['Name', 'EMAIL', 'OrderId'];
        $templateData      = [
            'name'    => 'John Doe',
            'email'   => 'john@example.com',
            'orderid' => '12345',
        ];

        // When
        $mappedData = $this->invokeMethod($this->runner, 'mapDataToVariables', [$templateVariables, $templateData]);

        // Then
        $expected = [
            'Name'    => 'John Doe',
            'EMAIL'   => 'john@example.com',
            'OrderId' => '12345',
        ];

        $this->assertEquals($expected, $mappedData);
    }

    public function test_mapDataToVariables_withMissingVariables_fillsWithEmptyString(): void
    {
        // Given
        $templateVariables = ['name', 'email', 'missing_field'];
        $templateData      = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ];

        // When
        $mappedData = $this->invokeMethod($this->runner, 'mapDataToVariables', [$templateVariables, $templateData]);

        // Then
        $expected = [
            'name'          => 'John Doe',
            'email'         => 'john@example.com',
            'missing_field' => '',
        ];

        $this->assertEquals($expected, $mappedData);
    }

    public function test_createOutputArtifact_withValidData_createsArtifactWithCorrectStructure(): void
    {
        // Given
        $newDocument = [
            'document_id' => 'test-doc-123',
            'title'       => 'Test Document',
            'url'         => 'https://docs.google.com/document/d/test-doc-123/edit',
            'created_at'  => '2023-01-01T12:00:00Z',
        ];

        $refinedMapping = [
            'title'     => 'Test Document',
            'variables' => [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
            ],
            'reasoning' => 'Mapped based on available data',
        ];

        // When
        $artifact = $this->invokeMethod($this->runner, 'createOutputArtifact', [$newDocument, $refinedMapping]);

        // Then
        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertEquals($this->user->currentTeam->id, $artifact->team_id);
        $this->assertEquals('Generated Google Doc: Test Document', $artifact->name);

        // Verify meta data
        $this->assertEquals('https://docs.google.com/document/d/test-doc-123/edit', $artifact->meta['google_doc_url']);
        $this->assertEquals('test-doc-123', $artifact->meta['google_doc_id']);
        $this->assertEquals('Test Document', $artifact->meta['document_title']);
        $this->assertEquals('2023-01-01T12:00:00Z', $artifact->meta['created_at']);
        $this->assertEquals($refinedMapping['variables'], $artifact->meta['variable_mapping']);
        $this->assertEquals('Mapped based on available data', $artifact->meta['reasoning']);

        // Verify JSON content
        $this->assertEquals($newDocument, $artifact->json_content['document']);
        $this->assertEquals($refinedMapping, $artifact->json_content['mapping']);

        // Verify text content
        $this->assertStringContainsString('Test Document', $artifact->text_content);
        $this->assertStringContainsString('https://docs.google.com/document/d/test-doc-123/edit', $artifact->text_content);
        $this->assertStringContainsString('John Doe', $artifact->text_content);
    }

    public function test_run_withoutTemplateFound_throwsException(): void
    {
        // Given - no artifacts with template_stored_file_id or valid Google Doc URLs
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['other_data' => 'value'],
            'text_content' => 'Some text without any Google Doc references',
        ]);
        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->load('inputArtifacts');

        // Then
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template could not be resolved. No Google Docs template found in artifacts, text content, or directives.');

        // When
        $this->runner->run();
    }

    public function test_run_withInvalidStoredFileUrl_throwsException(): void
    {
        // Given
        $storedFile = StoredFile::create([
            'team_id'  => $this->user->currentTeam->id,
            'disk'     => 'google',
            'filename' => 'Test Template',
            'url'      => 'invalid-url-format',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id,
            ],
        ]);
        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->load('inputArtifacts');

        // Then
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not extract Google Doc ID from StoredFile URL: invalid-url-format');

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

    /**
     * Helper method to set protected/private properties for testing
     */
    protected function setProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property   = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Helper method to mock agent validation
     */
    protected function mockAgentValidation(bool $isValid): void
    {
        $agentThread = AgentThread::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
        ]);

        $responseArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => $isValid ? 'YES' : 'NO',
        ]);

        $mockThreadRepo = $this->mock(ThreadRepository::class);
        $mockThreadRepo->shouldReceive('create')
            ->andReturn($agentThread);

        $mockThreadRepo->shouldReceive('addMessageToThread');

        // Override the runner to mock runAgentThread
        $this->runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['runAgentThread'])
            ->getMock();

        $this->runner->expects($this->any())
            ->method('runAgentThread')
            ->willReturn($responseArtifact);

        $this->runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);
    }
}