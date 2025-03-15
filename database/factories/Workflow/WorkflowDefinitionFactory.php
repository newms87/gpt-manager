<?php

namespace Database\Factories\Workflow;

use App\Models\Team\Team;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowDefinitionFactory extends Factory
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
		return $this->afterCreating(function (WorkflowDefinition $workflowDefinition) use ($count) {
			WorkflowNode::factory()->count($count)->create(['workflow_definition_id' => $workflowDefinition]);
		});
	}
}
