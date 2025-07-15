<?php

namespace Database\Factories\Usage;

use App\Models\Task\TaskProcess;
use App\Models\Team\Team;
use App\Models\Usage\UsageEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'object_type' => TaskProcess::class,
            'object_id' => fn() => (string) TaskProcess::factory()->create()->id,
            'event_type' => $this->faker->randomElement(['ai_completion', 'ocr_conversion', 'screenshot_capture']),
            'api_name' => $this->faker->randomElement(['openai', 'imagetotext', 'screenshotone']),
            'run_time_ms' => $this->faker->numberBetween(100, 5000),
            'input_tokens' => $this->faker->numberBetween(0, 1000),
            'output_tokens' => $this->faker->numberBetween(0, 500),
            'input_cost' => $this->faker->randomFloat(6, 0, 1),
            'output_cost' => $this->faker->randomFloat(6, 0, 1),
            'request_count' => $this->faker->numberBetween(1, 5),
            'data_volume' => $this->faker->numberBetween(0, 10240),
            'metadata' => [
                'test' => true,
                'model' => $this->faker->randomElement(['gpt-4o', 'gpt-4o-mini']),
            ],
        ];
    }

    public function forTaskProcess(TaskProcess $taskProcess): self
    {
        return $this->state([
            'object_type' => TaskProcess::class,
            'object_id' => (string) $taskProcess->id,
        ]);
    }

    public function aiCompletion(): self
    {
        return $this->state([
            'event_type' => 'ai_completion',
            'api_name' => 'openai',
            'input_tokens' => $this->faker->numberBetween(50, 1000),
            'output_tokens' => $this->faker->numberBetween(25, 500),
            'metadata' => [
                'model' => 'gpt-4o',
                'cached_input_tokens' => $this->faker->numberBetween(0, 100),
            ],
        ]);
    }

    public function ocrConversion(): self
    {
        return $this->state([
            'event_type' => 'ocr_conversion',
            'api_name' => 'imagetotext',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'request_count' => 1,
            'data_volume' => $this->faker->numberBetween(100, 5000),
            'metadata' => [
                'filename' => $this->faker->word . '.jpg',
                'file_size' => $this->faker->numberBetween(10000, 500000),
            ],
        ]);
    }
}