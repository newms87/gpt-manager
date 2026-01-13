<?php

namespace App\Services\Template;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Repositories\MessageRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Service for managing LLM collaboration to generate HTML templates from PDFs/images.
 *
 * This service handles the chat-based collaboration flow where users work with an LLM
 * to build HTML templates. The LLM analyzes provided PDF/images and generates HTML+CSS
 * with data-var-* attributes for variable placeholders.
 */
class HtmlTemplateGenerationService
{
    use HasDebugLogging;

    protected const string AGENT_NAME = 'Template Builder';

    protected const string AGENT_MODEL = 'gpt-5-mini';

    protected const int DEFAULT_TIMEOUT = 300;

    protected const string AGENT_INSTRUCTIONS = <<<'INSTRUCTIONS'
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

        $agent = $this->findOrCreateTemplateBuilderAgent();

        // Build initial prompt
        $initialPrompt = $this->buildInitialPrompt($template, $sourceFiles, $userPrompt);

        // Get file IDs for attachment (may be empty)
        $fileIds = $sourceFiles->pluck('id')->toArray();

        // Create the thread using builder with collaboratable relationship
        $thread = AgentThreadBuilderService::for($agent, $teamId)
            ->named("Template: {$template->name}")
            ->forCollaboratable($template)
            ->withMessage($initialPrompt, $fileIds)
            ->build();

        static::logDebug('Collaboration thread created', [
            'thread_id'   => $thread->id,
            'thread_name' => $thread->name,
            'agent_id'    => $agent->id,
        ]);

        return $thread;
    }

    /**
     * Send a refinement message to continue the collaboration.
     *
     * @param  AgentThread  $thread  The collaboration thread
     * @param  string  $message  The user's message
     * @param  StoredFile|null  $attachment  Optional attachment (e.g., screenshot)
     */
    public function sendMessage(
        AgentThread $thread,
        string $message,
        ?StoredFile $attachment = null
    ): AgentThreadRun {
        static::logDebug('Sending collaboration message', [
            'thread_id'      => $thread->id,
            'message_length' => strlen($message),
            'has_attachment' => $attachment !== null,
        ]);

        // Add the user message to the thread
        $fileIds = $attachment ? [$attachment->id] : [];
        app(ThreadRepository::class)->addMessageToThread($thread, $message, $fileIds);

        // Run the thread
        $threadRun = app(AgentThreadService::class)
            ->withTimeout(self::DEFAULT_TIMEOUT)
            ->run($thread);

        static::logDebug('Collaboration message sent, thread run completed', [
            'thread_run_id' => $threadRun->id,
            'status'        => $threadRun->status,
        ]);

        // Process the LLM response to extract template content
        $template = $thread->collaboratable;
        if ($template instanceof TemplateDefinition && $threadRun->isCompleted()) {
            $this->processLlmResponse($threadRun, $template);
        }

        return $threadRun;
    }

    /**
     * Process the LLM response and update the template.
     *
     * Extracts html_content, css_content, and variable_names from the response,
     * updates the template (triggering auto-versioning), and syncs variables.
     *
     * @return array{status: string, screenshot_request: array|null, variables_synced: int}
     */
    public function processLlmResponse(AgentThreadRun $run, TemplateDefinition $template): array
    {
        static::logDebug('Processing LLM response', [
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
                'template_id'    => $template->id,
                'html_length'    => strlen($htmlContent ?? ''),
                'css_length'     => strlen($cssContent ?? ''),
            ]);
        }

        // Sync variables
        $variablesSynced = $this->syncVariables($template, $variableNames);

        // Handle screenshot request in message data
        if ($screenshotRequest && is_array($screenshotRequest) && $screenshotRequest !== false) {
            $this->storeScreenshotRequest($run->lastMessage, $screenshotRequest);
        }

        static::logDebug('LLM response processed', [
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
     * Handle screenshot response from frontend.
     *
     * Updates the message data with screenshot info and attaches the screenshot file.
     */
    public function handleScreenshotResponse(AgentThreadMessage $message, StoredFile $screenshot): void
    {
        static::logDebug('Handling screenshot response', [
            'message_id'     => $message->id,
            'screenshot_id'  => $screenshot->id,
        ]);

        // Update message data with screenshot completion
        $data = $message->data ?? [];
        if (isset($data['screenshot_request'])) {
            $data['screenshot_request']['status']         = 'completed';
            $data['screenshot_request']['screenshot_id']  = $screenshot->id;
            $data['screenshot_request']['completed_at']   = now()->toIso8601String();
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

    /**
     * Find or create the Template Builder agent.
     */
    protected function findOrCreateTemplateBuilderAgent(): Agent
    {
        $agent = Agent::where('name', self::AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'name'        => self::AGENT_NAME,
                'team_id'     => null,
                'model'       => self::AGENT_MODEL,
                'description' => 'Generates HTML templates from PDF/image sources through collaborative refinement.',
                'api_options' => [],
            ]);

            static::logDebug('Created Template Builder agent', [
                'agent_id' => $agent->id,
                'model'    => self::AGENT_MODEL,
            ]);
        }

        return $agent;
    }

    /**
     * Build the initial prompt for starting collaboration.
     *
     * @param  Collection<StoredFile>  $sourceFiles
     * @param  string|null  $userPrompt  Optional user-provided prompt
     */
    protected function buildInitialPrompt(TemplateDefinition $template, Collection $sourceFiles, ?string $userPrompt = null): string
    {
        $prompt = "# Template Generation Request\n\n";
        $prompt .= "## Template Information\n";
        $prompt .= "- **Name:** {$template->name}\n";

        if ($template->description) {
            $prompt .= "- **Description:** {$template->description}\n";
        }

        $prompt .= "\n";

        // Add source files section if files are provided
        if ($sourceFiles->isNotEmpty()) {
            $fileDescriptions = $sourceFiles->map(fn(StoredFile $file) => "- {$file->filename} ({$file->mime})")->implode("\n");
            $prompt .= "## Source Files\n";
            $prompt .= "The following files have been provided for analysis:\n";
            $prompt .= "{$fileDescriptions}\n\n";
        }

        // Add user prompt if provided
        if (!empty($userPrompt)) {
            $prompt .= "## User Request\n";
            $prompt .= "{$userPrompt}\n\n";
        }

        $prompt .= "## Instructions\n";
        $prompt .= "{$this->getAgentInstructions()}\n\n";

        $prompt .= "## Your Task\n";

        if ($sourceFiles->isNotEmpty()) {
            $prompt .= "1. Analyze the provided PDF/images to understand the document structure and layout\n";
            $prompt .= "2. Generate an initial HTML template that matches the document layout\n";
            $prompt .= "3. Create CSS styles that accurately represent the visual design\n";
            $prompt .= "4. Identify variable placeholders and use data-var-* attributes\n";
        } else {
            $prompt .= "1. Based on the user's description, design an appropriate HTML template structure\n";
            $prompt .= "2. Generate clean, semantic HTML with appropriate layout\n";
            $prompt .= "3. Create CSS styles that match the described requirements\n";
            $prompt .= "4. Identify likely variable placeholders and use data-var-* attributes\n";
        }

        $prompt .= "\nPlease provide your response in the JSON format specified in the instructions.";

        return $prompt;
    }

    /**
     * Get the agent instructions for template building.
     */
    protected function getAgentInstructions(): string
    {
        return self::AGENT_INSTRUCTIONS;
    }

    /**
     * Sync template variables based on extracted names from HTML.
     *
     * Creates new variables for any new names, keeps existing variables intact.
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
}
