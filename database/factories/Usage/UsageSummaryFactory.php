<?php

namespace Database\Factories\Usage;

use App\Models\Task\TaskProcess;
use App\Models\Usage\UsageSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageSummaryFactory extends Factory
{
    protected $model = UsageSummary::class;

    public function definition(): array
    {
        $inputCost = $this->faker->randomFloat(6, 0, 1);
        $outputCost = $this->faker->randomFloat(6, 0, 1);

        return [
            'object_type' => TaskProcess::class,
            'object_id' => fn() => (string) TaskProcess::factory()->create()->id,
            'count' => $this->faker->numberBetween(1, 10),
            'run_time_ms' => $this->faker->numberBetween(1000, 10000),
            'input_tokens' => $this->faker->numberBetween(0, 2000),
            'output_tokens' => $this->faker->numberBetween(0, 1000),
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'total_cost' => $inputCost + $outputCost,
            'request_count' => $this->faker->numberBetween(1, 20),
            'data_volume' => $this->faker->numberBetween(0, 20480),
        ];
    }

    public function forTaskProcess(TaskProcess $taskProcess): self
    {
        return $this->state([
            'object_type' => TaskProcess::class,
            'object_id' => (string) $taskProcess->id,
        ]);
    }

    public function withSpecificCosts(float $inputCost, float $outputCost): self
    {
        return $this->state([
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'total_cost' => $inputCost + $outputCost,
        ]);
    }

    public function withTokens(int $inputTokens, int $outputTokens): self
    {
        return $this->state([
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);
    }
}