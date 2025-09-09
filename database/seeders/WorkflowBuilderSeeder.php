<?php

namespace Database\Seeders;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\Task\Runners\TaskDefinitionBuilderTaskRunner;
use App\Services\Task\Runners\WorkflowDefinitionBuilderTaskRunner;
use App\Services\Task\Runners\WorkflowInputTaskRunner;
use App\Services\Task\Runners\WorkflowOutputTaskRunner;
use Illuminate\Database\Seeder;

class WorkflowBuilderSeeder extends Seeder
{
    public function run(): void
    {
        // Use the first available team (Team Dan from TestingSeeder)
        $team = Team::first();
        
        if (!$team) {
            $this->command->error('No team found. Run TestingSeeder first.');
            return;
        }

        // Create required agents for workflow building
        $this->createRequiredAgents($team);

        // Create the LLM Workflow Builder WorkflowDefinition
        $workflowDefinition = WorkflowDefinition::firstOrCreate(
            [
                'name' => 'LLM Workflow Builder',
                'team_id' => $team->id,
            ],
            [
                'description' => 'Automated system for creating and modifying workflow definitions through natural language conversations with AI agents.',
                'max_workers' => 10,
            ]
        );

        // Create Task Definitions for each node
        $workflowInputTaskDef = $this->createTaskDefinition($team, [
            'name' => 'Workflow Builder Input',
            'description' => 'Accepts user input and requirements for workflow building',
            'task_runner_name' => WorkflowInputTaskRunner::RUNNER_NAME,
            'prompt' => null,
        ]);

        $workflowOrchestratorTaskDef = $this->createTaskDefinition($team, [
            'name' => 'Workflow Orchestrator',
            'description' => 'Analyzes requirements and creates task specifications for the workflow',
            'task_runner_name' => WorkflowDefinitionBuilderTaskRunner::RUNNER_NAME,
            'prompt' => 'Analyze the user requirements and current workflow state to create a comprehensive plan with individual task specifications.',
            'output_artifact_mode' => 'split',
            'timeout_after_seconds' => 120,
        ]);

        $taskBuilderTaskDef = $this->createTaskDefinition($team, [
            'name' => 'Task Definition Builder',
            'description' => 'Creates individual task definitions from specifications',
            'task_runner_name' => TaskDefinitionBuilderTaskRunner::RUNNER_NAME,
            'prompt' => 'Create a complete task definition based on the provided specification and workflow context.',
            'input_artifact_mode' => 'split',
            'timeout_after_seconds' => 60,
        ]);

        $workflowOutputTaskDef = $this->createTaskDefinition($team, [
            'name' => 'Workflow Builder Output',
            'description' => 'Collects completed task definitions and outputs final workflow artifacts',
            'task_runner_name' => WorkflowOutputTaskRunner::RUNNER_NAME,
            'prompt' => null,
        ]);

        // Create Workflow Nodes
        $inputNode = $this->createWorkflowNode($workflowDefinition, $workflowInputTaskDef, [
            'name' => 'Workflow Input',
        ]);

        $orchestratorNode = $this->createWorkflowNode($workflowDefinition, $workflowOrchestratorTaskDef, [
            'name' => 'Workflow Orchestrator',
        ]);

        $taskBuilderNode = $this->createWorkflowNode($workflowDefinition, $taskBuilderTaskDef, [
            'name' => 'Task Definition Builder',
        ]);

        $outputNode = $this->createWorkflowNode($workflowDefinition, $workflowOutputTaskDef, [
            'name' => 'Workflow Output',
        ]);

        // Create Workflow Connections
        $this->createWorkflowConnection($workflowDefinition, $inputNode, $orchestratorNode, [
            'name' => 'Input to Orchestrator',
        ]);

        $this->createWorkflowConnection($workflowDefinition, $orchestratorNode, $taskBuilderNode, [
            'name' => 'Orchestrator to Task Builder',
        ]);

        $this->createWorkflowConnection($workflowDefinition, $taskBuilderNode, $outputNode, [
            'name' => 'Task Builder to Output',
        ]);

        $this->command->info("Created 'LLM Workflow Builder' workflow definition with {$workflowDefinition->workflowNodes()->count()} nodes and {$workflowDefinition->workflowConnections()->count()} connections.");
    }

    /**
     * Create the required agents for workflow building
     */
    private function createRequiredAgents(Team $team): void
    {
        // Create Workflow Planner agent
        Agent::firstOrCreate(
            [
                'name' => 'Workflow Planner',
                'team_id' => $team->id,
            ],
            [
                'description' => 'Specialized agent for analyzing user requirements and creating workflow plans',
                'model' => 'claude-3-5-sonnet-20241022',
                'api_options' => [
                    'temperature' => 0.3, // Lower temperature for more structured planning
                    'max_tokens' => 4000,
                ],
            ]
        );

        // Create Workflow Evaluator agent
        Agent::firstOrCreate(
            [
                'name' => 'Workflow Evaluator',
                'team_id' => $team->id,
            ],
            [
                'description' => 'Specialized agent for evaluating completed workflow builds and providing user-friendly summaries',
                'model' => 'claude-3-5-sonnet-20241022',
                'api_options' => [
                    'temperature' => 0.5, // Balanced temperature for evaluation and communication
                    'max_tokens' => 3000,
                ],
            ]
        );

        $this->command->info("Created Workflow Planner and Workflow Evaluator agents.");
    }

    private function createTaskDefinition(Team $team, array $attributes): TaskDefinition
    {
        $defaults = [
            'team_id' => $team->id,
            'task_runner_config' => null,
            'response_format' => null,
            'input_artifact_mode' => null,
            'input_artifact_levels' => null,
            'output_artifact_mode' => null,
            'output_artifact_levels' => null,
            'timeout_after_seconds' => 300,
            'schema_definition_id' => null,
            'agent_id' => null,
            'task_queue_type_id' => null,
        ];

        return TaskDefinition::firstOrCreate(
            [
                'name' => $attributes['name'],
                'team_id' => $team->id,
            ],
            array_merge($defaults, $attributes)
        );
    }

    private function createWorkflowNode(WorkflowDefinition $workflowDefinition, TaskDefinition $taskDefinition, array $attributes): WorkflowNode
    {
        $defaults = [
            'workflow_definition_id' => $workflowDefinition->id,
            'task_definition_id' => $taskDefinition->id,
            'settings' => null,
            'params' => null,
        ];

        return WorkflowNode::firstOrCreate(
            [
                'workflow_definition_id' => $workflowDefinition->id,
                'task_definition_id' => $taskDefinition->id,
                'name' => $attributes['name'],
            ],
            array_merge($defaults, $attributes)
        );
    }

    private function createWorkflowConnection(WorkflowDefinition $workflowDefinition, WorkflowNode $sourceNode, WorkflowNode $targetNode, array $attributes): WorkflowConnection
    {
        $defaults = [
            'workflow_definition_id' => $workflowDefinition->id,
            'source_node_id' => $sourceNode->id,
            'target_node_id' => $targetNode->id,
            'source_output_port' => null,
            'target_input_port' => null,
        ];

        return WorkflowConnection::firstOrCreate(
            [
                'workflow_definition_id' => $workflowDefinition->id,
                'source_node_id' => $sourceNode->id,
                'target_node_id' => $targetNode->id,
            ],
            array_merge($defaults, $attributes)
        );
    }
}