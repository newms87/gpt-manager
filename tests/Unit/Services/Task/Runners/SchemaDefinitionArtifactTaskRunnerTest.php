<?php

namespace Tests\Unit\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\ArtifactCategoryDefinition;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Services\Task\Runners\SchemaDefinitionArtifactTaskRunner;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class SchemaDefinitionArtifactTaskRunnerTest extends AuthenticatedTestCase
{
    protected SchemaDefinition $schemaDefinition;

    protected TaskDefinition $taskDefinition;

    protected TaskRun $taskRun;

    protected Agent $agent;

    public function setUp(): void
    {
        parent::setUp();

        // Create agent for the task definition
        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => 'test-model',
        ]);

        // Create schema definition
        $this->schemaDefinition = SchemaDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create task definition configured for SchemaDefinitionArtifactTaskRunner
        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'agent_id'           => $this->agent->id,
            'task_runner_name'   => SchemaDefinitionArtifactTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'schema_definition_id' => $this->schemaDefinition->id,
            ],
        ]);

        // Create task run
        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    #[Test]
    public function prepareRun_creates_no_processes_when_no_artifact_categories_exist(): void
    {
        // Create input artifact with team_object_id reference
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $teamObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Ensure no artifact category definitions exist
        $this->assertEquals(0, $this->schemaDefinition->artifactCategoryDefinitions()->count());

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should mark task run as skipped, no processes created
        $this->taskRun->refresh();
        $this->assertNotNull($this->taskRun->skipped_at);
        $this->assertEquals(0, $this->taskRun->taskProcesses()->count());
    }

    #[Test]
    public function prepareRun_creates_processes_for_root_team_object_when_fragment_selector_is_null(): void
    {
        // Create input artifact with team_object_id reference
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Root Object',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $teamObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Create artifact category definition with null fragment_selector
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'summary',
            'label'                => 'Summary',
            'prompt'               => 'Generate a summary',
            'fragment_selector'    => null,
        ]);

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should create one process for the root TeamObject
        $processes = $this->taskRun->taskProcesses()
            ->where('operation', SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT)
            ->get();

        $this->assertEquals(1, $processes->count());
        $this->assertStringContainsString('Root Object', $processes->first()->name);
    }

    #[Test]
    public function prepareRun_creates_processes_for_related_team_objects_when_fragment_selector_is_set(): void
    {
        // Create root TeamObject
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Root Object',
        ]);

        // Create related TeamObjects (providers)
        $provider1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Provider One',
        ]);

        $provider2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Provider Two',
        ]);

        // Create relationships
        TeamObjectRelationship::create([
            'team_object_id'         => $rootObject->id,
            'related_team_object_id' => $provider1->id,
            'relationship_name'      => 'providers',
        ]);

        TeamObjectRelationship::create([
            'team_object_id'         => $rootObject->id,
            'related_team_object_id' => $provider2->id,
            'relationship_name'      => 'providers',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $rootObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Create artifact category definition with fragment_selector pointing to providers
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'provider-summary',
            'label'                => 'Provider Summary',
            'prompt'               => 'Generate a provider summary',
            'fragment_selector'    => ['providers'],
        ]);

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should create two processes (one for each provider)
        $processes = $this->taskRun->taskProcesses()
            ->where('operation', SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT)
            ->get();

        $this->assertEquals(2, $processes->count());
    }

    #[Test]
    public function prepareRun_throws_validation_error_when_schema_definition_id_is_missing(): void
    {
        // Create task definition without schema_definition_id in config
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'agent_id'           => $this->agent->id,
            'task_runner_name'   => SchemaDefinitionArtifactTaskRunner::RUNNER_NAME,
            'task_runner_config' => [],
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($taskRun);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('schema_definition_id');
        $runner->prepareRun();
    }

    #[Test]
    public function prepareRun_throws_validation_error_when_input_artifact_has_no_team_object_id(): void
    {
        // Create artifact category definition so it won't skip
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'test',
            'label'                => 'Test',
            'prompt'               => 'Test prompt',
        ]);

        // Create input artifact WITHOUT team_object_id
        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['some_other_field' => 'value'],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('team_object_id');
        $runner->prepareRun();
    }

    #[Test]
    public function run_generates_artifact_and_attaches_to_team_object(): void
    {
        // Create TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Object',
        ]);

        // Create artifact category definition
        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'summary',
            'label'                => 'Summary',
            'prompt'               => 'Generate a summary',
        ]);

        // Create config artifact for the process
        $configArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'name'         => SchemaDefinitionArtifactTaskRunner::CONFIG_ARTIFACT_NAME,
            'json_content' => [
                'team_object_id'                  => $teamObject->id,
                'artifact_category_definition_id' => $categoryDefinition->id,
                'prompt'                          => 'Generate a summary',
                'category_name'                   => 'summary',
                'data'                            => ['name' => 'Test Object'],
            ],
        ]);

        // Create task process with the operation
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'operation'   => SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT,
        ]);

        $taskProcess->inputArtifacts()->attach($configArtifact->id);

        // Mock the agent thread to simulate artifact generation
        $runner = $this->getMockBuilder(SchemaDefinitionArtifactTaskRunner::class)
            ->onlyMethods(['runAgentThread', 'setupAgentThreadWithPrompt'])
            ->getMock();

        $generatedArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'text_content' => 'Generated summary content',
        ]);

        $runner->expects($this->once())
            ->method('setupAgentThreadWithPrompt')
            ->willReturn($this->createMock(\App\Models\Agent\AgentThread::class));

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->willReturn($generatedArtifact);

        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Verify artifact is attached to TeamObject with correct category
        $teamObject->refresh();
        $attachedArtifacts = $teamObject->getArtifactsByCategory('summary');

        $this->assertCount(1, $attachedArtifacts);
        $this->assertEquals($generatedArtifact->id, $attachedArtifacts->first()->id);
    }

    #[Test]
    public function run_completes_process_even_when_no_artifact_generated(): void
    {
        // Create TeamObject
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create artifact category definition
        $categoryDefinition = ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'summary',
            'label'                => 'Summary',
            'prompt'               => 'Generate a summary',
        ]);

        // Create config artifact for the process
        $configArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'task_run_id'  => $this->taskRun->id,
            'name'         => SchemaDefinitionArtifactTaskRunner::CONFIG_ARTIFACT_NAME,
            'json_content' => [
                'team_object_id'                  => $teamObject->id,
                'artifact_category_definition_id' => $categoryDefinition->id,
                'prompt'                          => 'Generate a summary',
                'category_name'                   => 'summary',
                'data'                            => [],
            ],
        ]);

        // Create task process with the operation
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'operation'   => SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT,
        ]);

        $taskProcess->inputArtifacts()->attach($configArtifact->id);

        // Mock the agent thread to return null (no artifact generated)
        $runner = $this->getMockBuilder(SchemaDefinitionArtifactTaskRunner::class)
            ->onlyMethods(['runAgentThread', 'setupAgentThreadWithPrompt'])
            ->getMock();

        $runner->expects($this->once())
            ->method('setupAgentThreadWithPrompt')
            ->willReturn($this->createMock(\App\Models\Agent\AgentThread::class));

        $runner->expects($this->once())
            ->method('runAgentThread')
            ->willReturn(null);

        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);
        $runner->run();

        // Process should still complete
        $taskProcess->refresh();
        $this->assertTrue($taskProcess->isCompleted());
    }

    #[Test]
    public function prepareRun_creates_processes_for_multiple_category_definitions(): void
    {
        // Create root TeamObject
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Root Object',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $rootObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Create multiple artifact category definitions
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'summary',
            'label'                => 'Summary',
            'prompt'               => 'Generate a summary',
            'fragment_selector'    => null,
        ]);

        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'analysis',
            'label'                => 'Analysis',
            'prompt'               => 'Generate an analysis',
            'fragment_selector'    => null,
        ]);

        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'recommendations',
            'label'                => 'Recommendations',
            'prompt'               => 'Generate recommendations',
            'fragment_selector'    => null,
        ]);

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should create three processes (one for each category definition)
        $processes = $this->taskRun->taskProcesses()
            ->where('operation', SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT)
            ->get();

        $this->assertEquals(3, $processes->count());
    }

    #[Test]
    public function prepareRun_handles_nested_relationship_paths(): void
    {
        // Create hierarchical TeamObjects: Root -> Providers -> Contacts
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Root Object',
        ]);

        $provider = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Provider',
        ]);

        $contact1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Contact One',
        ]);

        $contact2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Contact Two',
        ]);

        // Create relationships
        TeamObjectRelationship::create([
            'team_object_id'         => $rootObject->id,
            'related_team_object_id' => $provider->id,
            'relationship_name'      => 'providers',
        ]);

        TeamObjectRelationship::create([
            'team_object_id'         => $provider->id,
            'related_team_object_id' => $contact1->id,
            'relationship_name'      => 'contacts',
        ]);

        TeamObjectRelationship::create([
            'team_object_id'         => $provider->id,
            'related_team_object_id' => $contact2->id,
            'relationship_name'      => 'contacts',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $rootObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Create artifact category definition with nested fragment_selector
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'contact-info',
            'label'                => 'Contact Info',
            'prompt'               => 'Generate contact information',
            'fragment_selector'    => ['providers', 'contacts'],
        ]);

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should create two processes (one for each contact)
        $processes = $this->taskRun->taskProcesses()
            ->where('operation', SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT)
            ->get();

        $this->assertEquals(2, $processes->count());
    }

    #[Test]
    public function prepareRun_creates_no_processes_when_relationship_path_yields_empty_collection(): void
    {
        // Create root TeamObject with no related providers
        $rootObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Root Object',
        ]);

        $inputArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['team_object_id' => $rootObject->id],
        ]);

        $this->taskRun->inputArtifacts()->attach($inputArtifact->id);

        // Create artifact category definition with fragment_selector pointing to non-existent relationship
        ArtifactCategoryDefinition::create([
            'schema_definition_id' => $this->schemaDefinition->id,
            'name'                 => 'provider-summary',
            'label'                => 'Provider Summary',
            'prompt'               => 'Generate a provider summary',
            'fragment_selector'    => ['providers'],
        ]);

        // Run prepareRun
        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun);
        $runner->prepareRun();

        // Should create no processes since there are no related providers
        $processes = $this->taskRun->taskProcesses()
            ->where('operation', SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT)
            ->get();

        $this->assertEquals(0, $processes->count());
    }

    #[Test]
    public function run_throws_validation_error_when_config_artifact_is_missing(): void
    {
        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'operation'   => SchemaDefinitionArtifactTaskRunner::OPERATION_GENERATE_ARTIFACT,
        ]);

        // Don't attach any input artifacts

        $runner = new SchemaDefinitionArtifactTaskRunner();
        $runner->setTaskRun($this->taskRun)->setTaskProcess($taskProcess);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('config artifact');
        $runner->run();
    }
}
