<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\ContentSearchResult;
use App\Services\ContentSearch\ContentSearchService;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GoogleDocsTemplateTaskRunnerTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected TaskDefinition               $taskDefinition;
    protected TaskRun                      $taskRun;
    protected TaskProcess                  $taskProcess;
    protected GoogleDocsTemplateTaskRunner $runner;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'agent_id'         => $agent->id,
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

    public function test_findGoogleDocFileId_withFileIdInJsonContent_returnsFileId(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'google_doc_file_id' => 'test-doc-id-123',
                'other_data'         => 'some value',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Mock ContentSearchService to return the file ID
        $successResult = ContentSearchResult::fieldPathFound('test-doc-id-123', $artifact, 'json_content.google_doc_file_id');
        $mockSearchService = $this->mock(ContentSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with(\Mockery::type(ContentSearchRequest::class))
            ->andReturn($successResult);

        // When
        $fileId = $this->invokeMethod($this->runner, 'findGoogleDocFileId', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertEquals('test-doc-id-123', $fileId);
    }

    public function test_findGoogleDocFileId_withFileIdInMeta_returnsFileId(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => [
                'google_doc_file_id' => 'test-doc-meta-456',
                'other_meta'         => 'some value',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Mock ContentSearchService to return the file ID from meta
        $successResult = ContentSearchResult::fieldPathFound('test-doc-meta-456', $artifact, 'meta.google_doc_file_id');
        $mockSearchService = $this->mock(ContentSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with(\Mockery::type(ContentSearchRequest::class))
            ->andReturn($successResult);
        
        // When
        $fileId = $this->invokeMethod($this->runner, 'findGoogleDocFileId', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertEquals('test-doc-meta-456', $fileId);
    }

    public function test_findGoogleDocFileId_withNoFileId_returnsNull(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['other_data' => 'value'],
            'meta'         => ['other_meta' => 'value'],
            'text_content' => 'Some text without any file ID',
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Mock ContentSearchService to return no results
        $mockSearchService = $this->mock(ContentSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->twice() // Once for artifacts, once for directives
            ->andReturn(ContentSearchResult::notFound('No file ID found'));

        // When
        $fileId = $this->invokeMethod($this->runner, 'findGoogleDocFileId', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertNull($fileId);
    }

    public function test_findGoogleDocFileId_withFileIdFromContentSearchService_returnsFileId(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'text_content' => 'Please use this Google Doc: https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Mock ContentSearchService to return the file ID via regex/LLM extraction
        $successResult = ContentSearchResult::regexFound('1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg', $artifact, '/(?:docs\.google\.com\/document\/d\/|drive\.google\.com\/file\/d\/)?([a-zA-Z0-9_-]{25,60})/')
            ->setValidated(true);
        $mockSearchService = $this->mock(ContentSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with(\Mockery::type(ContentSearchRequest::class))
            ->andReturn($successResult);
        
        // When
        $fileId = $this->invokeMethod($this->runner, 'findGoogleDocFileId', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertEquals('1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg', $fileId);
    }

    public function test_collectTemplateData_withMultipleArtifacts_combinesAllData(): void
    {
        // Given
        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'name'               => 'John Doe',
                'email'              => 'john@example.com',
                'google_doc_file_id' => 'should-be-excluded',
            ],
            'meta'         => [
                'created_date'       => '2023-01-01',
                'google_doc_file_id' => 'should-also-be-excluded',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'order_id' => '12345',
                'amount'   => 99.99,
            ],
            'meta'         => [
                'priority' => 'high',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $templateData = $this->invokeMethod($this->runner, 'collectTemplateData', [$artifacts]);

        // Then
        $expected = [
            'name'         => 'John Doe',
            'email'        => 'john@example.com',
            'created_date' => '2023-01-01',
            'order_id'     => '12345',
            'amount'       => 99.99,
            'priority'     => 'high',
        ];

        $this->assertEquals($expected, $templateData);
        $this->assertArrayNotHasKey('google_doc_file_id', $templateData);
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

    public function test_run_withoutGoogleDocFileId_throwsException(): void
    {
        // Given - no artifacts with google_doc_file_id
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['other_data' => 'value'],
        ]);
        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->load('inputArtifacts');

        // Mock ContentSearchService to return unsuccessful results
        $notFoundResult = ContentSearchResult::notFound('No file ID found');
        $mockSearchService = $this->mock(ContentSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->andReturn($notFoundResult);

        // Then
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No google_doc_file_id found in any input artifact');

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
