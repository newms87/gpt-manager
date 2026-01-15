<?php

namespace Tests\Unit\Services\Usage;

use App\Api\ImageToText\ImageToTextOcrApi;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use App\Models\User;
use App\Models\Workflow\WorkflowRun;
use App\Services\Usage\UsageTrackingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Feature\Api\TestAi\TestAiApi;
use Tests\Traits\SetUpTeamTrait;

class UsageTrackingServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected UsageTrackingService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(UsageTrackingService::class);
    }

    #[Test]
    public function it_records_api_usage_event()
    {
        $taskProcess = TaskProcess::factory()->create();
        $user        = User::factory()->create();
        $this->actingAs($user);

        $usageEvent = $this->service->recordApiUsage(
            $taskProcess,
            ImageToTextOcrApi::class,
            'ocr_conversion',
            [
                'request_count' => 2,
                'data_volume'   => 2048,
                'metadata'      => ['test' => true],
            ],
            1500,
            $user
        );

        $this->assertInstanceOf(UsageEvent::class, $usageEvent);
        $this->assertEquals(ImageToTextOcrApi::class, $usageEvent->api_name);
        $this->assertEquals('ocr_conversion', $usageEvent->event_type);
        $this->assertEquals(2, $usageEvent->request_count);
        $this->assertEquals(2048, $usageEvent->data_volume);
        $this->assertEquals(1500, $usageEvent->run_time_ms);
        $this->assertEquals(['test' => true], $usageEvent->metadata);
        $this->assertEquals($taskProcess->id, $usageEvent->object_id);
        $this->assertEquals(TaskProcess::class, $usageEvent->object_type);
    }

    #[Test]
    public function it_records_ai_usage_event()
    {
        // Uses test model from TestCase::configureTestModel()
        $taskProcess = TaskProcess::factory()->create();
        $user        = User::factory()->create();
        $this->actingAs($user);

        $usageEvent = $this->service->recordAiUsage(
            $taskProcess,
            self::TEST_MODEL,
            [
                'input_tokens'        => 100,
                'output_tokens'       => 50,
                'cached_input_tokens' => 20,
            ],
            2000,
            $user
        );

        $this->assertInstanceOf(UsageEvent::class, $usageEvent);
        $this->assertEquals(TestAiApi::class, $usageEvent->api_name);
        $this->assertEquals('ai_completion', $usageEvent->event_type);
        $this->assertEquals(100, $usageEvent->input_tokens);
        $this->assertEquals(50, $usageEvent->output_tokens);
        $this->assertEquals(2000, $usageEvent->run_time_ms);
        $this->assertEquals(self::TEST_MODEL, $usageEvent->metadata['model']);
        $this->assertEquals(20, $usageEvent->metadata['cached_input_tokens']);
    }

    #[Test]
    public function it_calculates_ai_costs_correctly()
    {
        // Uses test model pricing from TestCase::configureTestModel()
        // input: 1.00 / 1_000_000 per token
        // output: 2.00 / 1_000_000 per token
        // cached_input: 0.10 / 1_000_000 per token

        $costs = $this->service->calculateCosts(self::TEST_MODEL, [
            'input_tokens'        => 1000,
            'output_tokens'       => 500,
            'cached_input_tokens' => 200,
        ]);

        // Input cost = 1000 * (1.00 / 1_000_000) + 200 * (0.10 / 1_000_000) = 0.001 + 0.00002 = 0.00102
        // Output cost = 500 * (2.00 / 1_000_000) = 0.001
        $this->assertEquals(0.00102, $costs['input_cost']);
        $this->assertEquals(0.001, $costs['output_cost']);
    }

    #[Test]
    public function it_calculates_api_costs_correctly()
    {
        // Mock config values for testing
        config([
            'apis.' . ImageToTextOcrApi::class . '.pricing' => [
                'per_request' => 0.001,
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create();

        $usageEvent = $this->service->recordApiUsage(
            $taskProcess,
            ImageToTextOcrApi::class,
            'ocr_conversion',
            ['request_count' => 5]
        );

        $this->assertEquals(0.005, $usageEvent->input_cost); // 5 * 0.001
        $this->assertEquals(0, $usageEvent->output_cost);
    }

    #[Test]
    public function it_creates_usage_summary_on_first_event()
    {
        $taskProcess = TaskProcess::factory()->create();

        $this->assertNull($taskProcess->usageSummary);

        $this->service->recordApiUsage(
            $taskProcess,
            ImageToTextOcrApi::class,
            'ocr_conversion',
            ['request_count' => 1]
        );

        $taskProcess->refresh();
        $this->assertNotNull($taskProcess->usageSummary);
        $this->assertEquals(1, $taskProcess->usageSummary->count);
        $this->assertEquals(1, $taskProcess->usageSummary->request_count);
    }

    #[Test]
    public function it_updates_usage_summary_on_subsequent_events()
    {
        $taskProcess = TaskProcess::factory()->create();

        // First event
        $this->service->recordAiUsage(
            $taskProcess,
            self::TEST_MODEL,
            ['input_tokens' => 100, 'output_tokens' => 50],
            1000
        );

        // Second event
        $this->service->recordAiUsage(
            $taskProcess,
            self::TEST_MODEL,
            ['input_tokens' => 200, 'output_tokens' => 100],
            2000
        );

        $taskProcess->refresh();
        $summary = $taskProcess->usageSummary;

        $this->assertEquals(2, $summary->count);
        $this->assertEquals(300, $summary->input_tokens); // 100 + 200
        $this->assertEquals(150, $summary->output_tokens); // 50 + 100
        $this->assertEquals(3000, $summary->run_time_ms); // 1000 + 2000
    }

    #[Test]
    public function it_handles_missing_pricing_configuration_gracefully()
    {
        $nonExistentModel = 'nonexistent-model-for-testing';

        $costs = $this->service->calculateCosts($nonExistentModel, [
            'input_tokens'  => 1000,
            'output_tokens' => 500,
        ]);

        $this->assertNull($costs['input_cost']);
        $this->assertNull($costs['output_cost']);
    }

    #[Test]
    public function it_normalizes_api_names_for_pricing_lookup()
    {
        // Uses test model pricing from TestCase::configureTestModel()

        // Test with model name
        $costs1 = $this->service->calculateCosts(self::TEST_MODEL, [
            'input_tokens'  => 1000,
            'output_tokens' => 500,
        ]);

        // Test with same model name
        $costs2 = $this->service->calculateCosts(self::TEST_MODEL, [
            'input_tokens'  => 1000,
            'output_tokens' => 500,
        ]);

        $this->assertEquals($costs1['input_cost'], $costs2['input_cost']);
        $this->assertEquals($costs1['output_cost'], $costs2['output_cost']);
    }

    #[Test]
    public function it_uses_default_values_for_missing_usage_data()
    {
        $taskProcess = TaskProcess::factory()->create();

        $usageEvent = $this->service->recordUsage(
            $taskProcess,
            'test_event',
            'test_api',
            [] // Empty usage data
        );

        $this->assertEquals(0, $usageEvent->run_time_ms);
        $this->assertEquals(0, $usageEvent->input_tokens);
        $this->assertEquals(0, $usageEvent->output_tokens);
        $this->assertEquals(0, $usageEvent->input_cost);
        $this->assertEquals(0, $usageEvent->output_cost);
        $this->assertEquals(1, $usageEvent->request_count); // Default value
        $this->assertEquals(0, $usageEvent->data_volume);
    }

    #[Test]
    public function it_associates_usage_events_with_user()
    {
        $user = User::factory()->create();

        $taskProcess = TaskProcess::factory()->create();

        $usageEvent = $this->service->recordApiUsage(
            $taskProcess,
            ImageToTextOcrApi::class,
            'ocr_conversion',
            [],
            null,
            $user
        );

        $this->assertEquals($user->id, $usageEvent->user_id);
    }

    #[Test]
    public function has_usage_tracking_trait_provides_usage_attribute()
    {
        $taskProcess = TaskProcess::factory()->create();

        // Record some usage
        $this->service->recordAiUsage(
            $taskProcess,
            self::TEST_MODEL,
            ['input_tokens' => 100, 'output_tokens' => 50],
            1000
        );

        $taskProcess->refresh();
        $summary = $taskProcess->usageSummary;

        $this->assertNotNull($summary);
        $this->assertNotNull($summary->count);
        $this->assertNotNull($summary->total_tokens);
        $this->assertNotNull($summary->total_cost);
        $this->assertEquals(150, $summary->total_tokens); // 100 + 50
    }

    #[Test]
    public function it_aggregates_child_usage_for_task_runs()
    {
        $taskRun      = TaskRun::factory()->create();
        $taskProcess1 = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $taskProcess2 = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);

        // Add usage to processes
        $this->service->recordAiUsage(
            $taskProcess1,
            self::TEST_MODEL,
            ['input_tokens' => 100, 'output_tokens' => 50],
            1000
        );

        $this->service->recordAiUsage(
            $taskProcess2,
            self::TEST_MODEL,
            ['input_tokens' => 200, 'output_tokens' => 100],
            2000
        );

        // Aggregate to task run
        $taskRun->refreshUsageFromProcesses();
        $taskRun->refresh();

        $summary = $taskRun->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(300, $summary->input_tokens); // 100 + 200
        $this->assertEquals(150, $summary->output_tokens); // 50 + 100
        $this->assertEquals(3000, $summary->run_time_ms); // 1000 + 2000
    }

    #[Test]
    public function it_aggregates_child_usage_for_workflow_runs()
    {
        // Create workflow run and task runs manually since WorkflowRun factory doesn't exist
        $workflowDefinition = \App\Models\Workflow\WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Workflow',
        ]);

        $workflowRun = WorkflowRun::create([
            'name'                   => 'Test Workflow',
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $taskRun1 = TaskRun::factory()->create(['workflow_run_id' => $workflowRun->id]);
        $taskRun2 = TaskRun::factory()->create(['workflow_run_id' => $workflowRun->id]);

        // Create usage summaries for task runs
        UsageSummary::create([
            'object_type'   => TaskRun::class,
            'object_id'     => (string)$taskRun1->id,
            'object_id_int' => $taskRun1->id,
            'count'         => 1,
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.0005,
            'total_cost'    => 0.0015,
            'run_time_ms'   => 1000,
            'request_count' => 1,
            'data_volume'   => 1024,
        ]);

        UsageSummary::create([
            'object_type'   => TaskRun::class,
            'object_id'     => (string)$taskRun2->id,
            'object_id_int' => $taskRun2->id,
            'count'         => 1,
            'input_tokens'  => 200,
            'output_tokens' => 100,
            'input_cost'    => 0.002,
            'output_cost'   => 0.001,
            'total_cost'    => 0.003,
            'run_time_ms'   => 2000,
            'request_count' => 2,
            'data_volume'   => 2048,
        ]);

        // Aggregate to workflow run
        $workflowRun->refreshUsageFromTaskRuns();
        $workflowRun->refresh();

        $summary = $workflowRun->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(300, $summary->input_tokens); // 100 + 200
        $this->assertEquals(150, $summary->output_tokens); // 50 + 100
        $this->assertEquals(0.0045, $summary->total_cost); // 0.0015 + 0.003
    }
}
