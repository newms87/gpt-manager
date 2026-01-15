<?php

namespace App\Services\Template;

use App\Events\AgentThreadUpdatedEvent;
use App\Jobs\TemplateCollaborationJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Schema\SchemaDefinition;
use App\Models\Template\TemplateDefinition;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
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

    protected const string GENERATION_AGENT_NAME = 'Template Builder';

    protected const int DEFAULT_TIMEOUT = 120;

    /** Key used in message data to identify system prompt messages */
    protected const string SYSTEM_PROMPT_DATA_KEY = 'is_system_prompt';

    /**
     * Get the model to use for conversation agent.
     */
    protected function getConversationModel(): string
    {
        return config('ai.template_collaboration.model');
    }

    /**
     * Get the model to use for generation agent (initial collaboration).
     * Uses template_building model since that's what the builder agent uses.
     */
    protected function getGenerationModel(): string
    {
        return config('ai.template_building.model');
    }

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
            $model = $this->getConversationModel();
            $agent = Agent::create([
                'name'        => self::CONVERSATION_AGENT_NAME,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'Fast conversational agent for template collaboration. Provides quick responses and determines when to trigger template builds.',
                'api_options' => [
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ],
            ]);

            static::logDebug('Created Conversation Agent', [
                'agent_id' => $agent->id,
                'model'    => $model,
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

    /**
     * Start a new collaboration thread for template generation.
     *
     * Creates an AgentThread linked to the template, uploads source files,
     * and sends the initial prompt to begin the collaboration.
     *
     * @param  TemplateDefinition  $template  The template to generate HTML for
     * @param  Collection<StoredFile>  $sourceFiles  PDF/images to analyze (optional)
     * @param  int  $teamId  The team ID for context
     * @param  string|null  $userPrompt  Optional user-provided prompt to start collaboration
     */
    public function startCollaboration(
        TemplateDefinition $template,
        Collection $sourceFiles,
        int $teamId,
        ?string $userPrompt = null
    ): AgentThread {
        static::logDebug('Starting template collaboration', [
            'template_id'   => $template->id,
            'template_name' => $template->name,
            'file_count'    => $sourceFiles->count(),
            'has_prompt'    => !empty($userPrompt),
            'team_id'       => $teamId,
        ]);

        $agent = $this->findOrCreateGenerationAgent();

        // Build simple user message (agent instructions are added via ensureInstructionsExist)
        $userMessage = $this->buildUserMessage($sourceFiles, $userPrompt);

        // Create the thread using builder with collaboratable relationship (no message yet)
        $thread = AgentThreadBuilderService::for($agent, $teamId)
            ->named("Template: {$template->name}")
            ->forCollaboratable($template)
            ->build();

        static::logDebug('Collaboration thread created', [
            'thread_id'   => $thread->id,
            'thread_name' => $thread->name,
            'agent_id'    => $agent->id,
        ]);

        // Add the user message IMMEDIATELY so frontend has it when thread is returned
        // Include ALL file IDs, not just the first one
        $fileIds = $sourceFiles->pluck('id')->toArray();
        app(ThreadRepository::class)->addMessageToThread($thread, $userMessage, $fileIds);

        // Dispatch job to process the message (skipAddMessage=true since we already added it)
        $firstFileId = $sourceFiles->first()?->id;
        $job         = new TemplateCollaborationJob($thread, $userMessage, $firstFileId, skipAddMessage: true);
        $job->dispatch();

        // Attach job dispatch to template for Jobs tab tracking
        $jobDispatch = $job->getJobDispatch();
        if ($jobDispatch) {
            $template->jobDispatches()->attach($jobDispatch->id);
            $template->updateRelationCounter('jobDispatches');
        }

        static::logDebug('Initial collaboration job dispatched', [
            'thread_id'       => $thread->id,
            'job_dispatch_id' => $jobDispatch?->id,
        ]);

        // Load messages so frontend has them immediately
        $thread->load('messages');

        return $thread;
    }

    /**
     * Build a simple user message for the initial collaboration message.
     *
     * This creates the actual user message that will be displayed in the chat.
     * Agent instructions are added separately via ensureInstructionsExist().
     *
     * @param  Collection<StoredFile>  $sourceFiles
     * @param  string|null  $userPrompt  Optional user-provided prompt
     */
    protected function buildUserMessage(Collection $sourceFiles, ?string $userPrompt = null): string
    {
        $hasFiles  = $sourceFiles->isNotEmpty();
        $hasPrompt = !empty($userPrompt);

        if ($hasPrompt && $hasFiles) {
            return "Please analyze the attached files and help me create a template. {$userPrompt}";
        }

        if ($hasPrompt) {
            return $userPrompt;
        }

        if ($hasFiles) {
            return 'Please analyze the attached files and help me create an HTML template based on them.';
        }

        return 'Please help me create an HTML template.';
    }

    /**
     * Find or create the generation agent for initial template collaboration.
     *
     * This agent is used when starting a new collaboration thread. It uses the
     * 'Template Builder' name to distinguish it from the conversation agent.
     */
    protected function findOrCreateGenerationAgent(): Agent
    {
        $agent = Agent::where('name', self::GENERATION_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $model = $this->getGenerationModel();
            $agent = Agent::create([
                'name'        => self::GENERATION_AGENT_NAME,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'Generates HTML templates from PDF/image sources through collaborative refinement.',
                'api_options' => [],
            ]);

            static::logDebug('Created Template Builder agent', [
                'agent_id' => $agent->id,
                'model'    => $model,
            ]);
        }

        return $agent;
    }
}
