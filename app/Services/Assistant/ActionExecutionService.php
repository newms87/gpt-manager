<?php

namespace App\Services\Assistant;

use App\Models\Agent\Agent;
use App\Models\Assistant\AssistantAction;
use App\Models\Schema\SchemaDefinition;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Assistant\Context\ContextServiceInterface;

class ActionExecutionService
{
    /**
     * Execute an action using the o3 model in a separate thread
     */
    public function executeAction(AssistantAction $action): array
    {
        try {
            $action->markInProgress();

            // Create a separate agent for action execution using o3 model
            $actionAgent = $this->getActionAgent();

            // Create a new thread for action execution
            $actionThread = app(ThreadRepository::class)->create($actionAgent, "Action: {$action->title}");

            // Get the original thread context
            $originalThread = $action->agentThread;
            $contextResources = $action->payload['context_resources'] ?? [];
            $threadContext = $action->payload['thread_context'] ?? 'general-chat';

            // Build action prompt with full context
            $actionPrompt = $this->buildActionPrompt($action, $originalThread, $contextResources);

            // Add the action prompt as first message
            app(ThreadRepository::class)->addMessageToThread($actionThread, $actionPrompt);

            // Create action-specific response schema
            $actionResponseSchema = $this->getActionResponseSchema($action->payload['action_name'] ?? 'general');

            // Execute the action using o3 model
            $agentThreadService = app(AgentThreadService::class)
                ->withResponseFormat($actionResponseSchema);

            $threadRun = $agentThreadService->dispatch($actionThread);
            $lastMessage = $threadRun->lastMessage;

            if ($lastMessage) {
                $responseData = json_decode($lastMessage->content, true);
                
                $action->markCompleted([
                    'result' => $responseData,
                    'action_thread_id' => $actionThread->id,
                    'execution_details' => [
                        'model' => $actionAgent->model,
                        'tokens_used' => $threadRun->input_tokens + $threadRun->output_tokens,
                        'execution_time' => $threadRun->completed_at?->diffInSeconds($threadRun->started_at)
                    ]
                ]);

                return [
                    'success' => true,
                    'result' => $responseData,
                    'action_thread' => $actionThread,
                    'message' => $responseData['message'] ?? 'Action completed successfully'
                ];
            } else {
                throw new \Exception('No response generated from action execution');
            }

        } catch (\Exception $e) {
            $action->markFailed($e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'action_id' => $action->id
            ];
        }
    }

    /**
     * Get or create an agent specifically for action execution using o3 model
     */
    protected function getActionAgent(): Agent
    {
        return Agent::firstOrCreate([
            'team_id' => team()->id,
            'name' => 'Action Execution Agent (O3)'
        ], [
            'description' => 'Specialized agent for executing complex actions using the O3 model',
            'model' => 'o3',
            'temperature' => 0.1, // Lower temperature for more precise actions
        ]);
    }

    /**
     * Build the action prompt with full context from the original thread
     */
    protected function buildActionPrompt(AssistantAction $action, $originalThread, array $contextResources): string
    {
        $prompt = "EXECUTE ACTION: {$action->payload['action_name']}\n\n";
        $prompt .= "Original Request Context:\n";
        $prompt .= "Action Title: {$action->title}\n";
        $prompt .= "Action Description: {$action->description}\n\n";

        // Add context resources
        if (!empty($contextResources)) {
            $prompt .= "Available Context Resources:\n";
            foreach ($contextResources as $resource) {
                $prompt .= "- {$resource['name']} ({$resource['resource_type']})\n";
                if (!empty($resource['description'])) {
                    $prompt .= "  Description: {$resource['description']}\n";
                }
                if (!empty($resource['data'])) {
                    $prompt .= "  Data: " . json_encode($resource['data'], JSON_PRETTY_PRINT) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // Add recent conversation context from original thread
        $recentMessages = $originalThread->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->reverse();

        if ($recentMessages->count() > 0) {
            $prompt .= "Recent Conversation Context:\n";
            foreach ($recentMessages as $message) {
                $role = ucfirst($message->role);
                $content = strlen($message->content) > 200 
                    ? substr($message->content, 0, 200) . '...' 
                    : $message->content;
                $prompt .= "$role: $content\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Execute the requested action based on the context provided\n";
        $prompt .= "2. Provide detailed steps of what you're doing\n";
        $prompt .= "3. Return results in the specified JSON format\n";
        $prompt .= "4. If you need additional information, request it in your response\n";
        $prompt .= "5. Be thorough and precise in your execution\n\n";

        $prompt .= "Execute the action now:";

        return $prompt;
    }

    /**
     * Get action-specific response schema
     */
    protected function getActionResponseSchema(string $actionName): SchemaDefinition
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'Summary message about the action execution'
                ],
                'steps_taken' => [
                    'type' => 'array',
                    'description' => 'Detailed steps that were executed',
                    'items' => ['type' => 'string']
                ],
                'result' => [
                    'type' => 'object',
                    'description' => 'The result of the action execution',
                    'additionalProperties' => true
                ],
                'success' => [
                    'type' => 'boolean',
                    'description' => 'Whether the action was successful'
                ],
                'next_steps' => [
                    'type' => 'array',
                    'description' => 'Suggested next steps for the user',
                    'items' => ['type' => 'string']
                ]
            ],
            'required' => ['message', 'success'],
            'additionalProperties' => false
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => team()->id,
            'name' => "Action Response Schema: $actionName"
        ], [
            'description' => "JSON schema for $actionName action responses",
            'schema' => $schema
        ]);
    }
}