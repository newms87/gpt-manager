<?php

namespace App\Services\Template;

use App\Events\AgentThreadUpdatedEvent;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Schema\SchemaDefinition;
use App\Models\Template\TemplateDefinition;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Service for handling conversational agent interactions in template collaboration.
 *
 * The conversational agent:
 * 1. ALWAYS returns a user-facing message
 * 2. Optionally returns an action (like "update_template")
 * 3. When action is "update_template", dispatches TemplateBuildingJob
 */
class TemplateCollaborationService
{
    use HasDebugLogging;

    protected const string CONVERSATION_AGENT_NAME = 'Template Conversation Agent';

    protected const string CONVERSATION_AGENT_MODEL = 'gpt-4o-mini';

    protected const int DEFAULT_TIMEOUT = 120;

    /** Key used in message data to identify system prompt messages */
    protected const string SYSTEM_PROMPT_DATA_KEY = 'is_system_prompt';

    /**
     * Process a user message in a template collaboration thread.
     *
     * Uses a fast conversational agent to quickly respond to the user,
     * and optionally dispatches a building job if template changes are needed.
     */
    public function processMessage(
        AgentThread $thread,
        string $message,
        ?StoredFile $attachment = null,
        bool $skipAddMessage = false
    ): void {
        static::logDebug('Processing collaboration message', [
            'thread_id'        => $thread->id,
            'message_length'   => strlen($message),
            'has_attachment'   => $attachment !== null,
            'skip_add_message' => $skipAddMessage,
        ]);

        // Ensure the conversation agent instructions exist (only added once per thread)
        $this->ensureInstructionsExist($thread);

        // Add the user message to the thread (unless already added by caller)
        if (!$skipAddMessage) {
            $fileIds = $attachment ? [$attachment->id] : [];
            app(ThreadRepository::class)->addMessageToThread($thread, $message, $fileIds);
        }

        // Run the conversational agent
        $agent = $this->findOrCreateConversationAgent();

        // Temporarily switch the thread to use the conversation agent
        $originalAgentId  = $thread->agent_id;
        $thread->agent_id = $agent->id;
        $thread->save();

        try {
            // Create a temporary in-memory SchemaDefinition with the JSON schema
            // This allows us to use proper structured JSON output instead of json_object type
            $tempSchemaDefinition = new SchemaDefinition([
                'name'   => 'conversation-response',
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'message' => [
                            'type'        => 'string',
                            'description' => 'User-facing conversational response',
                        ],
                        'action' => [
                            'type'        => ['object', 'null'],
                            'description' => 'Optional action to trigger template modification',
                            'properties'  => [
                                'type'    => ['type' => 'string', 'enum' => ['update_template']],
                                'context' => ['type' => 'string', 'description' => 'Instructions for the HTML builder agent'],
                            ],
                            'required' => ['type', 'context'],
                        ],
                    ],
                    'required'             => ['message'],
                    'additionalProperties' => false,
                ],
            ]);

            $threadRun = app(AgentThreadService::class)
                ->withResponseFormat($tempSchemaDefinition)
                ->withTimeout(self::DEFAULT_TIMEOUT)
                ->run($thread);

            static::logDebug('Conversation agent completed', [
                'thread_run_id' => $threadRun->id,
                'status'        => $threadRun->status,
            ]);

            // Process the response
            if ($threadRun->isCompleted()) {
                $this->processConversationResponse($thread, $threadRun->lastMessage);
            }
        } finally {
            // Restore the original agent
            $thread->agent_id = $originalAgentId;
            $thread->save();
        }

        // Broadcast thread update
        AgentThreadUpdatedEvent::dispatch($thread, 'updated');
    }

    /**
     * Ensure the conversation agent instructions exist in the thread.
     *
     * Instructions are only added once per thread and marked with is_system_prompt
     * in the data field so the frontend can filter them from display.
     */
    protected function ensureInstructionsExist(AgentThread $thread): void
    {
        // Check if a system prompt message already exists
        $existingSystemPrompt = $thread->messages()
            ->whereJsonContains('data->' . self::SYSTEM_PROMPT_DATA_KEY, true)
            ->exists();

        if ($existingSystemPrompt) {
            static::logDebug('System prompt already exists, skipping', ['thread_id' => $thread->id]);

            return;
        }

        // Create the system prompt message with the is_system_prompt flag
        $thread->messages()->create([
            'role'    => AgentThreadMessage::ROLE_USER,
            'content' => $this->buildConversationPrompt(),
            'data'    => [self::SYSTEM_PROMPT_DATA_KEY => true],
        ]);

        static::logDebug('Created system prompt message', ['thread_id' => $thread->id]);
    }

    /**
     * Process the conversation agent's response.
     *
     * If the response includes an action, dispatch the appropriate job.
     */
    protected function processConversationResponse(AgentThread $thread, ?AgentThreadMessage $responseMessage): void
    {
        if (!$responseMessage) {
            static::logDebug('No response message from conversation agent');

            return;
        }

        $responseData = $responseMessage->getJsonContent();

        if (!$responseData || !is_array($responseData)) {
            static::logDebug('Invalid or non-JSON response from conversation agent');

            return;
        }

        // Check for action
        $action = $responseData['action'] ?? null;

        // Extract the user-facing message and update the message content
        // This ensures the frontend receives clean text instead of raw JSON
        $userMessage = $responseData['message'] ?? null;
        if ($userMessage) {
            $responseMessage->content = $userMessage;

            // Store the action in data for debugging/tracking if present
            if ($action) {
                $responseMessage->data = array_merge($responseMessage->data ?? [], ['action' => $action]);
            }

            $responseMessage->save();
        }

        if ($action && is_array($action) && ($action['type'] ?? null) === 'update_template') {
            $context = $action['context'] ?? '';

            if ($context && $thread->collaboratable instanceof TemplateDefinition) {
                static::logDebug('Dispatching template build job', [
                    'template_id'    => $thread->collaboratable_id,
                    'context_length' => strlen($context),
                ]);

                app(TemplateBuildingService::class)->dispatchBuild(
                    $thread->collaboratable,
                    $context
                );
            }
        }
    }

    /**
     * Find or create the conversation agent.
     */
    protected function findOrCreateConversationAgent(): Agent
    {
        $agent = Agent::where('name', self::CONVERSATION_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'name'        => self::CONVERSATION_AGENT_NAME,
                'team_id'     => null,
                'model'       => self::CONVERSATION_AGENT_MODEL,
                'description' => 'Fast conversational agent for template collaboration. Provides quick responses and determines when to trigger template builds.',
                'api_options' => [
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ],
            ]);

            static::logDebug('Created Conversation Agent', [
                'agent_id' => $agent->id,
                'model'    => self::CONVERSATION_AGENT_MODEL,
            ]);
        }

        return $agent;
    }

    /**
     * Get the conversation agent instructions from the markdown file.
     */
    protected function getConversationAgentInstructions(): string
    {
        return file_get_contents(resource_path('prompts/templates/conversation-agent.md'));
    }

    /**
     * Build the conversation prompt with agent instructions.
     *
     * This is sent as a message before each user message to ensure the LLM
     * knows its role as the conversation agent, even when using previousResponseId
     * optimization that carries context from the HTML builder agent.
     */
    protected function buildConversationPrompt(): string
    {
        return "# Conversation Agent Instructions\n\n" . $this->getConversationAgentInstructions();
    }
}
