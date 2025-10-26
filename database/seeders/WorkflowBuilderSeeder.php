<?php

namespace Database\Seeders;

use App\Models\Agent\Agent;
use App\Models\Task\TaskDefinition;
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
        // Create required system agents (no team ownership)
        [$workflowPlannerAgent, $workflowEvaluatorAgent] = $this->createRequiredAgents();

        // Create the LLM Workflow Builder WorkflowDefinition (system-owned)
        $workflowDefinition = WorkflowDefinition::firstOrCreate(
            [
                'name'    => 'LLM Workflow Builder',
                'team_id' => null, // System-owned workflow
            ],
            [
                'description' => 'Automated system for creating and modifying workflow definitions through natural language conversations with AI agents.',
                'max_workers' => 10,
            ]
        );

        // Create Task Definitions for each node (system-owned)
        $workflowInputTaskDef = $this->createTaskDefinition([
            'name'             => 'Workflow Builder Input',
            'description'      => 'Accepts user input and requirements for workflow building',
            'task_runner_name' => WorkflowInputTaskRunner::RUNNER_NAME,
            'prompt'           => null,
        ]);

        $workflowOrchestratorTaskDef = $this->createTaskDefinition([
            'name'                  => 'Workflow Orchestrator',
            'description'           => 'Analyzes requirements and creates task specifications for the workflow',
            'task_runner_name'      => WorkflowDefinitionBuilderTaskRunner::RUNNER_NAME,
            'prompt'                => 'Analyze the user requirements and current workflow state to create a comprehensive plan with individual task specifications.',
            'output_artifact_mode'  => 'split',
            'timeout_after_seconds' => 120,
            'agent_id'              => $workflowPlannerAgent->id,
        ]);

        $taskBuilderTaskDef = $this->createTaskDefinition([
            'name'                  => 'Task Definition Builder',
            'description'           => 'Creates individual task definitions from specifications',
            'task_runner_name'      => TaskDefinitionBuilderTaskRunner::RUNNER_NAME,
            'prompt'                => 'Create a complete task definition based on the provided specification and workflow context.',
            'input_artifact_mode'   => 'split',
            'timeout_after_seconds' => 60,
        ]);

        $workflowOutputTaskDef = $this->createTaskDefinition([
            'name'             => 'Workflow Builder Output',
            'description'      => 'Collects completed task definitions and outputs final workflow artifacts',
            'task_runner_name' => WorkflowOutputTaskRunner::RUNNER_NAME,
            'prompt'           => null,
            'agent_id'         => $workflowEvaluatorAgent->id,
        ]);

        // Create Workflow Nodes
        $inputNode = $this->createWorkflowNode($workflowDefinition, $workflowInputTaskDef,
            ['name' => 'Workflow Input'],
            ['x'    => 0, 'y' => 0]
        );

        $orchestratorNode = $this->createWorkflowNode($workflowDefinition, $workflowOrchestratorTaskDef,
            ['name' => 'Workflow Orchestrator'],
            ['x'    => 400, 'y' => 0]
        );

        $taskBuilderNode = $this->createWorkflowNode($workflowDefinition, $taskBuilderTaskDef,
            ['name' => 'Task Definition Builder'],
            ['x'    => 800, 'y' => 0]
        );

        $outputNode = $this->createWorkflowNode($workflowDefinition, $workflowOutputTaskDef,
            ['name' => 'Workflow Output'],
            ['x'    => 1200, 'y' => 0]
        );

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
     * Create the required system agents for workflow building (no team ownership)
     *
     * @return array{0: Agent, 1: Agent} Returns [workflowPlannerAgent, workflowEvaluatorAgent]
     */
    private function createRequiredAgents(): array
    {
        // Create Workflow Planner agent (system-owned)
        $workflowPlannerAgent = Agent::firstOrCreate(
            [
                'name'    => 'Workflow Planner',
                'team_id' => null, // System-owned agent
            ],
            [
                'description' => 'Specialized agent for analyzing user requirements and creating workflow plans',
                'model'       => 'gpt-5',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ]
        );

        // Create Workflow Evaluator agent (system-owned)
        $workflowEvaluatorAgent = Agent::firstOrCreate(
            [
                'name'    => 'Workflow Evaluator',
                'team_id' => null, // System-owned agent
            ],
            [
                'description' => 'Specialized agent for evaluating completed workflow builds and providing user-friendly summaries',
                'model'       => 'gpt-5',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ]
        );

        $this->command->info('Created system-owned Workflow Planner and Workflow Evaluator agents.');

        return [$workflowPlannerAgent, $workflowEvaluatorAgent];
    }

    private function createTaskDefinition(array $attributes): TaskDefinition
    {
        $defaults = [
            'team_id'                => null, // System-owned task definition
            'task_runner_config'     => null,
            'response_format'        => null,
            'input_artifact_mode'    => null,
            'input_artifact_levels'  => null,
            'output_artifact_mode'   => null,
            'output_artifact_levels' => null,
            'timeout_after_seconds'  => 300,
            'schema_definition_id'   => null,
            'agent_id'               => null,
            'task_queue_type_id'     => null,
        ];

        return TaskDefinition::firstOrCreate(
            [
                'name'    => $attributes['name'],
                'team_id' => null, // System-owned
            ],
            $attributes + $defaults
        );
    }

    private function createWorkflowNode(WorkflowDefinition $workflowDefinition, TaskDefinition $taskDefinition, array $attributes, array $settings = []): WorkflowNode
    {
        $defaults = [
            'workflow_definition_id' => $workflowDefinition->id,
            'task_definition_id'     => $taskDefinition->id,
            'settings'               => $settings,
            'params'                 => null,
        ];

        return WorkflowNode::firstOrCreate(
            [
                'workflow_definition_id' => $workflowDefinition->id,
                'task_definition_id'     => $taskDefinition->id,
                'name'                   => $attributes['name'],
            ],
            $attributes + $defaults
        );
    }

    private function createWorkflowConnection(WorkflowDefinition $workflowDefinition, WorkflowNode $sourceNode, WorkflowNode $targetNode, array $attributes): WorkflowConnection
    {
        $defaults = [
            'workflow_definition_id' => $workflowDefinition->id,
            'source_node_id'         => $sourceNode->id,
            'target_node_id'         => $targetNode->id,
            'source_output_port'     => 'default',
            'target_input_port'      => 'default',
        ];

        return WorkflowConnection::firstOrCreate(
            [
                'workflow_definition_id' => $workflowDefinition->id,
                'source_node_id'         => $sourceNode->id,
                'target_node_id'         => $targetNode->id,
            ],
            $attributes + $defaults
        );
    }
}
