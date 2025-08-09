<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class GoogleDocsTemplateTaskRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected Team           $team;
    protected User           $user;
    protected TaskRun        $taskRun;
    protected TaskDefinition $taskDefinition;
    protected Agent          $agent;
    protected TaskProcess    $taskProcess;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user);
        $this->user->currentTeam = $this->team;
        $this->actingAs($this->user);

        // Configure TestAI
        Config::set('ai.models.test-model', [
            'api'     => TestAiApi::class,
            'name'    => 'Test Model',
            'context' => 4096,
            'input'   => 0,
            'output'  => 0,
        ]);

        // Create agent
        $this->agent = Agent::factory()->create([
            'team_id' => $this->team->id,
            'model'   => 'test-model',
        ]);

        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'         => $this->team->id,
            'agent_id'        => $this->agent->id,
            'response_format' => 'json_schema',
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'started_at'         => now()->subMinutes(10),
            'completed_at'       => now(),
        ]);

        // Create task process
        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);
    }

    #[Test]
    public function it_finds_google_doc_file_id_in_json_content()
    {
        // Given - Create artifacts with google_doc_file_id in json_content
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'google_doc_file_id' => '1234567890abcdef',
                'other_data' => 'value',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'different_data' => 'value',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should find the file ID
        $this->assertEquals('1234567890abcdef', $result);
    }

    #[Test]
    public function it_finds_google_doc_file_id_in_meta()
    {
        // Given - Create artifacts with google_doc_file_id in meta
        $artifact1 = Artifact::factory()->create([
            'meta' => [
                'google_doc_file_id' => 'abcdef1234567890',
                'other_meta' => 'value',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'meta' => [
                'different_meta' => 'value',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should find the file ID
        $this->assertEquals('abcdef1234567890', $result);
    }

    #[Test]
    public function it_prioritizes_json_content_over_meta_for_google_doc_file_id()
    {
        // Given - Create artifacts with google_doc_file_id in both json_content and meta
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'google_doc_file_id' => 'json_content_id',
            ],
            'meta' => [
                'google_doc_file_id' => 'meta_id',
            ],
        ]);

        $artifacts = collect([$artifact1]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should prioritize json_content
        $this->assertEquals('json_content_id', $result);
    }

    #[Test]
    public function it_returns_null_when_no_google_doc_file_id_found()
    {
        // Given - Create artifacts without google_doc_file_id
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'other_data' => 'value',
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'meta' => [
                'other_meta' => 'value',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('findGoogleDocFileId');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should return null
        $this->assertNull($result);
    }

    #[Test]
    public function it_extracts_template_variables_from_json_content()
    {
        // Given - Create artifacts with various data in json_content
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Anytown',
                ],
                'google_doc_file_id' => '1234', // Should be excluded from variables
            ],
        ]);

        $artifact2 = Artifact::factory()->create([
            'json_content' => [
                'company' => 'Acme Corp',
                'phone' => '555-1234',
            ],
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('extractTemplateVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should extract all variables with nested objects flattened
        $expected = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'address.street' => '123 Main St',
            'address.city' => 'Anytown',
            'google_doc_file_id' => '1234', // From json_content, not excluded here
            'company' => 'Acme Corp',
            'phone' => '555-1234',
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_extracts_template_variables_from_meta_excluding_google_doc_file_id()
    {
        // Given - Create artifacts with data in meta
        $artifact1 = Artifact::factory()->create([
            'meta' => [
                'title' => 'Project Report',
                'author' => 'Jane Smith',
                'google_doc_file_id' => '5678', // Should be excluded
                'settings' => [
                    'format' => 'PDF',
                    'pages' => 10,
                ],
            ],
        ]);

        $artifacts = collect([$artifact1]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('extractTemplateVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should extract variables from meta but exclude google_doc_file_id
        $expected = [
            'title' => 'Project Report',
            'author' => 'Jane Smith',
            'settings.format' => 'PDF',
            'settings.pages' => 10,
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_extracts_template_variables_from_text_content()
    {
        // Given - Create artifact with text content containing key-value pairs
        $textContent = <<<TEXT
        customer_name: Alice Johnson
        order_id = 12345
        total_amount: $199.99
        delivery_date = 2024-01-15
        
        Some other text that doesn't match the pattern.
        invalid_line_without_separator
        
        special_notes: Please handle with care
        TEXT;

        $artifact1 = Artifact::factory()->create([
            'text_content' => $textContent,
        ]);

        $artifacts = collect([$artifact1]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('extractTemplateVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should extract key-value pairs from text content
        $expected = [
            'customer_name' => 'Alice Johnson',
            'order_id' => '12345',
            'total_amount' => '$199.99',
            'delivery_date' => '2024-01-15',
            'special_notes' => 'Please handle with care',
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_extracts_template_variables_from_all_sources_combined()
    {
        // Given - Create artifacts with data in all sources
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'meta' => [
                'title' => 'Document Title',
                'google_doc_file_id' => '1234', // Should be excluded from meta
            ],
            'text_content' => 'phone: 555-1234',
        ]);

        $artifacts = collect([$artifact1]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('extractTemplateVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should merge all sources
        $expected = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'title' => 'Document Title',
            'phone' => '555-1234',
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_flattens_nested_arrays_with_dot_notation()
    {
        // Given - Create artifact with deeply nested data
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'user' => [
                    'profile' => [
                        'personal' => [
                            'name' => 'John Doe',
                            'age' => 30,
                        ],
                        'contact' => [
                            'email' => 'john@example.com',
                        ],
                    ],
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        $artifacts = collect([$artifact1]);

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('extractTemplateVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $artifacts);

        // Then - Should flatten with proper dot notation
        $expected = [
            'user.profile.personal.name' => 'John Doe',
            'user.profile.personal.age' => 30,
            'user.profile.contact.email' => 'john@example.com',
            'settings.theme' => 'dark',
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_builds_instructions_with_template_variables()
    {
        // Given - Template variables and Google Doc file ID
        $googleDocFileId = '1234567890abcdef';
        $templateVariables = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Acme Corp',
        ];

        // When - Create runner and test the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $googleDocFileId, $templateVariables);

        // Then - Should build proper instructions
        $this->assertStringContainsString('google_docs_create_document_from_template MCP tool', $result);
        $this->assertStringContainsString('Template Document ID: 1234567890abcdef', $result);
        $this->assertStringContainsString('"name": "John Doe"', $result);
        $this->assertStringContainsString('"email": "john@example.com"', $result);
        $this->assertStringContainsString('"company": "Acme Corp"', $result);
        $this->assertStringContainsString('replace ALL template variables', $result);
    }

    #[Test]
    public function it_stores_google_doc_url_from_agent_thread_message()
    {
        // Given - Create artifact and agent thread with message containing Google Doc URL
        $artifact = Artifact::factory()->create([
            'meta' => ['existing' => 'data'],
        ]);

        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
        ]);

        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $agentThread->id,
            'content' => 'I have created the document. You can view it here: https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
            'created_at' => now(),
        ]);

        // When - Create runner and call the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('storeGoogleDocUrl');
        $method->setAccessible(true);

        $method->invoke($runner, $artifact, $agentThread);

        // Then - Should store the URL in artifact meta
        $artifact->refresh();
        $this->assertArrayHasKey('google_doc_url', $artifact->meta);
        $this->assertEquals('https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit', $artifact->meta['google_doc_url']);
        $this->assertEquals('data', $artifact->meta['existing']); // Should preserve existing meta
    }

    #[Test]
    public function it_does_not_store_url_when_no_google_doc_url_found_in_message()
    {
        // Given - Create artifact and agent thread with message without Google Doc URL
        $artifact = Artifact::factory()->create([
            'meta' => ['existing' => 'data'],
        ]);

        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
        ]);

        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $agentThread->id,
            'content' => 'I could not create the document. Please try again.',
            'created_at' => now(),
        ]);

        // When - Create runner and call the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('storeGoogleDocUrl');
        $method->setAccessible(true);

        $method->invoke($runner, $artifact, $agentThread);

        // Then - Should not modify artifact meta
        $artifact->refresh();
        $this->assertArrayNotHasKey('google_doc_url', $artifact->meta);
        $this->assertEquals('data', $artifact->meta['existing']); // Should preserve existing meta
    }

    #[Test]
    public function it_handles_null_meta_when_storing_google_doc_url()
    {
        // Given - Create artifact with null meta
        $artifact = Artifact::factory()->create([
            'meta' => null,
        ]);

        $agentThread = AgentThread::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
        ]);

        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $agentThread->id,
            'content' => 'Document created: https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/',
            'created_at' => now(),
        ]);

        // When - Create runner and call the protected method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('storeGoogleDocUrl');
        $method->setAccessible(true);

        $method->invoke($runner, $artifact, $agentThread);

        // Then - Should create new meta array with URL
        $artifact->refresh();
        $this->assertIsArray($artifact->meta);
        $this->assertArrayHasKey('google_doc_url', $artifact->meta);
        $this->assertEquals('https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/', $artifact->meta['google_doc_url']);
    }

    #[Test]
    public function it_throws_exception_when_no_google_doc_file_id_found_in_run()
    {
        // Given - Create artifacts without google_doc_file_id
        $artifact1 = Artifact::factory()->create([
            'json_content' => ['other_data' => 'value'],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact1->id);

        // When/Then - Should throw exception
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No google_doc_file_id found in any input artifact');

        $runner->run();
    }

    #[Test]
    public function it_successfully_runs_with_valid_google_doc_file_id()
    {
        // Given - Create input artifacts with google_doc_file_id and template variables
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'google_doc_file_id' => '1234567890abcdef',
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact1->id);

        // Mock the ThreadRepository to avoid actual thread operations
        $mockThreadRepo = $this->mock(ThreadRepository::class);
        $mockThreadRepo->shouldReceive('addMessageToThread')
            ->once()
            ->andReturn($this->createMock(AgentThread::class));

        // Mock the AgentThreadService and setupAgentThread/runAgentThread methods
        $mockAgentThread = AgentThread::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
        ]);

        $mockOutputArtifact = Artifact::factory()->create([
            'text_content' => 'Document created successfully',
        ]);

        // Use partial mock to control specific methods while allowing others to work
        $runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['setupAgentThread', 'runAgentThread', 'storeGoogleDocUrl', 'complete'])
            ->getMock();

        $runner->expects($this->once())
            ->method('setupAgentThread')
            ->willReturn($mockAgentThread);

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->with($mockAgentThread)
            ->willReturn($mockOutputArtifact);

        $runner->expects($this->once())
            ->method('storeGoogleDocUrl')
            ->with($mockOutputArtifact, $mockAgentThread);

        $runner->expects($this->once())
            ->method('complete')
            ->with([$mockOutputArtifact]);

        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        // When - Run the task
        $runner->run();

        // Then - Should complete successfully (assertions are in the mock expectations)
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_when_agent_thread_fails_to_create_document()
    {
        // Given - Create input artifacts with google_doc_file_id
        $artifact1 = Artifact::factory()->create([
            'json_content' => [
                'google_doc_file_id' => '1234567890abcdef',
                'name' => 'John Doe',
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact1->id);

        // Mock the ThreadRepository
        $mockThreadRepo = $this->mock(ThreadRepository::class);
        $mockThreadRepo->shouldReceive('addMessageToThread')
            ->once()
            ->andReturn($this->createMock(AgentThread::class));

        $mockAgentThread = AgentThread::factory()->create([
            'team_id' => $this->team->id,
            'agent_id' => $this->agent->id,
        ]);

        // Use partial mock to simulate runAgentThread returning null (failure)
        $runner = $this->getMockBuilder(GoogleDocsTemplateTaskRunner::class)
            ->onlyMethods(['setupAgentThread', 'runAgentThread'])
            ->getMock();

        $runner->expects($this->once())
            ->method('setupAgentThread')
            ->willReturn($mockAgentThread);

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->with($mockAgentThread)
            ->willReturn(null); // Simulate failure

        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        // When/Then - Should throw exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to create document from template');

        $runner->run();
    }

    #[Test]
    public function it_handles_empty_artifacts_collection()
    {
        // Given - Empty artifacts collection
        $artifacts = collect([]);

        // When - Create runner and test methods
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);

        // Test findGoogleDocFileId with empty collection
        $findMethod = $reflection->getMethod('findGoogleDocFileId');
        $findMethod->setAccessible(true);
        $fileIdResult = $findMethod->invoke($runner, $artifacts);

        // Test extractTemplateVariables with empty collection
        $extractMethod = $reflection->getMethod('extractTemplateVariables');
        $extractMethod->setAccessible(true);
        $variablesResult = $extractMethod->invoke($runner, $artifacts);

        // Then - Should handle gracefully
        $this->assertNull($fileIdResult);
        $this->assertEquals([], $variablesResult);
    }

    #[Test]
    public function it_parses_text_content_with_various_separators()
    {
        // Given - Text content with different key-value separators
        $textContent = <<<TEXT
        key1: value with colon
        key2 = value with equals
        key3:multiple:colons:in:value
        key4 = equals = in = value
        
        invalid line without separator
        _private_key: should be included
        123_numeric_key = numeric value
        
        key_with_spaces   :   value with spaces   
        key_with_tabs	=	value with tabs
        TEXT;

        $artifact = Artifact::factory()->create([
            'text_content' => $textContent,
        ]);

        // When - Test parseTextContentVariables method
        $runner = new GoogleDocsTemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($this->taskProcess);

        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('parseTextContentVariables');
        $method->setAccessible(true);

        $result = $method->invoke($runner, $textContent);

        // Then - Should parse various formats correctly
        $expected = [
            'key1' => 'value with colon',
            'key2' => 'value with equals',
            'key3' => 'multiple:colons:in:value',
            'key4' => 'equals = in = value',
            '_private_key' => 'should be included',
            '123_numeric_key' => 'numeric value',
            'key_with_spaces' => 'value with spaces',
            'key_with_tabs' => 'value with tabs',
        ];

        $this->assertEquals($expected, $result);
    }
}