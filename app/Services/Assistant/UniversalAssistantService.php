<?php

namespace App\Services\Assistant;

use App\Models\Agent\AgentThread;
use App\Models\Assistant\AssistantAction;
use App\Models\Schema\SchemaDefinition;
use App\Repositories\Assistant\UniversalAssistantRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Assistant\Context\ContextServiceInterface;
use App\Services\Assistant\Context\GeneralChatContextService;
use App\Services\Assistant\Context\SchemaEditorContextService;
use Illuminate\Support\Facades\Log;

class UniversalAssistantService
{
    protected array $contextServices = [];

    public function __construct()
    {
        $this->registerContextServices();
    }

    protected function registerContextServices(): void
    {
        $this->contextServices = [
            AssistantAction::CONTEXT_SCHEMA_EDITOR => SchemaEditorContextService::class,
            AssistantAction::CONTEXT_GENERAL_CHAT  => GeneralChatContextService::class,
        ];
    }

    public function createChatThread(
        string $message,
        string $context,
        array  $contextData = []
    ): array
    {
        try {
            $schemaService = app(AgentResponseSchemaService::class);

            // Create new thread
            $thread = $this->createNewThread($context);

            // Get context service
            $contextService = $this->getContextService($context);



            // Add user message to thread
            $userMessage = app(ThreadRepository::class)->addMessageToThread($thread, $message);

            // Get agent for this context
            $agent = app(ContextualAgentFactory::class)->getAgentForContext($context, $contextData);

            // Run agent with JSON schema response format
            $responseSchema = SchemaDefinition::updateOrCreate([
                'team_id' => team()->id,
                'name'    => 'Assistant Response Schema',
            ], [
                'description' => 'JSON schema for assistant responses',
                'schema'      => $schemaService->getResponseSchema(),
            ]);

            // Add context-specific system message to thread
            $contextSystemPrompt = $contextService->buildSystemPrompt($contextData);
            $thread->messages()->create([
                'role' => 'system',
                'content' => $contextSystemPrompt,
            ]);

            $agentThreadService = app(AgentThreadService::class)
                ->withResponseFormat($responseSchema);

            $threadRun = $agentThreadService->run($thread);

            // Process the agent response and update message content
            $processed = $this->processAgentResponse($thread, $threadRun);
            $responseData = $processed['responseData'];
            $lastMessage = $processed['lastMessage'];


            // Handle action requests
            $actions = [];
            if ($responseData && isset($responseData['action'])) {
                $actions = $this->processActionRequest($thread, $responseData['action'], $context, $responseData);
            }

            return [
                'thread'  => $thread->fresh(['messages', 'runs']),
                'actions' => $actions,
            ];

        } catch(\Exception $e) {
            Log::error('UniversalAssistant thread creation error', [
                'message' => $message,
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function handleChatMessage(
        AgentThread $thread,
        string      $message,
        string      $context,
        array       $contextData = []
    ): array
    {
        try {
            $schemaService = app(AgentResponseSchemaService::class);

            // Verify user has access to this thread
            if ($thread->team_id !== team()->id) {
                throw new \Exception('Thread access denied');
            }

            // Get context service
            $contextService = $this->getContextService($context);


            // Add user message to thread
            $userMessage = app(ThreadRepository::class)->addMessageToThread($thread, $message);

            // Get agent for this context
            $agent = app(ContextualAgentFactory::class)->getAgentForContext($context, $contextData);

            // Run agent with JSON schema response format
            $responseSchema = SchemaDefinition::updateOrCreate([
                'team_id' => team()->id,
                'name'    => 'Assistant Response Schema',
            ], [
                'description' => 'JSON schema for assistant responses',
                'schema'      => $schemaService->getResponseSchema(),
            ]);

            // Add context-specific system message to thread
            $contextSystemPrompt = $contextService->buildSystemPrompt($contextData);
            $thread->messages()->create([
                'role' => 'system',
                'content' => $contextSystemPrompt,
            ]);

            $agentThreadService = app(AgentThreadService::class)
                ->withResponseFormat($responseSchema);

            $threadRun = $agentThreadService->run($thread);

            // Process the agent response and update message content
            $processed = $this->processAgentResponse($thread, $threadRun);
            $responseData = $processed['responseData'];
            $lastMessage = $processed['lastMessage'];


            // Handle action requests
            $actions = [];
            if ($responseData && isset($responseData['action'])) {
                $actions = $this->processActionRequest($thread, $responseData['action'], $context, $responseData);
            }

            return [
                'thread'  => $thread->fresh(['messages', 'runs']),
                'actions' => $actions,
            ];

        } catch(\Exception $e) {
            Log::error('UniversalAssistant chat error', [
                'message'   => $message,
                'context'   => $context,
                'thread_id' => $thread->id,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function executeAction(AssistantAction $action): array
    {
        // Use the specialized ActionExecutionService for o3 model execution
        return app(ActionExecutionService::class)->executeAction($action);
    }

    public function getContextCapabilities(string $context, array $contextData = []): array
    {
        $contextService = $this->getContextService($context);

        return $contextService->getCapabilities($contextData);
    }

    protected function createNewThread(string $context): AgentThread
    {
        // Create new thread for this context
        // We need an agent first, so get the default agent for this context
        $agent = app(ContextualAgentFactory::class)->getAgentForContext($context, []);
        $name  = ucfirst(str_replace('-', ' ', $context)) . ' Assistant';

        return app(ThreadRepository::class)->create($agent, $name);
    }

    protected function getContextService(string $context): ContextServiceInterface
    {
        $serviceClass = $this->contextServices[$context] ?? GeneralChatContextService::class;

        return app($serviceClass);
    }

    protected function processAgentActions(AgentThread $thread, string $context, array $result): array
    {
        $actions = [];

        // Look for action suggestions in the agent response
        if (isset($result['suggested_actions'])) {
            foreach($result['suggested_actions'] as $actionData) {
                $action = app(UniversalAssistantRepository::class)->create([
                    'agent_thread_id' => $thread->id,
                    'context'         => $context,
                    'action_type'     => $actionData['type'],
                    'target_type'     => $actionData['target_type'],
                    'target_id'       => $actionData['target_id'] ?? null,
                    'title'           => $actionData['title'],
                    'description'     => $actionData['description'] ?? null,
                    'payload'         => $actionData['payload'] ?? null,
                    'preview_data'    => $actionData['preview_data'] ?? null,
                ]);

                $actions[] = $action;
            }
        }

        return $actions;
    }

    protected function loadContextObject(string $objectType, $objectId)
    {
        // Map frontend object types to model classes
        $modelMap = [
            'SchemaDefinition'   => \App\Models\Schema\SchemaDefinition::class,
            'WorkflowDefinition' => \App\Models\Workflow\WorkflowDefinition::class,
            'Agent'              => \App\Models\Agent\Agent::class,
            'TaskDefinition'     => \App\Models\Tasks\TaskDefinition::class,
        ];

        $modelClass = $modelMap[$objectType] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            Log::warning("Unknown object type: {$objectType}");

            return null;
        }

        try {
            $object = $modelClass::find($objectId);

            // Ensure user has access to this object
            if ($object && method_exists($object, 'team_id') && $object->team_id !== team()->id) {
                return null;
            }

            return $object;
        } catch(\Exception $e) {
            Log::error("Failed to load context object", [
                'type'  => $objectType,
                'id'    => $objectId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }



    /**
     * Process action requests from the agent
     */
    protected function processActionRequest(AgentThread $thread, string $actionName, string $context, array $responseData = []): array
    {
        // Extract the action content (schema, etc.) from the response
        $actionContent = $responseData['message'] ?? null;
        
        // Create preview data based on action type
        $previewData = null;
        if ($actionName === 'create_schema' && $actionContent) {
            $previewData = [
                'modification_type' => 'create_schema',
                'schema_content' => $actionContent,
                'target_path' => 'New Schema',
                'reason' => 'AI agent created a new JSON schema based on your requirements'
            ];
        }

        // Create an AssistantAction for the requested action
        $action = app(UniversalAssistantRepository::class)->create([
            'agent_thread_id' => $thread->id,
            'context'         => $context,
            'action_type'     => $actionName,
            'target_type'     => $actionName === 'create_schema' ? 'schema' : 'multiple',
            'title'           => "Create: " . ucwords(str_replace('_', ' ', $actionName)),
            'description'     => $this->getActionDescription($actionName),
            'payload'         => [
                'action_name'       => $actionName,
                'action_content'    => $actionContent,
                'thread_context'    => $context,
                'response_data'     => $responseData,
            ],
            'preview_data'    => $previewData,
            'status'          => 'pending',
        ]);

        return [$action];
    }

    protected function getActionDescription(string $actionName): string
    {
        $descriptions = [
            'create_schema' => 'Create a new JSON schema based on the AI-generated definition',
            'modify_schema' => 'Modify an existing schema with AI-suggested changes',
            'create_workflow' => 'Create a new workflow definition',
            'modify_workflow' => 'Modify an existing workflow',
        ];

        return $descriptions[$actionName] ?? "Execute action: $actionName";
    }

    /**
     * Process the agent's JSON response and update message content
     */
    protected function processAgentResponse(AgentThread $thread, $threadRun): array
    {
        $lastMessage = $threadRun->lastMessage;
        if (!$lastMessage) {
            return ['responseData' => null, 'lastMessage' => null];
        }

        // Parse the JSON response using the proper AgentThreadMessage method
        $responseData = $lastMessage->getJsonContent();
        
        if ($responseData && isset($responseData['message'])) {
            // Update the message to show just the user-friendly content
            $lastMessage->update([
                'content' => $responseData['message'],
                'data' => ['original_response' => $responseData]
            ]);
        }
        
        return ['responseData' => $responseData, 'lastMessage' => $lastMessage];
    }
}
