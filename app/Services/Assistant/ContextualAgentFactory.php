<?php

namespace App\Services\Assistant;

use App\Models\Agent\Agent;
use App\Models\Assistant\AssistantAction;

class ContextualAgentFactory
{
    protected array $contextAgents = [];

    public function getAgentForContext(string $context, array $contextData = []): Agent
    {
        // Check if we have a cached agent for this context
        if (isset($this->contextAgents[$context])) {
            return $this->contextAgents[$context];
        }

        // Create or find context-specific agent
        $agent = $this->createContextAgent($context, $contextData);
        
        // Cache the agent
        $this->contextAgents[$context] = $agent;
        
        return $agent;
    }

    protected function createContextAgent(string $context, array $contextData = []): Agent
    {
        switch ($context) {
            case AssistantAction::CONTEXT_SCHEMA_EDITOR:
                return $this->createSchemaEditorAgent();
                
            case AssistantAction::CONTEXT_WORKFLOW_EDITOR:
                return $this->createWorkflowEditorAgent();
                
            case AssistantAction::CONTEXT_AGENT_MANAGEMENT:
                return $this->createAgentManagementAgent();
                
            case AssistantAction::CONTEXT_TASK_MANAGEMENT:
                return $this->createTaskManagementAgent();
                
            default:
                return $this->createGeneralChatAgent();
        }
    }

    protected function createSchemaEditorAgent(): Agent
    {
        return Agent::firstOrCreate(
            [
                'team_id' => team()->id,
                'name' => 'Schema Design Assistant',
            ],
            [
                'description' => 'AI assistant specialized in JSON schema design and modification',
                'model' => 'o4-mini',
                'api_options' => [
                    'temperature' => 0.1,
                ],
            ]
        );
    }

    protected function createWorkflowEditorAgent(): Agent
    {
        return Agent::firstOrCreate(
            [
                'team_id' => team()->id,
                'name' => 'Workflow Design Assistant',
            ],
            [
                'description' => 'AI assistant for workflow design and automation',
                'model' => 'o4-mini',
                'api_options' => [
                    'temperature' => 0.2,
                ],
            ]
        );
    }

    protected function createAgentManagementAgent(): Agent
    {
        return Agent::firstOrCreate(
            [
                'team_id' => team()->id,
                'name' => 'Agent Management Assistant',
            ],
            [
                'description' => 'AI assistant for configuring and managing AI agents',
                'model' => 'o4-mini',
                'api_options' => [
                    'temperature' => 0.3,
                ],
            ]
        );
    }

    protected function createTaskManagementAgent(): Agent
    {
        return Agent::firstOrCreate(
            [
                'team_id' => team()->id,
                'name' => 'Task Management Assistant',
            ],
            [
                'description' => 'AI assistant for task definition and management',
                'model' => 'o4-mini',
                'api_options' => [
                    'temperature' => 0.2,
                ],
            ]
        );
    }

    protected function createGeneralChatAgent(): Agent
    {
        return Agent::firstOrCreate(
            [
                'team_id' => team()->id,
                'name' => 'General Assistant',
            ],
            [
                'description' => 'General purpose AI assistant for questions and help',
                'model' => 'o4-mini',
                'api_options' => [
                    'temperature' => 0.7,
                ],
            ]
        );
    }

}