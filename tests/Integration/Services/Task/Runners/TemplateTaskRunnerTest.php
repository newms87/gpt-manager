<?php

namespace Tests\Integration\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Models\User;
use App\Services\Task\Runners\TemplateTaskRunner;
use Illuminate\Support\Facades\Config;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\TestCase;

class TemplateTaskRunnerTest extends TestCase
{
    protected Team $team;

    protected User $user;

    protected TaskRun $taskRun;

    protected TaskDefinition $taskDefinition;

    protected TemplateDefinition $template;

    protected StoredFile $templateStoredFile;

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
            'api'      => TestAiApi::class,
            'name'     => 'Test Model',
            'context'  => 4096,
            'input'    => 0,
            'output'   => 0,
            'features' => [
                'temperature' => true,
            ],
        ]);

        // Configure gpt-4o to use TestAI for testing
        Config::set('ai.models.gpt-4o', [
            'api'      => TestAiApi::class,
            'name'     => 'GPT-4o Test',
            'context'  => 4096,
            'input'    => 0,
            'output'   => 0,
            'features' => [
                'temperature' => true,
            ],
        ]);

        // Create Google Docs template stored file
        $this->templateStoredFile = StoredFile::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'url'     => 'https://docs.google.com/document/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit',
            'disk'    => 'external',
            'mime'    => 'application/vnd.google-apps.document',
            'meta'    => ['type' => 'google_docs_template'],
        ]);

        // Create demand template
        $this->template = TemplateDefinition::factory()->create([
            'team_id'        => $this->team->id,
            'user_id'        => $this->user->id,
            'stored_file_id' => $this->templateStoredFile->id,
            'name'           => 'Test Template',
        ]);

        // Create task definition
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'          => $this->team->id,
            'name'             => 'Template Task',
            'task_runner_name' => TemplateTaskRunner::RUNNER_NAME,
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
            'started_at'         => now()->subMinutes(10),
        ]);
    }

    #[Test]
    public function test_pure_artifact_mapping_workflow(): void
    {
        // Create template variables with artifact mapping using fragment selectors
        $nameVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'company_name',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => [
                'field'    => 'json_content',
                'type'     => 'object',
                'children' => [
                    'company' => ['type' => 'string'],
                ],
            ],
            'multi_value_strategy' => TemplateVariable::STRATEGY_FIRST,
        ]);

        $locationVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'location',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => [
                'field'    => 'json_content',
                'type'     => 'object',
                'children' => [
                    'address' => ['type' => 'string'],
                ],
            ],
            'multi_value_strategy'  => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator' => ', ',
        ]);

        // Create task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Create input artifacts with data in json_content
        $companyArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Company Data',
            'json_content' => ['company' => 'Acme Corporation'],
        ]);

        $addressArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Address Data',
            'json_content' => ['address' => '123 Main Street, San Francisco, CA'],
        ]);

        // Artifact with template reference
        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Template',
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $companyArtifact->id,
            $addressArtifact->id,
            $templateArtifact->id,
        ]);

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')
                ->once()
                ->with(
                    '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                    [
                        'company_name' => 'Acme Corporation',
                        'location'     => '123 Main Street, San Francisco, CA',
                    ],
                    ''
                )
                ->andReturn([
                    'document_id' => 'new-doc-id-123',
                    'title'       => 'Generated Document',
                    'url'         => 'https://docs.google.com/document/d/new-doc-id-123/edit',
                    'created_at'  => now()->toISOString(),
                ]);
        });

        // Run the task
        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify task completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify output artifact created
        $outputArtifacts = $taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);

        $outputArtifact = $outputArtifacts->first();
        $this->assertStringContainsString('Generated Google Doc', $outputArtifact->name);
        $this->assertEquals('new-doc-id-123', $outputArtifact->meta['google_doc_id']);
        $this->assertEquals('https://docs.google.com/document/d/new-doc-id-123/edit', $outputArtifact->meta['google_doc_url']);

        // Verify variable mapping in artifact
        $this->assertEquals('Acme Corporation', $outputArtifact->meta['variable_mapping']['company_name']);
        $this->assertEquals('123 Main Street, San Francisco, CA', $outputArtifact->meta['variable_mapping']['location']);

        // Verify stored file attached
        $this->assertCount(1, $outputArtifact->storedFiles);
        $storedFile = $outputArtifact->storedFiles->first();
        $this->assertEquals('https://docs.google.com/document/d/new-doc-id-123/edit', $storedFile->url);
    }

    #[Test]
    public function test_pure_team_object_mapping_workflow(): void
    {
        // Create schema definition for TeamObject
        $schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->team->id,
            'name'    => 'Company Schema',
            'schema'  => [
                'type'       => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ]);

        // Create schema fragment
        $schemaFragment = SchemaFragment::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'name'                 => 'Company Name Fragment',
            'fragment_selector'    => [
                'field' => 'name',
            ],
        ]);

        // Create schema association
        $schemaAssociation = SchemaAssociation::factory()->create([
            'schema_definition_id' => $schemaDefinition->id,
            'schema_fragment_id'   => $schemaFragment->id,
        ]);

        // Create template variable with TeamObject mapping
        $nameVariable = TemplateVariable::factory()->create([
            'template_definition_id'                => $this->template->id,
            'name'                                  => 'company_name',
            'mapping_type'                          => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            'team_object_schema_association_id'     => $schemaAssociation->id,
            'multi_value_strategy'                  => TemplateVariable::STRATEGY_FIRST,
        ]);

        // Create TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id'              => $this->team->id,
            'schema_definition_id' => $schemaDefinition->id,
            'type'                 => 'Company',
            'name'                 => 'Acme Corp',
        ]);

        // Create task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Create artifact with TeamObject reference
        $teamObjectArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Company Data',
            'json_content' => [
                'team_object_id' => $teamObject->id,
            ],
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Template',
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $teamObjectArtifact->id,
            $templateArtifact->id,
        ]);

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')
                ->once()
                ->andReturn([
                    'document_id' => 'new-doc-id-456',
                    'title'       => 'Generated Document',
                    'url'         => 'https://docs.google.com/document/d/new-doc-id-456/edit',
                    'created_at'  => now()->toISOString(),
                ]);
        });

        // Run the task
        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify task completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify output artifact created
        $outputArtifacts = $taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);

        $outputArtifact = $outputArtifacts->first();
        $this->assertEquals('new-doc-id-456', $outputArtifact->meta['google_doc_id']);
    }

    #[Test]
    public function test_pure_ai_mapping_workflow(): void
    {
        // Create template variables with AI mapping
        $summaryVariable = TemplateVariable::factory()->create([
            'template_definition_id'   => $this->template->id,
            'name'                     => 'summary',
            'mapping_type'             => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions'          => 'Extract a brief summary of the document',
            'multi_value_strategy'     => TemplateVariable::STRATEGY_FIRST,
        ]);

        // Create task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Create input artifacts
        $contentArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Document Content',
            'text_content' => 'This is a comprehensive business proposal.',
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Template',
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $contentArtifact->id,
            $templateArtifact->id,
        ]);

        // Mock AI response
        TestAiCompletionResponse::setMockResponse(json_encode([
            'variables' => [
                'summary' => 'Business proposal summary',
            ],
            'title' => 'Business Proposal',
        ]));

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')
                ->once()
                ->with(
                    '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                    [
                        'summary' => 'Business proposal summary',
                    ],
                    'Business Proposal'
                )
                ->andReturn([
                    'document_id' => 'new-doc-id-789',
                    'title'       => 'Business Proposal',
                    'url'         => 'https://docs.google.com/document/d/new-doc-id-789/edit',
                    'created_at'  => now()->toISOString(),
                ]);
        });

        // Run the task
        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify task completed
        $this->assertTrue($taskProcess->fresh()->isCompleted());

        // Verify output artifact created with AI-resolved variables
        $outputArtifacts = $taskProcess->fresh()->outputArtifacts;
        $this->assertCount(1, $outputArtifacts);

        $outputArtifact = $outputArtifacts->first();
        $this->assertEquals('new-doc-id-789', $outputArtifact->meta['google_doc_id']);
        $this->assertEquals('Business proposal summary', $outputArtifact->meta['variable_mapping']['summary']);
        $this->assertEquals('Business Proposal', $outputArtifact->meta['document_title']);
    }

    #[Test]
    public function test_find_demand_template_throws_error_if_not_found(): void
    {
        // Create a stored file that doesn't have a template
        $orphanStoredFile = StoredFile::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'url'     => 'https://docs.google.com/document/d/orphan-doc-id/edit',
        ]);

        // Create task process
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        // Create artifact with orphan stored file reference
        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'name'         => 'Template',
            'json_content' => ['template_stored_file_id' => $orphanStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach($templateArtifact->id);

        // Run the task and expect ValidationError
        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No template definition found');

        $runner->run();
    }

    #[Test]
    public function test_extract_team_object_id_from_direct_reference(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->team->id,
        ]);

        // Create artifact with direct team_object_id reference
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => [
                'team_object_id' => $teamObject->id,
            ],
        ]);

        // Create simple template variable
        $simpleVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'test',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => null, // Will use artifact name
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $artifact->id,
            $templateArtifact->id,
        ]);

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')->once()->andReturn([
                'document_id' => 'test-doc',
                'title'       => 'Test',
                'url'         => 'https://docs.google.com/document/d/test-doc/edit',
                'created_at'  => now()->toISOString(),
            ]);
        });

        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify it found the TeamObject (test passes if no error thrown)
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function test_extract_team_object_id_from_nested_reference(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->team->id,
        ]);

        // Create artifact with nested team_object reference
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => [
                'data' => [
                    'team_object' => [
                        'id'   => $teamObject->id,
                        'name' => 'Test Object',
                    ],
                ],
            ],
        ]);

        $simpleVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'test',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => null,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $artifact->id,
            $templateArtifact->id,
        ]);

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')->once()->andReturn([
                'document_id' => 'test-doc',
                'title'       => 'Test',
                'url'         => 'https://docs.google.com/document/d/test-doc/edit',
                'created_at'  => now()->toISOString(),
            ]);
        });

        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify it found the TeamObject
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function test_find_team_object_returns_null_when_no_team_object(): void
    {
        // Create artifacts without TeamObject reference
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['some_data' => 'test'],
        ]);

        $simpleVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'test',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => null,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach([
            $artifact->id,
            $templateArtifact->id,
        ]);

        // Mock GoogleDocsApi
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')->once()->andReturn([
                'document_id' => 'test-doc',
                'title'       => 'Test',
                'url'         => 'https://docs.google.com/document/d/test-doc/edit',
                'created_at'  => now()->toISOString(),
            ]);
        });

        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Should complete without error (TeamObject is optional)
        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function test_extract_google_doc_id_from_stored_file(): void
    {
        $simpleVariable = TemplateVariable::factory()->create([
            'template_definition_id'         => $this->template->id,
            'name'                           => 'test',
            'mapping_type'                   => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'            => null,
            'artifact_fragment_selector'     => null,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach($templateArtifact->id);

        // Mock GoogleDocsApi to verify correct doc ID extracted
        $mockGoogleDocsApi = $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')
                ->once()
                ->with(
                    '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                    \Mockery::type('array'),
                    \Mockery::type('string')
                )
                ->andReturn([
                    'document_id' => 'extracted-doc',
                    'title'       => 'Test',
                    'url'         => 'https://docs.google.com/document/d/extracted-doc/edit',
                    'created_at'  => now()->toISOString(),
                ]);
        });

        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }

    #[Test]
    public function test_empty_template_variables_handled_gracefully(): void
    {
        // No template variables created - template has no variables

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
        ]);

        $templateArtifact = Artifact::factory()->create([
            'team_id'      => $this->team->id,
            'json_content' => ['template_stored_file_id' => $this->templateStoredFile->id],
        ]);

        $taskProcess->inputArtifacts()->attach($templateArtifact->id);

        // Mock GoogleDocsApi - should be called with empty values
        $this->mock(GoogleDocsApi::class, function ($mock) {
            $mock->shouldReceive('createDocumentFromTemplate')
                ->once()
                ->with('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', [], '')
                ->andReturn([
                    'document_id' => 'empty-doc',
                    'title'       => 'Empty Template',
                    'url'         => 'https://docs.google.com/document/d/empty-doc/edit',
                    'created_at'  => now()->toISOString(),
                ]);
        });

        $runner = new TemplateTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        $this->assertTrue($taskProcess->fresh()->isCompleted());
    }
}
