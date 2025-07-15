<?php

namespace Tests\Unit\Models\Usage;

use App\Models\Task\TaskProcess;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UsageEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_total_cost_attribute()
    {
        $event = UsageEvent::factory()->create([
            'input_cost' => 1.50,
            'output_cost' => 2.50,
        ]);

        $this->assertEquals(4.00, $event->total_cost);
    }

    #[Test]
    public function it_calculates_total_tokens_attribute()
    {
        $event = UsageEvent::factory()->create([
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $this->assertEquals(150, $event->total_tokens);
    }

    #[Test]
    public function it_belongs_to_team()
    {
        $team = Team::factory()->create();
        $event = UsageEvent::factory()->create(['team_id' => $team->id]);

        $this->assertInstanceOf(Team::class, $event->team);
        $this->assertEquals($team->id, $event->team->id);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $event = UsageEvent::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $event->user);
        $this->assertEquals($user->id, $event->user->id);
    }

    #[Test]
    public function it_has_polymorphic_relationship_to_object()
    {
        $taskProcess = TaskProcess::factory()->create();
        $event = UsageEvent::factory()->create([
            'object_type' => TaskProcess::class,
            'object_id' => $taskProcess->id,
        ]);

        $this->assertInstanceOf(TaskProcess::class, $event->object);
        $this->assertEquals($taskProcess->id, $event->object->id);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $event = UsageEvent::factory()->create([
            'run_time_ms' => '1500',
            'input_tokens' => '100',
            'output_tokens' => '50',
            'input_cost' => '1.234567',
            'output_cost' => '2.345678',
            'request_count' => '5',
            'data_volume' => '2048',
            'metadata' => ['test' => 'value'],
        ]);

        $this->assertIsInt($event->run_time_ms);
        $this->assertIsInt($event->input_tokens);
        $this->assertIsInt($event->output_tokens);
        $this->assertIsFloat($event->input_cost);
        $this->assertIsFloat($event->output_cost);
        $this->assertIsInt($event->request_count);
        $this->assertIsInt($event->data_volume);
        $this->assertIsArray($event->metadata);
        
        // Check decimal precision
        $this->assertEquals(1.234567, $event->input_cost);
        $this->assertEquals(2.345678, $event->output_cost);
    }

    #[Test]
    public function it_handles_null_values_in_cost_calculations()
    {
        $event = UsageEvent::factory()->create([
            'input_cost' => null,
            'output_cost' => null,
            'input_tokens' => null,
            'output_tokens' => null,
        ]);

        $this->assertEquals(0, $event->total_cost);
        $this->assertEquals(0, $event->total_tokens);
    }

    #[Test]
    public function it_can_be_soft_deleted()
    {
        $event = UsageEvent::factory()->create();
        $eventId = $event->id;

        $event->delete();

        $this->assertSoftDeleted('usage_events', ['id' => $eventId]);
        $this->assertNotNull($event->deleted_at);
    }

    #[Test]
    public function it_stores_metadata_as_json()
    {
        $metadata = [
            'model' => 'gpt-4o',
            'cached_input_tokens' => 50,
            'api_response' => ['id' => 'test-123'],
        ];

        $event = UsageEvent::factory()->create(['metadata' => $metadata]);
        $event->refresh();

        $this->assertEquals($metadata, $event->metadata);
        $this->assertEquals('gpt-4o', $event->metadata['model']);
        $this->assertEquals(50, $event->metadata['cached_input_tokens']);
    }
}