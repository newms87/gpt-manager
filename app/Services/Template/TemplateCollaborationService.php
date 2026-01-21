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

    /**
     * Get the model to use for conversation agent.
     */
    protected function getConversationModel(): string
    {
        return config('ai.template_collaboration.model');
    }

    /**
     * Get API options from config for conversation agent.
     */
    protected function getConversationApiOptions(): array
    {
        return config('ai.template_collaboration.api_options', []);
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
                            'type'        => ['string', 'null'],
                            'enum'        => ['plan', 'build', null],
                            'description' => 'Action to trigger: plan (complex changes), build (simple changes), or null (conversation only)',
                        ],
                        'effort' => [
                            'type'        => ['string', 'null'],
                            'enum'        => ['very_low', 'low', 'medium', 'high', 'very_high', null],
                            'description' => 'Effort level: very_low (trivial), low (simple), medium (standard), high (complex), very_high (highly complex)',
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
     * Process the conversation agent's response.
     *
     * Routes to planning or building based on the action type:
     * - "plan" -> TemplatePlanningService (for complex changes)
     * - "build" -> TemplateBuildingService (for simple changes)
     * - null -> no dispatch (conversation only)
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

        $action      = $responseData['action']  ?? null;
        $effort      = $responseData['effort']  ?? null;
        $userMessage = $responseData['message'] ?? null;

        // Extract the user-facing message and update the message content
        // This ensures the frontend receives clean text instead of raw JSON
        if ($userMessage) {
            $responseMessage->content = $userMessage;

            // Store action and effort in data for UI display and debugging
            $metadata = [];
            if ($action) {
                $metadata['action'] = $action;
            }
            if ($effort) {
                $metadata['effort'] = $effort;
            }
            if ($metadata) {
                $responseMessage->data = array_merge($responseMessage->data ?? [], $metadata);
            }

            $responseMessage->save();
        }

        if (!$thread->collaboratable instanceof TemplateDefinition) {
            return;
        }

        $template = $thread->collaboratable;

        // Get the last user message for context
        $lastUserMessage = $thread->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();

        $userContext = $lastUserMessage?->content ?? '';

        if ($action === 'plan') {
            static::logDebug('Dispatching template planning', [
                'template_id'    => $template->id,
                'context_length' => strlen($userContext),
                'effort'         => $effort,
            ]);

            app(TemplatePlanningService::class)->dispatchPlan(
                $template,
                $userContext,
                $thread,
                $effort
            );
        } elseif ($action === 'build') {
            static::logDebug('Dispatching template build', [
                'template_id'    => $template->id,
                'context_length' => strlen($userContext),
                'effort'         => $effort,
            ]);

            app(TemplateBuildingService::class)->dispatchBuild(
                $template,
                $userContext,
                $effort
            );
        }
        // If action is null, no dispatch needed - conversation only
    }

    /**
     * Find or create the conversation agent.
     *
     * Instructions are stored in api_options so they're included with every API call,
     * even when using previousResponseId optimization that skips old messages.
     */
    protected function findOrCreateConversationAgent(): Agent
    {
        $agent = Agent::where('name', self::CONVERSATION_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        $instructions = $this->getConversationAgentInstructions();

        if (!$agent) {
            $model = $this->getConversationModel();
            $agent = Agent::create([
                'name'        => self::CONVERSATION_AGENT_NAME,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'Fast conversational agent for template collaboration. Provides quick responses and determines when to trigger template builds.',
                'api_options' => array_merge($this->getConversationApiOptions(), [
                    'instructions'    => $instructions,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ]),
            ]);

            static::logDebug('Created Conversation Agent', [
                'agent_id' => $agent->id,
                'model'    => $model,
            ]);
        } else {
            // Update model and instructions if they differ from config
            $model               = $this->getConversationModel();
            $currentInstructions = $agent->api_options['instructions'] ?? null;
            $needsUpdate         = false;
            $updates             = [];

            if ($agent->model !== $model) {
                $updates['model'] = $model;
                $needsUpdate      = true;
            }

            if ($currentInstructions !== $instructions) {
                $updates['api_options'] = array_merge(
                    $this->getConversationApiOptions(),
                    $agent->api_options ?? [],
                    ['instructions' => $instructions]
                );
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $agent->update($updates);

                static::logDebug('Updated Conversation Agent', [
                    'agent_id'             => $agent->id,
                    'model_updated'        => isset($updates['model']),
                    'instructions_updated' => isset($updates['api_options']),
                ]);
            }
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

        // Build simple user message for display in the chat
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
     * Agent instructions are passed via api_options['instructions'] on the Agent.
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
