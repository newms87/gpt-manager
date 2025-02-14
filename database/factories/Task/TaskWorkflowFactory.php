<?php

namespace Database\Factories\Task;

use App\Models\Task\TaskWorkflow;
use App\Models\Task\TaskWorkflowNode;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskWorkflowFactory extends Factory
{
	public function definition(): array
	{
		return [
			'team_id' => Team::factory(),
			'name'    => fake()->unique()->name,
		];
	}

	public function withNodes(int $count = 2): static
	{
		return $this->afterCreating(function (TaskWorkflow $taskWorkflow) use ($count) {
			TaskWorkflowNode::factory()->count($count)->create(['task_workflow_id' => $taskWorkflow]);
		});
	}
}
