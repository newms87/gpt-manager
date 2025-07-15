<?php

namespace Tests\Unit\Models\Traits;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_usage_events_relationship()
    {
        $taskProcess = TaskProcess::factory()->create();

        // Create usage events
        $event1 = UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
        ]);

        $event2 = UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
        ]);

        $events = $taskProcess->usageEvents()->get();

        $this->assertCount(2, $events);
        $this->assertTrue($events->contains($event1));
        $this->assertTrue($events->contains($event2));
    }

    #[Test]
    public function it_has_usage_summary_relationship()
    {
        $taskProcess = TaskProcess::factory()->create();

        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
        ]);

        $taskProcess->refresh();

        $this->assertInstanceOf(UsageSummary::class, $taskProcess->usageSummary);
        $this->assertEquals($summary->id, $taskProcess->usageSummary->id);
    }

    #[Test]
    public function it_provides_usage_attribute()
    {
        $taskProcess = TaskProcess::factory()->create();

        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'count' => 5,
            'run_time_ms' => 5000,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'input_cost' => 2.5,
            'output_cost' => 5.0,
            'total_cost' => 7.5,
            'request_count' => 10,
            'data_volume' => 2048,
        ]);

        $taskProcess->refresh();
        $usage = $taskProcess->usage;

        $this->assertIsArray($usage);
        $this->assertEquals(5, $usage['count']);
        $this->assertEquals(5000, $usage['run_time_ms']);
        $this->assertEquals(1000, $usage['input_tokens']);
        $this->assertEquals(500, $usage['output_tokens']);
        $this->assertEquals(1500, $usage['total_tokens']);
        $this->assertEquals(2.5, $usage['input_cost']);
        $this->assertEquals(5.0, $usage['output_cost']);
        $this->assertEquals(7.5, $usage['total_cost']);
        $this->assertEquals(10, $usage['request_count']);
        $this->assertEquals(2048, $usage['data_volume']);
    }

    #[Test]
    public function it_returns_null_usage_when_no_summary_exists()
    {
        $taskProcess = TaskProcess::factory()->create();

        $usage = $taskProcess->usage;

        $this->assertNull($usage);
    }

    #[Test]
    public function it_refreshes_usage_summary()
    {
        $taskProcess = TaskProcess::factory()->create();

        // Create usage events
        UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.001,
            'output_cost' => 0.0005,
        ]);

        // Refresh summary
        $taskProcess->refreshUsageSummary();
        $taskProcess->refresh();

        $this->assertNotNull($taskProcess->usageSummary);
        $this->assertEquals(1, $taskProcess->usageSummary->count);
        $this->assertEquals(100, $taskProcess->usageSummary->input_tokens);
        $this->assertEquals(50, $taskProcess->usageSummary->output_tokens);
    }

    #[Test]
    public function it_creates_usage_summary_if_not_exists()
    {
        $taskProcess = TaskProcess::factory()->create();

        $this->assertNull($taskProcess->usageSummary);

        $taskProcess->refreshUsageSummary();
        $taskProcess->refresh();

        $this->assertNotNull($taskProcess->usageSummary);
        $this->assertEquals(0, $taskProcess->usageSummary->count);
    }

    #[Test]
    public function it_aggregates_child_usage_for_task_runs()
    {
        $taskRun = TaskRun::factory()->create();
        $process1 = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);
        $process2 = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);

        // Create summaries for processes
        UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $process1->id,
            'object_id_int' => $process1->id,
            'count' => 2,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.001,
            'output_cost' => 0.0005,
            'total_cost' => 0.0015,
            'run_time_ms' => 1000,
            'request_count' => 1,
            'data_volume' => 1024,
        ]);

        UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $process2->id,
            'object_id_int' => $process2->id,
            'count' => 3,
            'input_tokens' => 200,
            'output_tokens' => 100,
            'input_cost' => 0.002,
            'output_cost' => 0.001,
            'total_cost' => 0.003,
            'run_time_ms' => 2000,
            'request_count' => 2,
            'data_volume' => 2048,
        ]);

        // Aggregate usage
        $taskRun->aggregateChildUsage('taskProcesses');
        $taskRun->refresh();

        $summary = $taskRun->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->count); // Number of child summaries
        $this->assertEquals(300, $summary->input_tokens); // 100 + 200
        $this->assertEquals(150, $summary->output_tokens); // 50 + 100
        $this->assertEquals(0.0045, $summary->total_cost); // 0.0015 + 0.003
        $this->assertEquals(3000, $summary->run_time_ms); // 1000 + 2000
        $this->assertEquals(3, $summary->request_count); // 1 + 2
        $this->assertEquals(3072, $summary->data_volume); // 1024 + 2048
    }

    #[Test]
    public function it_aggregates_child_usage_for_workflow_runs()
    {
        $workflowDefinition = WorkflowDefinition::factory()->create();
        $workflowRun = WorkflowRun::create([
            'name' => 'Test Workflow',
            'workflow_definition_id' => $workflowDefinition->id,
        ]);
        $taskRun1 = TaskRun::factory()->create(['workflow_run_id' => $workflowRun->id]);
        $taskRun2 = TaskRun::factory()->create(['workflow_run_id' => $workflowRun->id]);

        // Create summaries for task runs
        UsageSummary::factory()->create([
            'object_type' => TaskRun::class,
            'object_id' => $taskRun1->id,
            'object_id_int' => $taskRun1->id,
            'count' => 5,
            'input_tokens' => 500,
            'output_tokens' => 250,
            'total_cost' => 5.0,
        ]);

        UsageSummary::factory()->create([
            'object_type' => TaskRun::class,
            'object_id' => $taskRun2->id,
            'object_id_int' => $taskRun2->id,
            'count' => 3,
            'input_tokens' => 300,
            'output_tokens' => 150,
            'total_cost' => 3.0,
        ]);

        // Aggregate usage
        $workflowRun->aggregateChildUsage('taskRuns');
        $workflowRun->refresh();

        $summary = $workflowRun->usageSummary;
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->count); // Number of child summaries
        $this->assertEquals(800, $summary->input_tokens); // 500 + 300
        $this->assertEquals(400, $summary->output_tokens); // 250 + 150
        $this->assertEquals(8.0, $summary->total_cost); // 5.0 + 3.0
    }

    #[Test]
    public function it_handles_missing_child_relationship_gracefully()
    {
        $taskProcess = TaskProcess::factory()->create();

        // Try to aggregate non-existent relationship
        $taskProcess->aggregateChildUsage('nonExistentRelation');

        // Should not throw error
        $this->assertNull($taskProcess->usageSummary);
    }

    #[Test]
    public function it_creates_summary_when_aggregating_with_no_existing_summary()
    {
        $taskRun = TaskRun::factory()->create();
        $process = TaskProcess::factory()->create(['task_run_id' => $taskRun->id]);

        UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $process->id,
            'object_id_int' => $process->id,
            'input_cost' => 0.6,
            'output_cost' => 0.4,
            'total_cost' => 1.0,
        ]);

        $this->assertNull($taskRun->usageSummary);

        $taskRun->aggregateChildUsage('taskProcesses');
        $taskRun->refresh();

        $this->assertNotNull($taskRun->usageSummary);
        $this->assertEquals(1.0, $taskRun->usageSummary->total_cost);
    }
}