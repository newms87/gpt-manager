<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Template\TemplateDefinition;
use App\Services\Task\Runners\TemplateTaskRunner;
use App\Services\Template\TemplateRenderingService;
use App\Services\Template\TemplateRenderResult;
use Newms87\Danx\Models\Utilities\StoredFile;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateTaskRunnerTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TaskDefinition $taskDefinition;

    protected TaskRun $taskRun;

    protected TaskProcess $taskProcess;

    protected TemplateTaskRunner $runner;

    protected Agent $agent;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'agent_id'         => $this->agent->id,
            'task_runner_name' => TemplateTaskRunner::RUNNER_NAME,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);

        $this->taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'name'        => 'Test Process',
            'status'      => 'pending',
        ]);

        // Ensure the relationship is properly loaded
        $this->taskProcess->load('taskRun.taskDefinition');
        $this->taskRun->load('taskDefinition');

        $this->runner = (new TemplateTaskRunner())
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess);
    }

    public function test_runner_name_is_template(): void
    {
        $this->assertEquals('Template', TemplateTaskRunner::RUNNER_NAME);
    }

    public function test_run_withValidGoogleDocTemplate_createsOutputArtifact(): void
    {
        // Given - Create a valid Google Doc StoredFile
        $storedFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/1hT7xB0npDUHmtWEzldE_qJNDoSNDVxLRQMcFkmdmiSg/edit',
        ]);

        // Create a TemplateDefinition linked to the StoredFile
        $template = TemplateDefinition::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'user_id'        => $this->user->id,
            'type'           => TemplateDefinition::TYPE_GOOGLE_DOCS,
            'stored_file_id' => $storedFile->id,
            'name'           => 'Test Template',
        ]);

        // Create output StoredFile for the generated doc
        $outputStoredFile = StoredFile::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'url'     => 'https://docs.google.com/document/d/new-generated-doc-id/edit',
        ]);

        // Mock TemplateRenderingService to return a Google Docs result
        $this->mock(TemplateRenderingService::class)
            ->shouldReceive('render')
            ->once()
            ->andReturn(TemplateRenderResult::googleDocs(
                title: 'Generated Test Document',
                values: ['client_name' => 'John Doe', 'date' => '2024-01-01'],
                url: 'https://docs.google.com/document/d/new-generated-doc-id/edit',
                documentId: 'new-generated-doc-id',
                storedFile: $outputStoredFile,
            ));

        // Create input artifact with direct reference to StoredFile ID
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_stored_file_id' => $storedFile->id,
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $this->runner->run();

        // Then - Verify output artifacts were created
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts;

        $this->assertCount(1, $outputArtifacts);
        $outputArtifact = $outputArtifacts->first();

        $this->assertStringContainsString('Generated Test Document', $outputArtifact->name);
        $this->assertStringContainsString('Successfully created Google Docs document', $outputArtifact->text_content);
        $this->assertEquals('https://docs.google.com/document/d/new-generated-doc-id/edit', $outputArtifact->meta['google_doc_url']);
        $this->assertEquals('new-generated-doc-id', $outputArtifact->meta['google_doc_id']);

        // Verify StoredFile was attached
        $this->assertCount(1, $outputArtifact->storedFiles);
        $attachedStoredFile = $outputArtifact->storedFiles->first();
        $this->assertEquals('https://docs.google.com/document/d/new-generated-doc-id/edit', $attachedStoredFile->url);
    }

    public function test_run_withHtmlTemplate_createsHtmlOutputArtifact(): void
    {
        // Given - Create an HTML template
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'name'         => 'HTML Template',
            'html_content' => '<div data-var-name>placeholder</div>',
            'css_content'  => '.test { color: red; }',
        ]);

        // Mock TemplateRenderingService to return an HTML result
        $this->mock(TemplateRenderingService::class)
            ->shouldReceive('render')
            ->once()
            ->andReturn(TemplateRenderResult::html(
                title: 'Rendered HTML Document',
                values: ['name' => 'John Doe'],
                html: '<div data-var-name>John Doe</div>',
                css: '.test { color: red; }',
            ));

        // Create input artifact with template reference
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'template_definition_id' => $template->id,
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $this->runner->run();

        // Then - Verify HTML output artifact was created
        $this->taskProcess->refresh();
        $outputArtifacts = $this->taskProcess->outputArtifacts;

        $this->assertCount(1, $outputArtifacts);
        $outputArtifact = $outputArtifacts->first();

        $this->assertStringContainsString('Rendered HTML', $outputArtifact->name);
        $this->assertEquals('<div data-var-name>John Doe</div>', $outputArtifact->text_content);
        $this->assertEquals('rendered_html', $outputArtifact->meta['type']);
        $this->assertEquals('<div data-var-name>John Doe</div>', $outputArtifact->json_content['html']);
        $this->assertEquals('.test { color: red; }', $outputArtifact->json_content['css']);
    }

    public function test_run_withNoTemplateFound_throwsException(): void
    {
        // Given - Create input artifact without template reference
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['some_other_data' => 'value'],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // Then
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('No template definition found');

        // When
        $this->runner->run();
    }

    public function test_findTeamObjectFromArtifacts_withDirectReference_findsTeamObject(): void
    {
        // Given
        $teamObject = \App\Models\TeamObject\TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'team_object_id' => $teamObject->id,
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findTeamObjectFromArtifacts', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertNotNull($result);
        $this->assertEquals($teamObject->id, $result->id);
    }

    public function test_findTeamObjectFromArtifacts_withNestedReference_findsTeamObject(): void
    {
        // Given
        $teamObject = \App\Models\TeamObject\TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'data' => [
                    'team_object' => [
                        'id'   => $teamObject->id,
                        'name' => 'Test Object',
                    ],
                ],
            ],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findTeamObjectFromArtifacts', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertNotNull($result);
        $this->assertEquals($teamObject->id, $result->id);
    }

    public function test_findTeamObjectFromArtifacts_withNoTeamObject_returnsNull(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['some_data' => 'test'],
        ]);

        $this->taskProcess->inputArtifacts()->attach($artifact->id);
        $this->taskProcess->refresh();

        // When
        $result = $this->invokeMethod($this->runner, 'findTeamObjectFromArtifacts', [$this->taskProcess->inputArtifacts]);

        // Then
        $this->assertNull($result);
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
