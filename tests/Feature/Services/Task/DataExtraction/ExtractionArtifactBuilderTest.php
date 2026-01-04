<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\DataExtraction\ExtractionArtifactBuilder;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ExtractionArtifactBuilderTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private ExtractionArtifactBuilder $builder;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->builder = app(ExtractionArtifactBuilder::class);

        // Set up common test fixtures
        $agent            = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schemaDefinition = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'              => $this->user->currentTeam->id,
            'agent_id'             => $agent->id,
            'schema_definition_id' => $schemaDefinition->id,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    // =========================================================================
    // buildIdentityArtifact() tests
    // =========================================================================

    #[Test]
    public function buildIdentityArtifact_creates_artifact_with_correct_structure(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = [
            'data'         => ['client_name' => 'John Smith'],
            'search_query' => ['client_name' => '%John%Smith%'],
        ];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact has correct structure
        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertStringContainsString('Identity:', $artifact->name);
        $this->assertStringContainsString('Client', $artifact->name);
        $this->assertStringContainsString('John Smith', $artifact->name);

        // Verify json_content structure
        $this->assertEquals($teamObject->id, $artifact->json_content['id']);
        $this->assertEquals('Client', $artifact->json_content['type']);
        $this->assertEquals('John Smith', $artifact->json_content['client_name']);

        // Verify meta structure
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY, $artifact->meta['operation']);
        $this->assertEquals(['client_name' => '%John%Smith%'], $artifact->meta['search_query']);
        $this->assertFalse($artifact->meta['was_existing']);
        $this->assertNull($artifact->meta['match_id']);
        $this->assertEquals($taskProcess->id, $artifact->meta['task_process_id']);
        $this->assertEquals(0, $artifact->meta['level']);
        $this->assertEquals('Client', $artifact->meta['identity_group']);
    }

    #[Test]
    public function buildIdentityArtifact_sets_was_existing_when_match_found(): void
    {
        // Given: TaskProcess and TeamObject with existing match
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = [
            'data'         => ['client_name' => 'John Smith'],
            'search_query' => ['client_name' => '%John%'],
        ];

        // When: Building identity artifact with existing match
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: 999
        );

        // Then: was_existing and match_id are set correctly
        $this->assertTrue($artifact->meta['was_existing']);
        $this->assertEquals(999, $artifact->meta['match_id']);
        $this->assertEquals(1, $artifact->meta['level']);
    }

    #[Test]
    public function buildIdentityArtifact_attaches_to_process_outputs(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = ['data' => ['client_name' => 'Test']];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is attached to process outputs
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;

        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact->id, $outputArtifacts->first()->id);
    }

    #[Test]
    public function buildIdentityArtifact_links_to_parent_artifact(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = ['data' => ['client_name' => 'Test']];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is linked as child of input artifact via parent_artifact_id
        $this->assertEquals($inputArtifact->id, $artifact->parent_artifact_id);

        // Input artifact's json_content is NOT modified (data extracted artifacts are children)
        $inputArtifact->refresh();
        $this->assertEmpty($inputArtifact->json_content);
    }

    #[Test]
    public function test_build_identity_artifact_sets_parent_artifact_id_to_input_artifact(): void
    {
        // Given: A TaskProcess with operation "Extract Identity" and an attached input artifact (page artifact)
        $parentArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Root Extraction Output',
        ]);

        $pageArtifact = Artifact::factory()->create([
            'task_run_id'        => $this->taskRun->id,
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'Page 1',
            'parent_artifact_id' => $parentArtifact->id,
        ]);

        $taskProcess = TaskProcess::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'operation'   => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
        ]);
        $taskProcess->inputArtifacts()->attach($pageArtifact->id);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Test Client',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = [
            'data'         => ['client_name' => 'Test Client'],
            'search_query' => null,
        ];

        // When: Building an identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: The returned artifact has parent_artifact_id set to the page artifact's ID
        $this->assertEquals(
            $pageArtifact->id,
            $artifact->parent_artifact_id,
            'Extraction artifact should have parent_artifact_id set to the input page artifact'
        );

        // And: The page artifact's children relationship should contain the extraction artifact
        $pageArtifact->refresh();
        $childrenIds = $pageArtifact->children()->pluck('id')->toArray();
        $this->assertContains(
            $artifact->id,
            $childrenIds,
            'Page artifact\'s children relationship should contain the extraction artifact'
        );
    }

    #[Test]
    public function buildIdentityArtifact_uses_object_type_when_no_name_in_group(): void
    {
        // Given: Group without name
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
        ]);

        $group = [
            'object_type' => 'Demand',
            // No 'name' key
        ];

        $extractionResult = ['data' => ['demand_id' => '12345']];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Uses object_type as identity_group
        $this->assertEquals('Demand', $artifact->meta['identity_group']);
    }

    // =========================================================================
    // buildRemainingArtifact() tests
    // =========================================================================

    #[Test]
    public function buildRemainingArtifact_creates_artifact_with_correct_structure(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Smith',
        ]);

        $group = [
            'name'        => 'Client Address',
            'object_type' => 'Client',
        ];

        $extractedData = [
            'address' => '123 Main St',
            'city'    => 'Springfield',
            'state'   => 'IL',
        ];

        // When: Building remaining artifact
        $artifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Artifact has correct structure
        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertStringContainsString('Remaining:', $artifact->name);
        $this->assertStringContainsString('Client Address', $artifact->name);
        $this->assertStringContainsString('John Smith', $artifact->name);

        // Verify json_content structure
        $this->assertEquals($teamObject->id, $artifact->json_content['id']);
        $this->assertEquals('Client', $artifact->json_content['type']);
        $this->assertEquals('123 Main St', $artifact->json_content['address']);
        $this->assertEquals('Springfield', $artifact->json_content['city']);
        $this->assertEquals('IL', $artifact->json_content['state']);

        // Verify meta structure
        $this->assertEquals(ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING, $artifact->meta['operation']);
        $this->assertEquals('exhaustive', $artifact->meta['extraction_mode']);
        $this->assertEquals($taskProcess->id, $artifact->meta['task_process_id']);
        $this->assertEquals(0, $artifact->meta['level']);
        $this->assertEquals('Client Address', $artifact->meta['extraction_group']);
    }

    #[Test]
    public function buildRemainingArtifact_attaches_to_process_outputs(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Client Details',
            'object_type' => 'Client',
        ];

        $extractedData = ['email' => 'test@example.com'];

        // When: Building remaining artifact
        $artifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'skim'
        );

        // Then: Artifact is attached to process outputs
        $taskProcess->refresh();
        $outputArtifacts = $taskProcess->outputArtifacts;

        $this->assertCount(1, $outputArtifacts);
        $this->assertEquals($artifact->id, $outputArtifacts->first()->id);
    }

    #[Test]
    public function buildRemainingArtifact_links_to_parent_artifact(): void
    {
        // Given: TaskProcess with input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Contact Info',
            'object_type' => 'Client',
        ];

        $extractedData = ['phone' => '555-1234'];

        // When: Building remaining artifact
        $artifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Artifact is linked as child of input artifact via parent_artifact_id
        $this->assertEquals($inputArtifact->id, $artifact->parent_artifact_id);

        // Input artifact's json_content is NOT modified (data extracted artifacts are children)
        $inputArtifact->refresh();
        $this->assertEmpty($inputArtifact->json_content);
    }

    #[Test]
    public function buildRemainingArtifact_supports_different_search_modes(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Test Group',
            'object_type' => 'Client',
        ];

        $extractedData = ['field' => 'value'];

        // When: Building with skim mode
        $skimArtifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'skim'
        );

        // Then: Extraction mode is set correctly
        $this->assertEquals('skim', $skimArtifact->meta['extraction_mode']);
    }

    #[Test]
    public function buildRemainingArtifact_handles_multiple_levels(): void
    {
        // Given: TaskProcess and TeamObject
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Claim',
        ]);

        $group = [
            'name'        => 'Claim Details',
            'object_type' => 'Claim',
        ];

        $extractedData = ['claim_amount' => 50000];

        // When: Building at level 2
        $artifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 2,
            searchMode: 'exhaustive'
        );

        // Then: Level is set correctly
        $this->assertEquals(2, $artifact->meta['level']);
    }

    #[Test]
    public function buildRemainingArtifact_uses_name_in_meta_extraction_group(): void
    {
        // Given: Group with both name and object_type
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Accident',
        ]);

        $group = [
            'name'        => 'Accident Location',
            'object_type' => 'Accident',
        ];

        $extractedData = ['location' => 'Highway 101'];

        // When: Building remaining artifact
        $artifact = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Uses name as extraction_group
        $this->assertEquals('Accident Location', $artifact->meta['extraction_group']);
        $this->assertStringContainsString('Accident Location', $artifact->name);
    }

    // =========================================================================
    // attachToProcessAndLinkParent() edge cases
    // =========================================================================

    #[Test]
    public function buildIdentityArtifact_handles_missing_input_artifact_gracefully(): void
    {
        // Given: TaskProcess without input artifacts
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        // No input artifacts attached

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = ['data' => ['name' => 'Test']];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is created without parent link
        $this->assertNull($artifact->parent_artifact_id);

        // And artifact is still attached to process outputs
        $taskProcess->refresh();
        $this->assertCount(1, $taskProcess->outputArtifacts);
    }

    #[Test]
    public function buildIdentityArtifact_preserves_existing_json_content_in_input_artifact(): void
    {
        // Given: Input artifact with existing json_content
        $inputArtifact = Artifact::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => [
                'existing_key' => 'existing_value',
                'page_number'  => 5,
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
        ]);

        $group = [
            'name'        => 'Client',
            'object_type' => 'Client',
        ];

        $extractionResult = ['data' => ['name' => 'Test']];

        // When: Building identity artifact
        $artifact = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Input artifact's json_content is preserved (not modified)
        $inputArtifact->refresh();
        $this->assertEquals('existing_value', $inputArtifact->json_content['existing_key']);
        $this->assertEquals(5, $inputArtifact->json_content['page_number']);

        // And artifact is linked via parent_artifact_id
        $this->assertEquals($inputArtifact->id, $artifact->parent_artifact_id);
    }
}
