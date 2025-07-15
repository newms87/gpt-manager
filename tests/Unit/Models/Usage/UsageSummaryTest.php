<?php

namespace Tests\Unit\Models\Usage;

use App\Models\Task\TaskProcess;
use App\Models\Usage\UsageEvent;
use App\Models\Usage\UsageSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UsageSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_total_tokens_attribute()
    {
        $summary = UsageSummary::factory()->create([
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $this->assertEquals(150, $summary->total_tokens);
    }

    #[Test]
    public function it_has_polymorphic_relationship_to_object()
    {
        $taskProcess = TaskProcess::factory()->create();
        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
        ]);

        $this->assertInstanceOf(TaskProcess::class, $summary->object);
        $this->assertEquals($taskProcess->id, $summary->object->id);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $summary = UsageSummary::factory()->create([
            'count' => '10',
            'run_time_ms' => '5000',
            'input_tokens' => '1000',
            'output_tokens' => '500',
            'input_cost' => '2.5',
            'output_cost' => '5.0',
            'total_cost' => '7.5',
            'request_count' => '20',
            'data_volume' => '10240',
        ]);

        $this->assertIsInt($summary->count);
        $this->assertIsInt($summary->run_time_ms);
        $this->assertIsInt($summary->input_tokens);
        $this->assertIsInt($summary->output_tokens);
        $this->assertIsFloat($summary->input_cost);
        $this->assertIsFloat($summary->output_cost);
        $this->assertIsFloat($summary->total_cost);
        $this->assertIsInt($summary->request_count);
        $this->assertIsInt($summary->data_volume);
    }

    #[Test]
    public function it_updates_from_usage_events()
    {
        $taskProcess = TaskProcess::factory()->create();
        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'count' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'input_cost' => 0,
            'output_cost' => 0,
            'total_cost' => 0,
        ]);

        // Create usage events
        UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.001,
            'output_cost' => 0.0005,
            'run_time_ms' => 1000,
            'request_count' => 1,
            'data_volume' => 1024,
        ]);

        UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
            'input_tokens' => 200,
            'output_tokens' => 100,
            'input_cost' => 0.002,
            'output_cost' => 0.001,
            'run_time_ms' => 2000,
            'request_count' => 2,
            'data_volume' => 2048,
        ]);

        // Update summary from events
        $summary->updateFromEvents();
        $summary->refresh();

        $this->assertEquals(2, $summary->count);
        $this->assertEquals(300, $summary->input_tokens); // 100 + 200
        $this->assertEquals(150, $summary->output_tokens); // 50 + 100
        $this->assertEquals(0.003, $summary->input_cost); // 0.001 + 0.002
        $this->assertEquals(0.0015, $summary->output_cost); // 0.0005 + 0.001
        $this->assertEquals(0.0045, $summary->total_cost); // 0.003 + 0.0015
        $this->assertEquals(3000, $summary->run_time_ms); // 1000 + 2000
        $this->assertEquals(3, $summary->request_count); // 1 + 2
        $this->assertEquals(3072, $summary->data_volume); // 1024 + 2048
    }

    #[Test]
    public function it_handles_empty_event_set_in_update()
    {
        $taskProcess = TaskProcess::factory()->create();
        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'count' => 5,
            'input_tokens' => 500,
            'output_tokens' => 250,
            'total_cost' => 10.0,
        ]);

        // No events exist
        $summary->updateFromEvents();
        $summary->refresh();

        // Should be zeroed out
        $this->assertEquals(0, $summary->count);
        $this->assertEquals(0, $summary->input_tokens);
        $this->assertEquals(0, $summary->output_tokens);
        $this->assertEquals(0, $summary->total_cost);
    }

    #[Test]
    public function it_handles_null_values_in_calculations()
    {
        $summary = UsageSummary::factory()->create([
            'input_tokens' => null,
            'output_tokens' => null,
        ]);

        $this->assertEquals(0, $summary->total_tokens);
    }

    #[Test]
    public function it_relates_to_usage_events_correctly()
    {
        $taskProcess = TaskProcess::factory()->create();
        $summary = UsageSummary::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
            'object_id_int' => $taskProcess->id,
        ]);

        // Create related events
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

        // Create unrelated event
        $otherProcess = TaskProcess::factory()->create();
        UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $otherProcess->id,
            'object_id_int' => $otherProcess->id,
        ]);

        $relatedEvents = $summary->usageEvents()->get();
        
        $this->assertCount(2, $relatedEvents);
        $this->assertTrue($relatedEvents->contains($event1));
        $this->assertTrue($relatedEvents->contains($event2));
    }
}