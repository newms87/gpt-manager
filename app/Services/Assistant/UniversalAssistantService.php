<?php

namespace App\Services\Assistant;

use App\Models\Agent\AgentThread;
use App\Models\Assistant\AssistantAction;
use App\Repositories\Assistant\UniversalAssistantRepository;
use App\Repositories\ThreadRepository;
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

    public function handleChatMessage(
        string $message,
        string $context,
        array  $contextData = [],
        ?int   $threadId = null
    ): array
    {
        try {
            // Load the actual object if provided
            if (isset($contextData['objectId']) && isset($contextData['objectType'])) {
                $object = $this->loadContextObject($contextData['objectType'], $contextData['objectId']);
                if ($object) {
                    $contextData['object'] = $object;
                }
            }

            // Get or create thread
            $thread = $this->getOrCreateThread($threadId, $context);

            // Get context service
            $contextService = $this->getContextService($context);

            // Add user message to thread
            $userMessage = app(ThreadRepository::class)->addMessageToThread($thread, $message);

            // Get agent for this context
            $agent = app(ContextualAgentFactory::class)->getAgentForContext($context, $contextData);

            // Prepare context-aware system prompt
            $systemPrompt = $contextService->buildSystemPrompt($contextData);

            // Run agent with context-aware configuration
            $threadRun = app(ThreadRepository::class)->runAgentThread($thread, [
                'system_prompt' => $systemPrompt,
                'context'       => $context,
                'context_data'  => $contextData,
            ]);

            // Process any actions suggested by the agent (currently not implemented)
            $actions = [];

            return [
                'thread'               => $thread->fresh(['messages', 'runs']),
                'message'              => $threadRun->lastMessage,
                'actions'              => $actions,
                'context_capabilities' => $contextService->getCapabilities($contextData),
            ];

        } catch(\Exception $e) {
            Log::error('UniversalAssistant chat error', [
                'message' => $message,
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function executeAction(AssistantAction $action): array
    {
        try {
            $action->markInProgress();

            $contextService = $this->getContextService($action->context);
            $result         = $contextService->executeAction($action);

            if ($result['success']) {
                $action->markCompleted($result['data'] ?? null);
            } else {
                $action->markFailed($result['error'] ?? 'Action execution failed');
            }

            return $result;

        } catch(\Exception $e) {
            $action->markFailed($e->getMessage());

            Log::error('Action execution failed', [
                'action_id' => $action->id,
                'error'     => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function getContextCapabilities(string $context, array $contextData = []): array
    {
        $contextService = $this->getContextService($context);

        return $contextService->getCapabilities($contextData);
    }

    protected function getOrCreateThread(?int $threadId, string $context): AgentThread
    {
        if ($threadId) {
            $thread = AgentThread::findOrFail($threadId);

            // Verify user has access to this thread
            if ($thread->team_id !== team()->id) {
                throw new \Exception('Thread access denied');
            }

            return $thread;
        }

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
}
