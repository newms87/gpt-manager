<?php

namespace App\Services\Template;

use App\Events\TemplateDefinitionUpdatedEvent;
use App\Jobs\TemplateBuildingJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Repositories\MessageRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;

/**
 * Service for building HTML templates using an LLM agent.
 *
 * This service handles the actual template generation/modification,
 * using a capable model (gpt-5) for quality output.
 */
class TemplateBuildingService
{
    use HasDebugLogging;

    protected const string BUILDER_AGENT_NAME = 'Template Builder';

    protected const string BUILDER_AGENT_MODEL = 'gpt-5';

    protected const int DEFAULT_TIMEOUT = 300;

    protected const string BUILDER_AGENT_INSTRUCTIONS = <<<'INSTRUCTIONS'
You are an expert HTML template builder. Your role is to analyze PDF documents and images
to create clean, semantic HTML templates with CSS styling.

## Your Responsibilities:
1. Analyze provided PDF/images to understand the document layout and structure
2. Generate clean, semantic HTML that matches the document's visual layout
3. Use `data-var-*` attributes for variable placeholders (e.g., `<span data-var-customer_name>Customer Name</span>`)
4. Create scoped CSS that accurately styles the template
5. Request screenshots when you need to see how your current template renders

## Variable Placeholders:
- Use `data-var-{variable_name}` attributes on elements that should be replaced with dynamic data
- The element's inner content should be a descriptive placeholder (e.g., "Customer Name", "Order Total")
- Variable names should be snake_case (e.g., customer_name, order_total, invoice_date)

## Response Format:
Always respond with a JSON object containing:
- `html_content`: The HTML template markup
- `css_content`: CSS styles for the template (use scoped class names)
- `variable_names`: Array of variable names extracted from data-var-* attributes
- `screenshot_request`: Object with screenshot request details, or false if not needed

## Screenshot Requests:
When you need to see how the current template renders in a browser, include a screenshot_request:
```json
{
  "screenshot_request": {
    "id": "unique-id",
    "status": "pending",
    "reason": "I need to see how the current layout renders"
  }
}
```

## Best Practices:
- Keep HTML semantic and accessible
- Use CSS classes, not inline styles
- Ensure variable names are descriptive and consistent
- Match the visual layout of the source document as closely as possible
INSTRUCTIONS;

    /**
     * Dispatch a template build job.
     *
     * Handles queueing/merging when a build is already in progress.
     */
    public function dispatchBuild(TemplateDefinition $template, string $context): void
    {
        $template->refresh();

        if ($template->building_job_dispatch_id) {
            // Already building - merge context into pending
            $existing                        = $template->pending_build_context ?? [];
            $existing[]                      = $context;
            $template->pending_build_context = $existing;
            $template->save();

            static::logDebug('Build queued - template already building', [
                'template_id'           => $template->id,
                'pending_context_count' => count($existing),
            ]);

            TemplateDefinitionUpdatedEvent::dispatch($template, 'updated');

            return;
        }

        // Not building - start new build
        $job = new TemplateBuildingJob($template, $context);
        $job->dispatch();

        $jobDispatch = $job->getJobDispatch();

        $template->building_job_dispatch_id = $jobDispatch?->id;
        $template->save();

        // Attach job dispatch to template for Jobs tab tracking
        if ($jobDispatch) {
            $template->jobDispatches()->attach($jobDispatch->id);
        }

        static::logDebug('Build job dispatched', [
            'template_id'      => $template->id,
            'job_dispatch_id'  => $template->building_job_dispatch_id,
        ]);

        TemplateDefinitionUpdatedEvent::dispatch($template, 'updated');
    }

    /**
     * Build/update the template with the given context.
     *
     * This is the main entry point called by TemplateBuildingJob.
     */
    public function build(TemplateDefinition $template, string $context): void
    {
        static::logDebug('Starting template build', [
            'template_id'    => $template->id,
            'context_length' => strlen($context),
        ]);

        try {
            // Get or create a build thread for this template
            $thread = $this->getOrCreateBuildThread($template);

            // Build a comprehensive prompt with instructions, current template content, and context
            $prompt = $this->buildBuilderPrompt($template, $context);

            // Add the prompt as a message
            app(ThreadRepository::class)->addMessageToThread($thread, $prompt);

            // Create a temporary in-memory SchemaDefinition with the JSON schema
            // This allows us to use proper structured JSON output instead of json_object type
            $tempSchemaDefinition = new SchemaDefinition([
                'name'   => 'builder-response',
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'html_content' => [
                            'type'        => 'string',
                            'description' => 'The HTML template markup',
                        ],
                        'css_content' => [
                            'type'        => 'string',
                            'description' => 'CSS styles for the template',
                        ],
                        'variable_names' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Variable names extracted from data-var-* attributes',
                        ],
                        'screenshot_request' => [
                            'type'        => ['object', 'boolean'],
                            'description' => 'Screenshot request object or false if not needed',
                            'properties'  => [
                                'id'     => ['type' => 'string'],
                                'status' => ['type' => 'string', 'enum' => ['pending']],
                                'reason' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'required'             => ['html_content', 'css_content', 'variable_names'],
                    'additionalProperties' => false,
                ],
            ]);

            // Run the builder agent
            $threadRun = app(AgentThreadService::class)
                ->withResponseFormat($tempSchemaDefinition)
                ->withTimeout(self::DEFAULT_TIMEOUT)
                ->run($thread);

            static::logDebug('Builder agent completed', [
                'thread_run_id' => $threadRun->id,
                'status'        => $threadRun->status,
            ]);

            // Process the response
            if ($threadRun->isCompleted()) {
                $this->processBuilderResponse($threadRun, $template);
            }
        } finally {
            // Clear the building job dispatch ID
            $template->building_job_dispatch_id = null;
            $template->save();

            // Notify frontend that build is complete
            TemplateDefinitionUpdatedEvent::dispatch($template, 'updated');

            // Check for pending builds
            $this->processPendingBuilds($template);
        }
    }

    /**
     * Get or create a build thread for the template.
     */
    protected function getOrCreateBuildThread(TemplateDefinition $template): AgentThread
    {
        $agent = $this->findOrCreateBuilderAgent();

        // Look for an existing build thread
        $thread = $template->collaborationThreads()
            ->where('agent_id', $agent->id)
            ->where('name', 'like', 'Build: %')
            ->orderByDesc('created_at')
            ->first();

        if (!$thread) {
            $thread = AgentThreadBuilderService::for($agent, $template->team_id)
                ->named("Build: {$template->name}")
                ->forCollaboratable($template)
                ->build();

            static::logDebug('Created build thread', [
                'thread_id'   => $thread->id,
                'template_id' => $template->id,
            ]);
        }

        return $thread;
    }

    /**
     * Build the prompt for the builder agent with instructions, current template content, and context.
     *
     * This ensures the builder agent knows:
     * 1. Its role and JSON response format (from BUILDER_AGENT_INSTRUCTIONS)
     * 2. The current template HTML/CSS content to modify
     * 3. What modifications are requested (the context)
     */
    protected function buildBuilderPrompt(TemplateDefinition $template, string $context): string
    {
        $prompt = "# Template Builder Instructions\n\n";
        $prompt .= self::BUILDER_AGENT_INSTRUCTIONS . "\n\n";

        $prompt .= "---\n\n";
        $prompt .= "# Current Template State\n\n";

        // Include current HTML content if it exists
        if ($template->html_content) {
            $prompt .= "## Current HTML Content\n";
            $prompt .= "```html\n";
            $prompt .= $template->html_content . "\n";
            $prompt .= "```\n\n";
        } else {
            $prompt .= "## Current HTML Content\n";
            $prompt .= "*No HTML content yet - this is a new template.*\n\n";
        }

        // Include current CSS content if it exists
        if ($template->css_content) {
            $prompt .= "## Current CSS Content\n";
            $prompt .= "```css\n";
            $prompt .= $template->css_content . "\n";
            $prompt .= "```\n\n";
        } else {
            $prompt .= "## Current CSS Content\n";
            $prompt .= "*No CSS content yet.*\n\n";
        }

        $prompt .= "---\n\n";
        $prompt .= "# Modification Request\n\n";
        $prompt .= $context . "\n\n";

        $prompt .= "---\n\n";
        $prompt .= 'Please provide your response in the JSON format specified in the instructions above.';

        return $prompt;
    }

    /**
     * Process the builder agent's response.
     *
     * Extracts html_content, css_content, and variable_names from the response,
     * updates the template (triggering auto-versioning), and syncs variables.
     */
    protected function processBuilderResponse(AgentThreadRun $run, TemplateDefinition $template): array
    {
        static::logDebug('Processing builder response', [
            'thread_run_id' => $run->id,
            'template_id'   => $template->id,
        ]);

        if (!$run->isCompleted()) {
            return [
                'status'             => 'error',
                'error'              => $run->error_message ?? 'Thread run did not complete successfully',
                'screenshot_request' => null,
                'variables_synced'   => 0,
            ];
        }

        $responseData = $run->lastMessage?->getJsonContent();

        if (!$responseData || !is_array($responseData)) {
            return [
                'status'             => 'error',
                'error'              => 'Invalid response format from LLM',
                'screenshot_request' => null,
                'variables_synced'   => 0,
            ];
        }

        // Extract content from response
        $htmlContent       = $responseData['html_content']       ?? null;
        $cssContent        = $responseData['css_content']        ?? null;
        $variableNames     = $responseData['variable_names']     ?? [];
        $screenshotRequest = $responseData['screenshot_request'] ?? null;

        // Update template if we have new content (triggers auto-versioning)
        $updated = false;
        if ($htmlContent !== null && $htmlContent !== $template->html_content) {
            $template->html_content = $htmlContent;
            $updated                = true;
        }

        if ($cssContent !== null && $cssContent !== $template->css_content) {
            $template->css_content = $cssContent;
            $updated               = true;
        }

        if ($updated) {
            $template->save();
            static::logDebug('Template content updated', [
                'template_id' => $template->id,
                'html_length' => strlen($htmlContent ?? ''),
                'css_length'  => strlen($cssContent ?? ''),
            ]);

            // Broadcast update
            TemplateDefinitionUpdatedEvent::dispatch($template, 'updated');
        }

        // Sync variables
        $variablesSynced = $this->syncVariables($template, $variableNames);

        // Handle screenshot request in message data
        if ($screenshotRequest && is_array($screenshotRequest) && $screenshotRequest !== false) {
            $this->storeScreenshotRequest($run->lastMessage, $screenshotRequest);
        }

        static::logDebug('Builder response processed', [
            'template_updated'       => $updated,
            'variables_synced'       => $variablesSynced,
            'has_screenshot_request' => $screenshotRequest !== null && $screenshotRequest !== false,
        ]);

        return [
            'status'             => 'success',
            'screenshot_request' => $screenshotRequest !== false ? $screenshotRequest : null,
            'variables_synced'   => $variablesSynced,
        ];
    }

    /**
     * Process any pending build contexts.
     */
    protected function processPendingBuilds(TemplateDefinition $template): void
    {
        $template->refresh();
        $pendingContexts = $template->pending_build_context;

        if (empty($pendingContexts)) {
            return;
        }

        // Clear pending contexts
        $template->pending_build_context = null;
        $template->save();

        // Merge all pending contexts into one
        $mergedContext = implode("\n\n--- Additional Request ---\n\n", $pendingContexts);

        static::logDebug('Processing pending build contexts', [
            'template_id'   => $template->id,
            'context_count' => count($pendingContexts),
        ]);

        // Dispatch a new build with merged context
        $this->dispatchBuild($template, $mergedContext);
    }

    /**
     * Find or create the builder agent.
     */
    protected function findOrCreateBuilderAgent(): Agent
    {
        $agent = Agent::where('name', self::BUILDER_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'name'        => self::BUILDER_AGENT_NAME,
                'team_id'     => null,
                'model'       => self::BUILDER_AGENT_MODEL,
                'description' => 'Generates HTML templates from PDF/image sources through collaborative refinement.',
                'api_options' => [
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ],
            ]);

            static::logDebug('Created Template Builder agent', [
                'agent_id' => $agent->id,
                'model'    => self::BUILDER_AGENT_MODEL,
            ]);
        }

        return $agent;
    }

    /**
     * Sync template variables based on extracted names from HTML.
     *
     * @param  array<string>  $variableNames
     * @return int Number of variables created
     */
    protected function syncVariables(TemplateDefinition $template, array $variableNames): int
    {
        if (empty($variableNames)) {
            // Also extract from HTML content directly as fallback
            $variableNames = $template->extractVariableNames();
        }

        if (empty($variableNames)) {
            return 0;
        }

        $existingNames = $template->templateVariables()->pluck('name')->toArray();
        $newVariables  = array_diff($variableNames, $existingNames);
        $created       = 0;

        foreach ($newVariables as $name) {
            TemplateVariable::create([
                'template_definition_id' => $template->id,
                'name'                   => $name,
                'description'            => $this->generateVariableDescription($name),
                'mapping_type'           => TemplateVariable::MAPPING_TYPE_AI,
                'multi_value_strategy'   => TemplateVariable::STRATEGY_FIRST,
                'multi_value_separator'  => ', ',
            ]);
            $created++;
        }

        static::logDebug('Variables synced', [
            'template_id'    => $template->id,
            'total_names'    => count($variableNames),
            'existing_count' => count($existingNames),
            'created_count'  => $created,
        ]);

        return $created;
    }

    /**
     * Generate a human-readable description from a variable name.
     */
    protected function generateVariableDescription(string $name): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Store screenshot request in message data.
     */
    protected function storeScreenshotRequest(AgentThreadMessage $message, array $screenshotRequest): void
    {
        $data                       = $message->data ?? [];
        $data['screenshot_request'] = [
            'id'           => $screenshotRequest['id'] ?? uniqid('screenshot_'),
            'status'       => 'pending',
            'reason'       => $screenshotRequest['reason'] ?? 'Screenshot requested by LLM',
            'requested_at' => now()->toIso8601String(),
        ];

        $message->data = $data;
        $message->save();

        static::logDebug('Screenshot request stored in message', [
            'message_id' => $message->id,
            'request_id' => $data['screenshot_request']['id'],
        ]);
    }

    /**
     * Handle screenshot response from frontend.
     *
     * Updates the message data with screenshot info and attaches the screenshot file.
     */
    public function handleScreenshotResponse(AgentThreadMessage $message, \Newms87\Danx\Models\Utilities\StoredFile $screenshot): void
    {
        static::logDebug('Handling screenshot response', [
            'message_id'    => $message->id,
            'screenshot_id' => $screenshot->id,
        ]);

        // Update message data with screenshot completion
        $data = $message->data ?? [];
        if (isset($data['screenshot_request'])) {
            $data['screenshot_request']['status']        = 'completed';
            $data['screenshot_request']['screenshot_id'] = $screenshot->id;
            $data['screenshot_request']['completed_at']  = now()->toIso8601String();
        }

        $message->data = $data;
        $message->save();

        // Attach screenshot to message
        app(MessageRepository::class)->saveFiles($message, [$screenshot->id]);

        static::logDebug('Screenshot response handled', [
            'message_id'    => $message->id,
            'screenshot_id' => $screenshot->id,
        ]);
    }
}
