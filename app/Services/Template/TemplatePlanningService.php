<?php

namespace App\Services\Template;

use App\Jobs\TemplatePlanningJob;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Template\TemplateDefinition;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Models\Job\JobDispatch;

/**
 * Service for planning complex template modifications.
 *
 * The planning agent analyzes user requests and chat context to create
 * detailed implementation plans for the builder agent. This provides
 * better results for complex modifications by adding a thinking/planning step.
 */
class TemplatePlanningService
{
    use HasDebugLogging;

    protected const string PLANNING_AGENT_NAME = 'Template Planning Agent';

    protected function getModelForEffort(?string $effort): string
    {
        $effort = $effort ?? config('ai.template_planning.default_effort', 'low');

        return config("ai.template_planning.efforts.{$effort}.model", 'gpt-5-nano');
    }

    protected function getApiOptionsForEffort(?string $effort): array
    {
        $effort = $effort ?? config('ai.template_planning.default_effort', 'low');

        return config("ai.template_planning.efforts.{$effort}.api_options", []);
    }

    protected function getPlanningTimeout(): int
    {
        return config('ai.template_planning.timeout', 300);
    }

    /**
     * Dispatch a planning job for complex template modifications.
     */
    public function dispatchPlan(
        TemplateDefinition $template,
        string $userMessage,
        AgentThread $thread,
        ?string $effort = null
    ): void {
        $template->refresh();

        // Use same building_job_dispatch_id for combined UI tracking
        if ($template->building_job_dispatch_id) {
            // Check if the current job has timed out
            $currentJob = JobDispatch::find($template->building_job_dispatch_id);

            if ($currentJob?->isTimedOut()) {
                // Job timed out - clear it and start fresh
                static::logDebug('Previous planning/build job timed out, starting new plan', [
                    'template_id'       => $template->id,
                    'timed_out_job_id'  => $template->building_job_dispatch_id,
                ]);
                $template->building_job_dispatch_id = null;
                // Fall through to start new planning below
            } else {
                // Still running - queue the request
                $existing                        = $template->pending_build_context ?? [];
                $existing[]                      = $userMessage;
                $template->pending_build_context = $existing;
                $template->save();

                static::logDebug('Plan queued - template already building/planning', [
                    'template_id'   => $template->id,
                    'pending_count' => count($existing),
                ]);

                return;
            }
        }

        $job = new TemplatePlanningJob($template, $userMessage, $thread, $effort);
        $job->dispatch();

        $jobDispatch = $job->getJobDispatch();

        $template->building_job_dispatch_id = $jobDispatch?->id;
        $template->save();

        if ($jobDispatch) {
            $template->jobDispatches()->attach($jobDispatch->id);
            $template->updateRelationCounter('jobDispatches');
        }

        static::logDebug('Planning job dispatched', [
            'template_id'     => $template->id,
            'job_dispatch_id' => $template->building_job_dispatch_id,
        ]);
    }

    /**
     * Execute planning and then dispatch the build.
     */
    public function plan(
        TemplateDefinition $template,
        string $userMessage,
        AgentThread $thread,
        ?string $effort = null
    ): void {
        static::logDebug('Starting template planning', [
            'template_id'    => $template->id,
            'message_length' => strlen($userMessage),
            'effort'         => $effort,
        ]);

        try {
            // Create planning thread with effort-based model
            $planThread = $this->createPlanningThread($template, $effort);

            // Build comprehensive prompt
            $prompt = $this->buildPlanningPrompt($template, $userMessage, $thread);

            // Add prompt as message
            app(ThreadRepository::class)->addMessageToThread($planThread, $prompt);

            // Run planning agent (plain text response - no schema needed)
            $threadRun = app(AgentThreadService::class)
                ->withTimeout($this->getPlanningTimeout())
                ->run($planThread);

            static::logDebug('Planning agent completed', [
                'thread_run_id' => $threadRun->id,
                'status'        => $threadRun->status,
            ]);

            // Check for cancellation before dispatching build
            if ($this->checkCancellation($template)) {
                return;
            }

            if ($threadRun->isCompleted()) {
                $plan = $threadRun->lastMessage?->content ?? '';

                if ($plan) {
                    static::logDebug('Plan generated, dispatching build', [
                        'template_id' => $template->id,
                        'plan_length' => strlen($plan),
                    ]);

                    // Clear the building_job_dispatch_id before dispatching build
                    // (build service will set its own)
                    $template->building_job_dispatch_id = null;
                    $template->save();

                    // Dispatch build with the plan
                    app(TemplateBuildingService::class)->dispatchBuild($template, $plan);
                } else {
                    static::logDebug('No plan generated from planning agent');
                    $template->building_job_dispatch_id = null;
                    $template->save();
                }
            } else {
                $template->building_job_dispatch_id = null;
                $template->save();
            }
        } catch (\Throwable $e) {
            static::logDebug('Planning failed', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);

            $template->building_job_dispatch_id = null;
            $template->save();

            throw $e;
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

        static::logDebug('Planning cancelled, aborting', [
            'template_id' => $template->id,
        ]);

        // Clear the build state (job is already marked as aborted)
        $template->building_job_dispatch_id = null;
        $template->save();

        return true;
    }

    protected function createPlanningThread(TemplateDefinition $template, ?string $effort = null): AgentThread
    {
        $agent = $this->findOrCreatePlanningAgent($effort);

        $thread = AgentThreadBuilderService::for($agent, $template->team_id)
            ->named("Plan: {$template->name}")
            ->build();

        static::logDebug('Created planning thread', [
            'thread_id'   => $thread->id,
            'template_id' => $template->id,
            'effort'      => $effort,
        ]);

        return $thread;
    }

    protected function buildPlanningPrompt(
        TemplateDefinition $template,
        string $userMessage,
        AgentThread $thread
    ): string {
        $prompt = "# Template Planning Request\n\n";

        // Include chat history for context
        $prompt .= "## Chat History\n\n";
        $prompt .= $this->getThreadHistory($thread) . "\n\n";
        $prompt .= "---\n\n";

        // Current template state
        $prompt .= "# Current Template State\n\n";

        if ($template->html_content) {
            $prompt .= "## HTML Content\n```html\n" . $template->html_content . "\n```\n\n";
        } else {
            $prompt .= "## HTML Content\n*No HTML content yet - new template.*\n\n";
        }

        if ($template->css_content) {
            $prompt .= "## CSS Content\n```css\n" . $template->css_content . "\n```\n\n";
        } else {
            $prompt .= "## CSS Content\n*No CSS content yet.*\n\n";
        }

        $prompt .= "---\n\n";
        $prompt .= "# User Request\n\n";
        $prompt .= $userMessage . "\n\n";
        $prompt .= "---\n\n";
        $prompt .= 'Please analyze this request and provide your detailed implementation plan.';

        return $prompt;
    }

    protected function getThreadHistory(AgentThread $thread): string
    {
        $messages = $thread->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        if ($messages->isEmpty()) {
            return '*No previous messages.*';
        }

        $history = [];
        foreach ($messages as $message) {
            $role    = ucfirst($message->role);
            $content = $message->content;

            // Skip messages that look like agent instructions
            if (str_contains($content, '# Conversation Agent Instructions') ||
                str_contains($content, '## Your Responsibilities')          ||
                str_contains($content, '## Response Format')                ||
                str_starts_with($content, 'You are a')) {
                continue;
            }

            // Truncate very long messages
            if (strlen($content) > 1000) {
                $content = substr($content, 0, 1000) . '... [truncated]';
            }

            $history[] = "**{$role}**: {$content}";
        }

        if (empty($history)) {
            return '*No previous messages.*';
        }

        return implode("\n\n", $history);
    }

    protected function findOrCreatePlanningAgent(?string $effort = null): Agent
    {
        $agent = Agent::where('name', self::PLANNING_AGENT_NAME)
            ->whereNull('team_id')
            ->first();

        $instructions = $this->getPlanningAgentInstructions();
        $model        = $this->getModelForEffort($effort);
        $apiOptions   = array_merge($this->getApiOptionsForEffort($effort), [
            'instructions' => $instructions,
        ]);

        if (!$agent) {
            $agent = Agent::create([
                'name'        => self::PLANNING_AGENT_NAME,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'Plans complex template modifications before passing to builder agent.',
                'api_options' => $apiOptions,
            ]);

            static::logDebug('Created Planning Agent', [
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

            static::logDebug('Updated Planning Agent for effort', [
                'agent_id' => $agent->id,
                'model'    => $model,
                'effort'   => $effort,
            ]);
        }

        return $agent;
    }

    protected function getPlanningAgentInstructions(): string
    {
        return file_get_contents(resource_path('prompts/templates/planning-agent.md'));
    }
}
