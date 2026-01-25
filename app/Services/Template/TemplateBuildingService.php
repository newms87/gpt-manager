<?php

namespace App\Services\Template;

use App\Jobs\TemplateBuildingJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use Newms87\Danx\Traits\HasDebugLogging;
use Newms87\Danx\Models\Job\JobDispatch;

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

    protected const int DEFAULT_TIMEOUT = 600;

    protected function getModelForEffort(?string $effort): string
    {
        $effort = $effort ?? config('ai.template_building.default_effort', 'medium');

        return config("ai.template_building.efforts.{$effort}.model", 'gpt-5.2');
    }

    protected function getApiOptionsForEffort(?string $effort): array
    {
        $effort = $effort ?? config('ai.template_building.default_effort', 'medium');

        return config("ai.template_building.efforts.{$effort}.api_options", []);
    }

    protected function getBuildingTimeout(): int
    {
        return config('ai.template_building.timeout', 300);
    }

    /**
     * Dispatch a template build job.
     *
     * Handles queueing/merging when a build is already in progress.
     */
    public function dispatchBuild(TemplateDefinition $template, string $context, ?string $effort = null): void
    {
        $template->refresh();

        if ($template->building_job_dispatch_id) {
            // Check if the current job has timed out
            $currentJob = JobDispatch::find($template->building_job_dispatch_id);

            if ($currentJob?->isTimedOut()) {
                // Job timed out - clear it and start fresh
                static::logDebug('Previous build job timed out, starting new build', [
                    'template_id'       => $template->id,
                    'timed_out_job_id'  => $template->building_job_dispatch_id,
                ]);
                $template->building_job_dispatch_id = null;
                // Fall through to start new build below
            } else {
                // Still running - queue the request
                $existing                        = $template->pending_build_context ?? [];
                $existing[]                      = $context;
                $template->pending_build_context = $existing;
                $template->save();

                static::logDebug('Build queued - template already building', [
                    'template_id'           => $template->id,
                    'pending_context_count' => count($existing),
                ]);

                return;
            }
        }

        // Not building - start new build
        $job = new TemplateBuildingJob($template, $context, $effort);
        $job->dispatch();

        $jobDispatch = $job->getJobDispatch();

        $template->building_job_dispatch_id = $jobDispatch?->id;
        $template->save();

        // Attach job dispatch to template for Jobs tab tracking
        if ($jobDispatch) {
            $template->jobDispatches()->attach($jobDispatch->id);
            $template->updateRelationCounter('jobDispatches');
        }

        static::logDebug('Build job dispatched', [
            'template_id'     => $template->id,
            'job_dispatch_id' => $template->building_job_dispatch_id,
        ]);
    }

    /**
     * Build/update the template with the given context.
     *
     * This is the main entry point called by TemplateBuildingJob.
     */
    public function build(TemplateDefinition $template, string $context, ?string $effort = null): void
    {
        static::logDebug('Starting template build', [
            'template_id'    => $template->id,
            'context_length' => strlen($context),
            'effort'         => $effort,
        ]);

        try {
            // Create a fresh build thread with effort-based model (not linked to template's collaborationThreads)
            $thread = $this->createBuildThread($template, $effort);

            // Build a comprehensive prompt with instructions, current template content, and context
            $prompt = $this->buildBuilderPrompt($template, $context);

            // Add the prompt as a message
            app(ThreadRepository::class)->addMessageToThread($thread, $prompt);

            // Create a temporary in-memory SchemaDefinition with the JSON schema
            // This allows us to use proper structured JSON output instead of json_object type
            // Supports both full replacement and partial edits response types
            $tempSchemaDefinition = new SchemaDefinition([
                'name'   => 'builder-response',
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'response_type' => [
                            'type'        => 'string',
                            'enum'        => ['full', 'partial'],
                            'description' => 'full for complete replacement, partial for anchored edits',
                        ],
                        // For full response (complete replacement):
                        'html_content' => [
                            'type'        => ['string', 'null'],
                            'description' => 'The HTML template markup (for full response)',
                        ],
                        'css_content' => [
                            'type'        => ['string', 'null'],
                            'description' => 'CSS styles for the template (for full response)',
                        ],
                        // For partial response (anchored edits):
                        'html_edits' => [
                            'type'        => ['array', 'null'],
                            'description' => 'Anchored replacement edits for HTML (for partial response)',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'old_string' => ['type' => 'string', 'description' => 'Exact content to find (must match uniquely)'],
                                    'new_string' => ['type' => 'string', 'description' => 'Replacement content'],
                                ],
                                'required'             => ['old_string', 'new_string'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'css_edits' => [
                            'type'        => ['array', 'null'],
                            'description' => 'Anchored replacement edits for CSS (for partial response)',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'old_string' => ['type' => 'string', 'description' => 'Exact content to find (must match uniquely)'],
                                    'new_string' => ['type' => 'string', 'description' => 'Replacement content'],
                                ],
                                'required'             => ['old_string', 'new_string'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required'             => ['response_type'],
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

            // Check for cancellation before processing response
            if ($this->checkCancellation($template)) {
                return;
            }

            // Process the response
            if ($threadRun->isCompleted()) {
                $this->processBuilderResponse($threadRun, $template);
            }
        } finally {
            // Refresh to check if cancellation already cleared the job dispatch ID
            $template->refresh();

            // Only clear if not already cleared by cancellation
            if ($template->building_job_dispatch_id !== null) {
                $template->building_job_dispatch_id = null;
                $template->save();
            }

            // Check for pending builds
            $this->processPendingBuilds($template);
        }
    }

    /**
     * Check if the build was cancelled and handle cleanup if so.
     *
     * @return bool True if cancelled (should return early), false to continue
     */
    protected function checkCancellation(TemplateDefinition $template): bool
    {
        $template->refresh();
        $jobDispatch = $template->buildingJobDispatch;

        if (!$jobDispatch || $jobDispatch->status !== JobDispatch::STATUS_ABORTED) {
            return false;
        }

        static::logDebug('Build cancelled, aborting processing', [
            'template_id' => $template->id,
        ]);

        // Clear the build state (job is already marked as aborted)
        $template->building_job_dispatch_id = null;
        $template->save();

        return true;
    }

    /**
     * Create a fresh build thread for the template.
     *
     * Build threads are implementation details (temporary), not user-facing collaboration history.
     * We create a fresh thread for each build - no need to reuse or track them.
     * Importantly, we do NOT call forCollaboratable() so these threads don't pollute
     * the template's collaborationThreads relationship.
     */
    protected function createBuildThread(TemplateDefinition $template, ?string $effort = null): AgentThread
    {
        $agent = $this->findOrCreateBuilderAgent($effort);

        // Create a fresh thread without linking to template's collaborationThreads
        $thread = AgentThreadBuilderService::for($agent, $template->team_id)
            ->named("Build: {$template->name}")
            ->build();

        static::logDebug('Created build thread', [
            'thread_id'   => $thread->id,
            'template_id' => $template->id,
            'effort'      => $effort,
        ]);

        return $thread;
    }

    /**
     * Get the builder agent instructions from the markdown file.
     */
    protected function getBuilderAgentInstructions(): string
    {
        return file_get_contents(resource_path('prompts/templates/builder-agent.md'));
    }

    /**
     * Build the prompt for the builder agent with current template content and context.
     *
     * Agent instructions are passed via api_options['instructions'] on the Agent,
     * so this prompt only includes:
     * 1. The current template HTML/CSS content to modify
     * 2. What modifications are requested (the context)
     */
    protected function buildBuilderPrompt(TemplateDefinition $template, string $context): string
    {
        $prompt = "# Current Template State\n\n";

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
        $prompt .= 'Please provide your response in the JSON format specified in your instructions.';

        return $prompt;
    }

    /**
     * Process the builder agent's response.
     *
     * Routes to appropriate handler based on response_type (full or partial).
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

        $responseType = $responseData['response_type'] ?? 'full';

        static::logDebug('Response type detected', [
            'template_id'   => $template->id,
            'response_type' => $responseType,
        ]);

        if ($responseType === 'partial') {
            return $this->processPartialResponse($run, $responseData, $template);
        }

        return $this->processFullResponse($run, $responseData, $template);
    }

    /**
     * Process a full replacement response.
     *
     * Extracts html_content and css_content from the response,
     * updates the template (triggering auto-versioning), and syncs variables from HTML.
     */
    protected function processFullResponse(AgentThreadRun $run, array $responseData, TemplateDefinition $template): array
    {
        // Extract content from response
        $htmlContent = $responseData['html_content'] ?? null;
        $cssContent  = $responseData['css_content']  ?? null;

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
            static::logDebug('Template content updated (full)', [
                'template_id' => $template->id,
                'html_length' => strlen($htmlContent ?? ''),
                'css_length'  => strlen($cssContent ?? ''),
            ]);
        }

        // Sync variables (extracted from HTML)
        $variablesSynced = $this->syncVariables($template, []);

        static::logDebug('Full response processed', [
            'template_updated' => $updated,
            'variables_synced' => $variablesSynced,
        ]);

        return [
            'status'           => 'success',
            'variables_synced' => $variablesSynced,
        ];
    }

    /**
     * Process a partial edits response.
     *
     * Applies anchored replacement edits to HTML and CSS content.
     */
    protected function processPartialResponse(AgentThreadRun $run, array $responseData, TemplateDefinition $template): array
    {
        $htmlEdits = $responseData['html_edits'] ?? [];
        $cssEdits  = $responseData['css_edits']  ?? [];

        static::logDebug('Processing partial edits', [
            'template_id'     => $template->id,
            'html_edit_count' => count($htmlEdits),
            'css_edit_count'  => count($cssEdits),
        ]);

        $editService = app(TemplateEditService::class);

        $htmlResult = $editService->applyEdits($template->html_content ?? '', $htmlEdits);
        $cssResult  = $editService->applyEdits($template->css_content ?? '', $cssEdits);

        $appliedCount = $htmlResult['applied_count'] + $cssResult['applied_count'];

        // Save successfully-applied edits BEFORE checking for errors
        $updated = false;
        if ($htmlResult['content'] !== $template->html_content) {
            $template->html_content = $htmlResult['content'];
            $updated                = true;
        }
        if ($cssResult['content'] !== $template->css_content) {
            $template->css_content = $cssResult['content'];
            $updated               = true;
        }

        if ($updated) {
            $template->save();
            static::logDebug('Template content updated (partial)', [
                'template_id'        => $template->id,
                'html_edits_applied' => $htmlResult['applied_count'],
                'css_edits_applied'  => $cssResult['applied_count'],
            ]);
        }

        // Collect recoverable errors
        $htmlErrors = array_filter($htmlResult['errors'], fn($e) => $e['recoverable'] ?? false);
        $cssErrors  = array_filter($cssResult['errors'], fn($e) => $e['recoverable'] ?? false);

        if (!empty($htmlErrors) || !empty($cssErrors)) {
            return $this->handleRecoverableErrors($template, $htmlErrors, $cssErrors, $appliedCount);
        }

        // Sync variables (extracted from HTML)
        $variablesSynced = $this->syncVariables($template, []);

        static::logDebug('Partial response processed', [
            'applied_count'    => $appliedCount,
            'variables_synced' => $variablesSynced,
        ]);

        return [
            'status'           => 'success',
            'applied_count'    => $appliedCount,
            'variables_synced' => $variablesSynced,
        ];
    }

    /**
     * Handle recoverable edit errors.
     *
     * Logs errors and optionally dispatches a correction build if auto_correct is enabled.
     */
    protected function handleRecoverableErrors(
        TemplateDefinition $template,
        array $htmlErrors,
        array $cssErrors,
        int $appliedCount
    ): array {
        static::logDebug('Recoverable edit errors detected', [
            'template_id'      => $template->id,
            'html_error_count' => count($htmlErrors),
            'css_error_count'  => count($cssErrors),
            'applied_count'    => $appliedCount,
        ]);

        // Build error feedback for potential LLM correction
        $allErrors = array_merge(
            array_map(fn($e) => array_merge($e, ['content_type' => 'html']), $htmlErrors),
            array_map(fn($e) => array_merge($e, ['content_type' => 'css']), $cssErrors)
        );

        // Check if auto-correction is enabled
        if (config('ai.template_building.partial_edits.auto_correct', false)) {
            $correctionContext = $this->buildCorrectionContext($template, $allErrors);
            $this->dispatchBuild($template, $correctionContext, effort: 'low');

            return [
                'status'        => 'correcting',
                'errors'        => $allErrors,
                'applied_count' => $appliedCount,
            ];
        }

        // Otherwise, log and continue (successful edits already saved above)
        return [
            'status'        => 'partial_failure',
            'errors'        => $allErrors,
            'applied_count' => $appliedCount,
            'message'       => 'Some edits could not be applied. Applied edits have been saved.',
        ];
    }

    /**
     * Build correction context message for failed edits.
     *
     * Generates a message explaining which edits failed and why,
     * so the LLM can retry with corrected anchors.
     */
    protected function buildCorrectionContext(TemplateDefinition $template, array $errors): string
    {
        $context = "Some edits could not be applied. Please review and retry:\n\n";

        foreach ($errors as $error) {
            $type  = $error['content_type'];
            $index = $error['index'];

            if (($error['recovery_action'] ?? '') === 'expand_anchor') {
                $count   = $error['count'] ?? 'multiple';
                $context .= "{$type} Edit #{$index}: Found {$count} matches. ";
                $context .= "Provide a longer old_string with more surrounding context.\n";
            } elseif (($error['recovery_action'] ?? '') === 'rebase') {
                $context .= "{$type} Edit #{$index}: Anchor not found. Content may have changed.\n";
            } else {
                $context .= "{$type} Edit #{$index}: {$error['hint']}\n";
            }
        }

        $context .= "\n---\nCurrent template content has been updated. Please use response_type: 'full' or provide corrected partial edits.";

        return $context;
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
     *
     * Instructions are stored in api_options so they're included with every API call,
     * even when using previousResponseId optimization that skips old messages.
     */
    protected function findOrCreateBuilderAgent(?string $effort = null): Agent
    {
        $agent = Agent::where('name', self::BUILDER_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        $instructions = $this->getBuilderAgentInstructions();
        $model        = $this->getModelForEffort($effort);
        $apiOptions   = array_merge($this->getApiOptionsForEffort($effort), [
            'instructions'    => $instructions,
            'response_format' => [
                'type' => 'json_object',
            ],
        ]);

        if (!$agent) {
            $agent = Agent::create([
                'name'        => self::BUILDER_AGENT_NAME,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'Generates HTML templates from PDF/image sources through collaborative refinement.',
                'api_options' => $apiOptions,
            ]);

            static::logDebug('Created Template Builder agent', [
                'agent_id' => $agent->id,
                'model'    => $model,
                'effort'   => $effort,
            ]);
        } else {
            // Always update model and api_options based on current effort level
            $agent->update([
                'model'       => $model,
                'api_options' => $apiOptions,
            ]);

            static::logDebug('Updated Template Builder agent for effort', [
                'agent_id' => $agent->id,
                'model'    => $model,
                'effort'   => $effort,
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
}
