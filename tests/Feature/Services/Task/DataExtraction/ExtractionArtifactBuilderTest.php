<?php

namespace Tests\Feature\Services\Task\DataExtraction;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectRelationship;
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact has correct structure
        $this->assertCount(1, $artifacts);
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: 999
        );

        // Then: was_existing and match_id are set correctly
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is attached to process outputs
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is linked as child of input artifact via parent_artifact_id
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: The returned artifact has parent_artifact_id set to the page artifact's ID
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Uses object_type as identity_group
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Artifact has correct structure
        $this->assertCount(1, $artifacts);
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'skim'
        );

        // Then: Artifact is attached to process outputs
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Artifact is linked as child of input artifact via parent_artifact_id
        $artifact = $artifacts[0];
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
        $skimArtifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'skim'
        );

        // Then: Extraction mode is set correctly
        $this->assertEquals('skim', $skimArtifacts[0]->meta['extraction_mode']);
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
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 2,
            searchMode: 'exhaustive'
        );

        // Then: Level is set correctly
        $this->assertEquals(2, $artifacts[0]->meta['level']);
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
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: Uses name as extraction_group
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Artifact is created without parent link
        $artifact = $artifacts[0];
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
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: Input artifact's json_content is preserved (not modified)
        $artifact = $artifacts[0];
        $inputArtifact->refresh();
        $this->assertEquals('existing_value', $inputArtifact->json_content['existing_key']);
        $this->assertEquals(5, $inputArtifact->json_content['page_number']);

        // And artifact is linked via parent_artifact_id
        $this->assertEquals($inputArtifact->id, $artifact->parent_artifact_id);
    }

    // =========================================================================
    // Hierarchical JSON structure tests (level 0 vs level 1+)
    // =========================================================================

    #[Test]
    public function buildIdentityArtifact_level_0_shows_flat_structure(): void
    {
        // Given: A root-level TeamObject (Demand) with no parent
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $group = [
            'name'        => 'Demand',
            'object_type' => 'Demand',
        ];

        $extractionResult = [
            'data'         => ['demand_number' => 'DEM-001', 'claimant_name' => 'John Smith'],
            'search_query' => ['demand_number' => '%DEM-001%'],
        ];

        // When: Building identity artifact at level 0 with no parent
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 0,
            matchId: null
        );

        // Then: json_content shows flat structure with object data at root level
        $jsonContent = $artifacts[0]->json_content;
        $this->assertEquals($teamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);
        $this->assertEquals('DEM-001', $jsonContent['demand_number']);
        $this->assertEquals('John Smith', $jsonContent['claimant_name']);

        // Verify there is NO nested relationship key (like 'demand' or 'provider')
        $this->assertArrayNotHasKey('demand', $jsonContent);
        $this->assertArrayNotHasKey('provider', $jsonContent);
    }

    #[Test]
    public function buildIdentityArtifact_level_1_shows_hierarchical_structure(): void
    {
        // Given: A parent TeamObject (Demand) and child TeamObject (Provider)
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Provider A',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'provider',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Provider',
            'object_type' => 'Provider',
        ];

        $extractionResult = [
            'data'         => ['provider_name' => 'Provider A', 'npi' => '1234567890'],
            'search_query' => ['provider_name' => '%Provider%A%'],
        ];

        // When: Building identity artifact at level 1 with parent object
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: null
        );

        // Then: json_content shows hierarchical structure with parent at root (derived from DB relationships)
        $jsonContent = $artifacts[0]->json_content;

        // Parent's id and type are at root level
        $this->assertEquals($parentTeamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Child data is nested under snake_case relationship key
        $this->assertArrayHasKey('provider', $jsonContent);
        $this->assertIsArray($jsonContent['provider']);
        $this->assertCount(1, $jsonContent['provider']);

        // Child object has correct structure
        $childData = $jsonContent['provider'][0];
        $this->assertEquals($childTeamObject->id, $childData['id']);
        $this->assertEquals('Provider', $childData['type']);
        $this->assertEquals('Provider A', $childData['provider_name']);
        $this->assertEquals('1234567890', $childData['npi']);
    }

    #[Test]
    public function buildRemainingArtifact_level_0_shows_flat_structure(): void
    {
        // Given: A root-level TeamObject (Demand) with no parent
        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $teamObject  = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $group = [
            'name'        => 'Demand Details',
            'object_type' => 'Demand',
        ];

        $extractedData = [
            'total_amount'    => 150000,
            'date_of_injury'  => '2024-01-15',
        ];

        // When: Building remaining artifact at level 0 with no parent
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $group,
            extractedData: $extractedData,
            level: 0,
            searchMode: 'exhaustive'
        );

        // Then: json_content shows flat structure with object data at root level
        $jsonContent = $artifacts[0]->json_content;
        $this->assertEquals($teamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);
        $this->assertEquals(150000, $jsonContent['total_amount']);
        $this->assertEquals('2024-01-15', $jsonContent['date_of_injury']);

        // Verify there is NO nested relationship key
        $this->assertArrayNotHasKey('demand', $jsonContent);
        $this->assertArrayNotHasKey('provider', $jsonContent);
    }

    #[Test]
    public function buildRemainingArtifact_level_1_shows_hierarchical_structure(): void
    {
        // Given: A parent TeamObject (Demand) and child TeamObject (Provider)
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Provider B',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'provider',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Provider Details',
            'object_type' => 'Provider',
        ];

        $extractedData = [
            'specialty'     => 'Orthopedics',
            'facility_name' => 'City Hospital',
        ];

        // When: Building remaining artifact at level 1 with parent object
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractedData: $extractedData,
            level: 1,
            searchMode: 'exhaustive'
        );

        // Then: json_content shows hierarchical structure with parent at root (derived from DB relationships)
        $jsonContent = $artifacts[0]->json_content;

        // Parent's id and type are at root level
        $this->assertEquals($parentTeamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Child data is nested under snake_case relationship key
        $this->assertArrayHasKey('provider', $jsonContent);
        $this->assertIsArray($jsonContent['provider']);
        $this->assertCount(1, $jsonContent['provider']);

        // Child object has correct structure
        $childData = $jsonContent['provider'][0];
        $this->assertEquals($childTeamObject->id, $childData['id']);
        $this->assertEquals('Provider', $childData['type']);
        $this->assertEquals('Orthopedics', $childData['specialty']);
        $this->assertEquals('City Hospital', $childData['facility_name']);
    }

    #[Test]
    public function buildHierarchicalJson_uses_snake_case_for_relationship_key(): void
    {
        // Given: Parent and child team objects with multi-word object type
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Case',
            'name'    => 'Test Case',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment Received',  // Multi-word type
            'name'    => 'Physical Therapy',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'treatment_received',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Treatment Received',
            'object_type' => 'Treatment Received',
        ];

        $extractionResult = [
            'data'         => ['treatment_type' => 'Physical Therapy', 'sessions' => 10],
            'search_query' => null,
        ];

        // When: Building identity artifact at level 1 with parent object
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: null
        );

        // Then: Relationship key is snake_case version of object type
        $jsonContent = $artifacts[0]->json_content;

        // "Treatment Received" becomes "treatment_received"
        $this->assertArrayHasKey('treatment_received', $jsonContent);
        $this->assertArrayNotHasKey('Treatment Received', $jsonContent);
        $this->assertArrayNotHasKey('treatmentReceived', $jsonContent);

        // Verify the child data is correct
        $childData = $jsonContent['treatment_received'][0];
        $this->assertEquals($childTeamObject->id, $childData['id']);
        $this->assertEquals('Treatment Received', $childData['type']);
    }

    #[Test]
    public function buildIdentityArtifact_with_simple_single_word_type_uses_lowercase(): void
    {
        // Given: Parent and child with simple single-word type
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Test Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',  // Single word, already snake_case
            'name'    => 'Dr. Smith',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'provider',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Provider',
            'object_type' => 'Provider',
        ];

        $extractionResult = [
            'data'         => ['name' => 'Dr. Smith'],
            'search_query' => null,
        ];

        // When: Building identity artifact
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: null
        );

        // Then: "Provider" becomes "provider" (lowercase snake_case)
        $jsonContent = $artifacts[0]->json_content;
        $this->assertArrayHasKey('provider', $jsonContent);
        $this->assertArrayNotHasKey('Provider', $jsonContent);
    }

    #[Test]
    public function buildRemainingArtifact_level_2_shows_full_hierarchical_structure(): void
    {
        // Given: Full hierarchy - Demand (root) -> Provider (level 1) -> Treatment (level 2)
        $demandObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $providerObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Dr. Smith',
        ]);

        $treatmentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment',
            'name'    => 'Therapy Session',
        ]);

        // Create DB relationships: Demand -> Provider -> Treatment
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $demandObject->id,
            'related_team_object_id' => $providerObject->id,
            'relationship_name'      => 'provider',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $providerObject->id,
            'related_team_object_id' => $treatmentObject->id,
            'relationship_name'      => 'treatment',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Treatment Details',
            'object_type' => 'Treatment',
        ];

        $extractedData = [
            'date'     => '2024-06-15',
            'duration' => '60 minutes',
            'notes'    => 'Patient responding well',
        ];

        // When: Building remaining artifact at level 2 with immediate parent
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $treatmentObject,
            group: $group,
            extractedData: $extractedData,
            level: 2,
            searchMode: 'exhaustive'
        );

        // Then: json_content shows FULL hierarchical structure from root (Demand)
        // Hierarchy is derived from DB relationships, not passed parentObjectId
        $jsonContent = $artifacts[0]->json_content;

        // Root is Demand (not Provider!)
        $this->assertEquals($demandObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Provider is nested under Demand
        $this->assertArrayHasKey('provider', $jsonContent);
        $this->assertIsArray($jsonContent['provider']);
        $this->assertCount(1, $jsonContent['provider']);

        $providerData = $jsonContent['provider'][0];
        $this->assertEquals($providerObject->id, $providerData['id']);
        $this->assertEquals('Provider', $providerData['type']);

        // Treatment is nested under Provider
        $this->assertArrayHasKey('treatment', $providerData);
        $this->assertIsArray($providerData['treatment']);
        $this->assertCount(1, $providerData['treatment']);

        $treatmentData = $providerData['treatment'][0];
        $this->assertEquals($treatmentObject->id, $treatmentData['id']);
        $this->assertEquals('Treatment', $treatmentData['type']);
        $this->assertEquals('2024-06-15', $treatmentData['date']);
        $this->assertEquals('60 minutes', $treatmentData['duration']);
        $this->assertEquals('Patient responding well', $treatmentData['notes']);
    }

    #[Test]
    public function buildIdentityArtifact_level_3_shows_full_hierarchical_structure(): void
    {
        // Given: Full hierarchy - Demand -> Provider -> Treatment -> LineItem (4 levels)
        $demandObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Jones Demand',
        ]);

        $providerObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'City Hospital',
        ]);

        $treatmentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment',
            'name'    => 'Surgery',
        ]);

        $lineItemObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Line Item',
            'name'    => 'CPT 99213',
        ]);

        // Create DB relationships: Demand -> Provider -> Treatment -> LineItem
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $demandObject->id,
            'related_team_object_id' => $providerObject->id,
            'relationship_name'      => 'provider',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $providerObject->id,
            'related_team_object_id' => $treatmentObject->id,
            'relationship_name'      => 'treatment',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $treatmentObject->id,
            'related_team_object_id' => $lineItemObject->id,
            'relationship_name'      => 'line_item',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Line Item',
            'object_type' => 'Line Item',
        ];

        $extractionResult = [
            'data'         => ['cpt_code' => '99213', 'amount' => 150.00, 'description' => 'Office visit'],
            'search_query' => ['cpt_code' => '%99213%'],
        ];

        // When: Building identity artifact at level 3 (Line Item under Treatment under Provider under Demand)
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $lineItemObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 3,
            matchId: null
        );

        // Then: json_content shows FULL hierarchical structure from root (Demand)
        // Hierarchy is derived from DB relationships, not passed parentObjectId
        $jsonContent = $artifacts[0]->json_content;

        // Root is Demand
        $this->assertEquals($demandObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Provider is nested under Demand
        $this->assertArrayHasKey('provider', $jsonContent);
        $providerData = $jsonContent['provider'][0];
        $this->assertEquals($providerObject->id, $providerData['id']);
        $this->assertEquals('Provider', $providerData['type']);

        // Treatment is nested under Provider
        $this->assertArrayHasKey('treatment', $providerData);
        $treatmentData = $providerData['treatment'][0];
        $this->assertEquals($treatmentObject->id, $treatmentData['id']);
        $this->assertEquals('Treatment', $treatmentData['type']);

        // Line Item is nested under Treatment
        $this->assertArrayHasKey('line_item', $treatmentData);
        $lineItemData = $treatmentData['line_item'][0];
        $this->assertEquals($lineItemObject->id, $lineItemData['id']);
        $this->assertEquals('Line Item', $lineItemData['type']);
        $this->assertEquals('99213', $lineItemData['cpt_code']);
        $this->assertEquals(150.00, $lineItemData['amount']);
        $this->assertEquals('Office visit', $lineItemData['description']);
    }

    #[Test]
    public function buildRemainingArtifact_level_4_shows_full_hierarchical_structure(): void
    {
        // Given: Full hierarchy - Demand -> Provider -> Treatment -> LineItem -> Modifier (5 levels)
        $demandObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Williams Demand',
        ]);

        $providerObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Provider',
            'name'    => 'Regional Medical Center',
        ]);

        $treatmentObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Treatment',
            'name'    => 'Physical Therapy',
        ]);

        $lineItemObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Line Item',
            'name'    => 'CPT 97110',
        ]);

        $modifierObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Modifier',
            'name'    => 'Modifier 59',
        ]);

        // Create DB relationships: Demand -> Provider -> Treatment -> LineItem -> Modifier
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $demandObject->id,
            'related_team_object_id' => $providerObject->id,
            'relationship_name'      => 'provider',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $providerObject->id,
            'related_team_object_id' => $treatmentObject->id,
            'relationship_name'      => 'treatment',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $treatmentObject->id,
            'related_team_object_id' => $lineItemObject->id,
            'relationship_name'      => 'line_item',
        ]);
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $lineItemObject->id,
            'related_team_object_id' => $modifierObject->id,
            'relationship_name'      => 'modifier',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        $group = [
            'name'        => 'Modifier Details',
            'object_type' => 'Modifier',
        ];

        $extractedData = [
            'code'        => '59',
            'description' => 'Distinct procedural service',
            'applies_to'  => 'Physical therapy exercises',
        ];

        // When: Building remaining artifact at level 4 (Modifier under LineItem under Treatment under Provider under Demand)
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $modifierObject,
            group: $group,
            extractedData: $extractedData,
            level: 4,
            searchMode: 'exhaustive'
        );

        // Then: json_content shows FULL hierarchical structure from root (Demand)
        // Hierarchy is derived from DB relationships, not passed parentObjectId
        $jsonContent = $artifacts[0]->json_content;

        // Root is Demand
        $this->assertEquals($demandObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Provider is nested under Demand
        $this->assertArrayHasKey('provider', $jsonContent);
        $providerData = $jsonContent['provider'][0];
        $this->assertEquals($providerObject->id, $providerData['id']);
        $this->assertEquals('Provider', $providerData['type']);

        // Treatment is nested under Provider
        $this->assertArrayHasKey('treatment', $providerData);
        $treatmentData = $providerData['treatment'][0];
        $this->assertEquals($treatmentObject->id, $treatmentData['id']);
        $this->assertEquals('Treatment', $treatmentData['type']);

        // Line Item is nested under Treatment
        $this->assertArrayHasKey('line_item', $treatmentData);
        $lineItemData = $treatmentData['line_item'][0];
        $this->assertEquals($lineItemObject->id, $lineItemData['id']);
        $this->assertEquals('Line Item', $lineItemData['type']);

        // Modifier is nested under Line Item
        $this->assertArrayHasKey('modifier', $lineItemData);
        $modifierData = $lineItemData['modifier'][0];
        $this->assertEquals($modifierObject->id, $modifierData['id']);
        $this->assertEquals('Modifier', $modifierData['type']);
        $this->assertEquals('59', $modifierData['code']);
        $this->assertEquals('Distinct procedural service', $modifierData['description']);
        $this->assertEquals('Physical therapy exercises', $modifierData['applies_to']);
    }

    // =========================================================================
    // Fragment selector type tests (object vs array)
    // =========================================================================

    #[Test]
    public function buildIdentityArtifact_with_object_type_fragment_selector_does_not_wrap_in_array(): void
    {
        // Given: A parent TeamObject (Demand) and child TeamObject (Client)
        // where the fragment_selector specifies client as type: "object" (not array)
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'Abdi, Abdinasir',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'client',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Group with fragment_selector specifying client as "object" type (NOT array)
        $group = [
            'name'              => 'Client',
            'object_type'       => 'Client',
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',  // <-- This tells us client is NOT an array
                        'children' => [
                            'name'          => ['type' => 'string'],
                            'date_of_birth' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $extractionResult = [
            'data'         => ['name' => 'Abdi, Abdinasir', 'date_of_birth' => '11/16/1995'],
            'search_query' => null,
        ];

        // When: Building identity artifact at level 1 with parent object
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: null
        );

        // Then: json_content shows hierarchical structure WITHOUT array wrapping for client
        // Hierarchy is derived from DB relationships
        $jsonContent = $artifacts[0]->json_content;

        // Parent's id and type are at root level
        $this->assertEquals($parentTeamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Client data is nested directly (NOT as array) because fragment_selector type is "object"
        $this->assertArrayHasKey('client', $jsonContent);
        $this->assertIsArray($jsonContent['client']);

        // CRITICAL: It should NOT be wrapped in an array - accessing directly, not via [0]
        $this->assertArrayHasKey('id', $jsonContent['client'], 'Client should be an object, not an array');
        $this->assertEquals($childTeamObject->id, $jsonContent['client']['id']);
        $this->assertEquals('Client', $jsonContent['client']['type']);
        $this->assertEquals('Abdi, Abdinasir', $jsonContent['client']['name']);
        $this->assertEquals('11/16/1995', $jsonContent['client']['date_of_birth']);
    }

    #[Test]
    public function buildIdentityArtifact_with_array_type_fragment_selector_wraps_in_array(): void
    {
        // Given: A parent TeamObject (Demand) and child TeamObject (Incident)
        // where the fragment_selector specifies incidents as type: "array"
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Incident',
            'name'    => 'Car Accident',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'incidents',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Group with fragment_selector specifying incidents as "array" type
        $group = [
            'name'              => 'Incident',
            'object_type'       => 'Incident',
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'incidents' => [
                        'type'     => 'array',  // <-- This tells us incidents IS an array
                        'children' => [
                            'name'        => ['type' => 'string'],
                            'date'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $extractionResult = [
            'data'         => ['name' => 'Car Accident', 'date' => '2024-01-15', 'description' => 'Rear-end collision'],
            'search_query' => null,
        ];

        // When: Building identity artifact at level 1 with parent object
        $artifacts = $this->builder->buildIdentityArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractionResult: $extractionResult,
            level: 1,
            matchId: null
        );

        // Then: json_content shows hierarchical structure WITH array wrapping for incidents
        $jsonContent = $artifacts[0]->json_content;

        // Parent's id and type are at root level
        $this->assertEquals($parentTeamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Incidents data is nested as an array because fragment_selector type is "array"
        $this->assertArrayHasKey('incidents', $jsonContent);
        $this->assertIsArray($jsonContent['incidents']);
        $this->assertCount(1, $jsonContent['incidents']);

        // Access via array index [0]
        $incidentData = $jsonContent['incidents'][0];
        $this->assertEquals($childTeamObject->id, $incidentData['id']);
        $this->assertEquals('Incident', $incidentData['type']);
        $this->assertEquals('Car Accident', $incidentData['name']);
        $this->assertEquals('2024-01-15', $incidentData['date']);
        $this->assertEquals('Rear-end collision', $incidentData['description']);
    }

    #[Test]
    public function buildRemainingArtifact_with_object_type_fragment_selector_does_not_wrap_in_array(): void
    {
        // Given: A parent TeamObject (Demand) and child TeamObject (Client)
        // where the fragment_selector specifies client as type: "object" (not array)
        $parentTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Demand',
            'name'    => 'Smith Demand',
        ]);

        $childTeamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'type'    => 'Client',
            'name'    => 'John Doe',
        ]);

        // Create DB relationship: parent -> child
        TeamObjectRelationship::factory()->create([
            'team_object_id'         => $parentTeamObject->id,
            'related_team_object_id' => $childTeamObject->id,
            'relationship_name'      => 'client',
        ]);

        // Create input artifact
        $inputArtifact = Artifact::factory()->create([
            'task_run_id' => $this->taskRun->id,
            'team_id'     => $this->user->currentTeam->id,
        ]);

        $taskProcess = TaskProcess::factory()->create(['task_run_id' => $this->taskRun->id]);
        $taskProcess->inputArtifacts()->attach($inputArtifact->id);

        // Group with fragment_selector specifying client as "object" type (NOT array)
        $group = [
            'name'              => 'Client Details',
            'object_type'       => 'Client',
            'fragment_selector' => [
                'type'     => 'object',
                'children' => [
                    'client' => [
                        'type'     => 'object',  // <-- This tells us client is NOT an array
                        'children' => [
                            'address' => ['type' => 'string'],
                            'phone'   => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $extractedData = [
            'address' => '123 Main St',
            'phone'   => '555-1234',
        ];

        // When: Building remaining artifact at level 1 with parent object
        $artifacts = $this->builder->buildRemainingArtifact(
            taskRun: $this->taskRun,
            taskProcess: $taskProcess,
            teamObject: $childTeamObject,
            group: $group,
            extractedData: $extractedData,
            level: 1,
            searchMode: 'exhaustive'
        );

        // Then: json_content shows hierarchical structure WITHOUT array wrapping for client
        // Hierarchy is derived from DB relationships
        $jsonContent = $artifacts[0]->json_content;

        // Parent's id and type are at root level
        $this->assertEquals($parentTeamObject->id, $jsonContent['id']);
        $this->assertEquals('Demand', $jsonContent['type']);

        // Client data is nested directly (NOT as array) because fragment_selector type is "object"
        $this->assertArrayHasKey('client', $jsonContent);
        $this->assertIsArray($jsonContent['client']);

        // CRITICAL: It should NOT be wrapped in an array - accessing directly, not via [0]
        $this->assertArrayHasKey('id', $jsonContent['client'], 'Client should be an object, not an array');
        $this->assertEquals($childTeamObject->id, $jsonContent['client']['id']);
        $this->assertEquals('Client', $jsonContent['client']['type']);
        $this->assertEquals('123 Main St', $jsonContent['client']['address']);
        $this->assertEquals('555-1234', $jsonContent['client']['phone']);
    }
}
