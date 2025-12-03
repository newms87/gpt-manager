<?php

namespace Tests\Feature\Services\UiDemand;

use App\Models\Demand\UiDemand;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowConfigService;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandWorkflowConfigServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected UiDemandWorkflowConfigService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(UiDemandWorkflowConfigService::class);

        // Clear cache before each test
        $this->service->clearCache();
    }

    #[Test]
    public function loads_workflows_from_yaml_config(): void
    {
        $workflows = $this->service->getWorkflows();

        $this->assertIsArray($workflows);
        $this->assertCount(3, $workflows);

        // Check workflow keys
        $keys = array_column($workflows, 'key');
        $this->assertEquals(['extract_data', 'write_medical_summary', 'write_demand_letter'], $keys);
    }

    #[Test]
    public function gets_single_workflow_by_key(): void
    {
        $workflow = $this->service->getWorkflow('extract_data');

        $this->assertIsArray($workflow);
        $this->assertEquals('extract_data', $workflow['key']);
        $this->assertEquals('Extract Service Dates', $workflow['name']);
        $this->assertEquals('Extract Data', $workflow['label']);
        $this->assertEquals('blue', $workflow['color']);
        $this->assertTrue($workflow['extracts_data']);
    }

    #[Test]
    public function returns_null_for_nonexistent_workflow(): void
    {
        $workflow = $this->service->getWorkflow('nonexistent_workflow');

        $this->assertNull($workflow);
    }

    #[Test]
    public function gets_schema_definition(): void
    {
        $schemaDefinition = $this->service->getSchemaDefinition();

        $this->assertEquals('Demand Schema', $schemaDefinition);
    }

    #[Test]
    public function gets_workflow_dependencies(): void
    {
        // extract_data has no dependencies
        $dependencies = $this->service->getDependencies('extract_data');
        $this->assertEmpty($dependencies);

        // write_medical_summary depends on extract_data
        $dependencies = $this->service->getDependencies('write_medical_summary');
        $this->assertEquals(['extract_data'], $dependencies);

        // write_demand_letter depends on write_medical_summary
        $dependencies = $this->service->getDependencies('write_demand_letter');
        $this->assertEquals(['write_medical_summary'], $dependencies);
    }

    #[Test]
    public function throws_exception_for_nonexistent_workflow_dependencies(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found");

        $this->service->getDependencies('nonexistent');
    }

    #[Test]
    public function gets_workflow_dependents(): void
    {
        // extract_data is depended on by write_medical_summary
        $dependents = $this->service->getDependents('extract_data');
        $this->assertEquals(['write_medical_summary'], $dependents);

        // write_medical_summary is depended on by write_demand_letter
        $dependents = $this->service->getDependents('write_medical_summary');
        $this->assertEquals(['write_demand_letter'], $dependents);

        // write_demand_letter has no dependents
        $dependents = $this->service->getDependents('write_demand_letter');
        $this->assertEmpty($dependents);
    }

    #[Test]
    public function can_run_workflow_returns_false_when_already_running(): void
    {
        $demand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // Create a running workflow
        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'Running',
        ]);
        $demand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        $canRun = $this->service->canRunWorkflow($demand, 'extract_data');

        $this->assertFalse($canRun);
    }

    #[Test]
    public function can_run_workflow_returns_false_when_input_files_required_but_missing(): void
    {
        $demand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        // extract_data requires input files
        $canRun = $this->service->canRunWorkflow($demand, 'extract_data');

        $this->assertFalse($canRun);
    }

    #[Test]
    public function can_run_workflow_returns_false_when_team_object_required_but_missing(): void
    {
        $demand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'team_object_id' => null,
        ]);

        // write_medical_summary requires team_object
        $canRun = $this->service->canRunWorkflow($demand, 'write_medical_summary');

        $this->assertFalse($canRun);
    }

    #[Test]
    public function can_run_workflow_returns_false_when_dependencies_not_completed(): void
    {
        $teamObject = \App\Models\TeamObject\TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $demand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
        ]);

        // Create a pending extract_data workflow
        $workflowRun = WorkflowRun::factory()->create([
            'status' => 'Pending',
        ]);
        $demand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        // write_medical_summary depends on extract_data being completed
        $canRun = $this->service->canRunWorkflow($demand, 'write_medical_summary');

        $this->assertFalse($canRun);
    }

    #[Test]
    public function can_run_workflow_returns_true_when_all_conditions_met(): void
    {
        $teamObject = \App\Models\TeamObject\TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $demand = UiDemand::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'team_object_id' => $teamObject->id,
        ]);

        // Add input files for extract_data
        $demand->inputFiles()->attach(\Newms87\Danx\Models\Utilities\StoredFile::factory()->create()->id, ['category' => 'input']);

        // Create a completed extract_data workflow
        // Set completed_at timestamp which will automatically set status to Completed
        $workflowRun = WorkflowRun::factory()->create([
            'completed_at' => now(),
        ]);

        $demand->workflowRuns()->attach($workflowRun->id, ['workflow_type' => 'extract_data']);

        // write_medical_summary should be able to run now
        $canRun = $this->service->canRunWorkflow($demand, 'write_medical_summary');

        $this->assertTrue($canRun);
    }

    #[Test]
    public function gets_workflow_display_config(): void
    {
        // extract_data has no display artifacts
        $displayConfig = $this->service->getWorkflowDisplayConfig('extract_data');
        $this->assertFalse($displayConfig);

        // write_medical_summary has display artifacts
        $displayConfig = $this->service->getWorkflowDisplayConfig('write_medical_summary');
        $this->assertIsArray($displayConfig);
        $this->assertEquals('Medical Summaries', $displayConfig['section_title']);
        $this->assertEquals('medical_summary', $displayConfig['artifact_category']);
        $this->assertTrue($displayConfig['editable']);
        $this->assertTrue($displayConfig['deletable']);

        // write_demand_letter has display artifacts with files
        $displayConfig = $this->service->getWorkflowDisplayConfig('write_demand_letter');
        $this->assertIsArray($displayConfig);
        $this->assertEquals('Output Documents', $displayConfig['section_title']);
        $this->assertEquals('files', $displayConfig['display_type']);
        $this->assertFalse($displayConfig['editable']);
    }

    #[Test]
    public function formats_workflows_for_api_response(): void
    {
        $workflows = $this->service->getWorkflowsForApi();

        $this->assertIsArray($workflows);
        $this->assertCount(3, $workflows);

        // Check first workflow structure
        $firstWorkflow = $workflows[0];
        $this->assertArrayHasKey('key', $firstWorkflow);
        $this->assertArrayHasKey('name', $firstWorkflow);
        $this->assertArrayHasKey('label', $firstWorkflow);
        $this->assertArrayHasKey('description', $firstWorkflow);
        $this->assertArrayHasKey('color', $firstWorkflow);
        $this->assertArrayHasKey('extracts_data', $firstWorkflow);
        $this->assertArrayHasKey('depends_on', $firstWorkflow);
        $this->assertArrayHasKey('input', $firstWorkflow);
        $this->assertArrayHasKey('template_categories', $firstWorkflow);
        $this->assertArrayHasKey('instruction_categories', $firstWorkflow);
        $this->assertArrayHasKey('display_artifacts', $firstWorkflow);
    }

    #[Test]
    public function caches_configuration(): void
    {
        // First call should cache
        $this->service->getWorkflows();
        $this->assertTrue(Cache::has('ui_demand_workflow_config'));

        // Clear cache
        $this->service->clearCache();
        $this->assertFalse(Cache::has('ui_demand_workflow_config'));
    }
}
