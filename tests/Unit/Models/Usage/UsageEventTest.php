<?php

namespace Tests\Unit\Models\Usage;

use App\Models\Usage\UsageEvent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageEventTest extends TestCase
{
    #[Test]
    public function it_calculates_total_cost_attribute()
    {
        $event = UsageEvent::factory()->create([
            'input_cost'  => 1.50,
            'output_cost' => 2.50,
        ]);

        $this->assertEquals(4.00, $event->total_cost);
    }

    #[Test]
    public function it_calculates_total_tokens_attribute()
    {
        $event = UsageEvent::factory()->create([
            'input_tokens'  => 100,
            'output_tokens' => 50,
        ]);

        $this->assertEquals(150, $event->total_tokens);
    }

    #[Test]
    public function it_handles_null_values_in_cost_calculations()
    {
        $event = UsageEvent::factory()->create([
            'input_cost'    => null,
            'output_cost'   => null,
            'input_tokens'  => null,
            'output_tokens' => null,
        ]);

        $this->assertEquals(0, $event->total_cost);
        $this->assertEquals(0, $event->total_tokens);
    }
}
