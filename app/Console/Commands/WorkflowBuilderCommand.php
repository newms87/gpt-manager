<?php

namespace App\Console\Commands;

use App\Events\WorkflowBuilderChatUpdatedEvent;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Services\WorkflowBuilder\WorkflowBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Interactive chat-style command for building and modifying workflows through natural language conversation.
 * 
 * This command provides a conversational interface for users to:
 * - Create new workflows from natural language descriptions
 * - Continue existing workflow builder chat sessions  
 * - Modify existing workflows with additional requirements
 * - Monitor workflow build progress in real-time
 * - View results and get recommendations for next steps
 *
 * The command manages the full workflow builder lifecycle through three main phases:
 * 1. Requirements gathering - Natural language planning conversation
 * 2. Workflow building - Automated creation of workflow structure via specialized tasks
 * 3. Result evaluation - Analysis and user-friendly summary of what was built
 *
 * @see App\Services\WorkflowBuilder\WorkflowBuilderService
 * @see App\Models\WorkflowBuilder\WorkflowBuilderChat
 */
class WorkflowBuilderCommand extends Command
{
    /**
     * The name and signature of the console command.
     * 
     * Usage examples:
     * - php artisan workflow:build "Create a content analysis workflow"
     * - php artisan workflow:build --chat=123
     * - php artisan workflow:build "Add validation step" --workflow=456
     */
    protected $signature = 'workflow:build
                           {prompt? : Natural language description of what you want to build or modify}
                           {--chat= : Continue existing chat session by ID}
                           {--workflow= : Modify existing workflow by ID}
                           {--team= : Team UUID (optional, defaults to first team)}';

    /**
     * The console command description.
     */
    protected $description = 'Build and modify workflows through interactive AI-powered conversation';

    /**
     * Current team context for all operations
     */
    private ?Team $team = null;

    /**
     * Current workflow builder chat session
     */
    private ?WorkflowBuilderChat $chat = null;

    /**
     * Execute the console command.
     * 
     * Handles the main command flow by parsing arguments and routing to appropriate methods
     * for starting new chats, continuing existing sessions, or modifying workflows.
     * 
     * @return int Command exit code (0 = success, 1 = error)
     */
    public function handle(): int
    {
        try {
            // Initialize team context
            if (!$this->initializeTeamContext()) {
                return 1;
            }

            $prompt = $this->argument('prompt');
            $chatId = $this->option('chat');
            $workflowId = $this->option('workflow');

            // Route to appropriate handler based on arguments
            if ($chatId) {
                return $this->continueExistingChat($chatId);
            }

            if ($workflowId && $prompt) {
                return $this->modifyExistingWorkflow($workflowId, $prompt);
            }

            if ($prompt) {
                return $this->startNewWorkflowBuild($prompt);
            }

            // No valid arguments provided
            $this->error('You must provide either a prompt, --chat option, or both --workflow and prompt.');
            $this->line('');
            $this->line('Usage examples:');
            $this->line('  php artisan workflow:build "Create a content analysis workflow"');
            $this->line('  php artisan workflow:build --chat=123');
            $this->line('  php artisan workflow:build "Add validation step" --workflow=456');

            return 1;

        } catch (ValidationError $e) {
            $this->error("Validation Error: {$e->getMessage()}");
            return 1;
        } catch (\Exception $e) {
            $this->error("Unexpected error: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Initialize team context for the command session.
     * 
     * Sets up the team context based on the --team option or defaults to the first
     * available team. All workflow operations will be scoped to this team.
     * 
     * @return bool True if team context successfully established, false otherwise
     */
    private function initializeTeamContext(): bool
    {
        $teamUuid = $this->option('team');

        if ($teamUuid) {
            $this->team = Team::where('uuid', $teamUuid)->first();
            if (!$this->team) {
                $this->error("Team with UUID '{$teamUuid}' not found.");
                return false;
            }
        } else {
            $this->team = Team::first();
            if (!$this->team) {
                $this->error('No teams available. Please create a team first.');
                return false;
            }
        }

        $this->info("Using team: {$this->team->name} ({$this->team->uuid})");
        $this->line('');

        return true;
    }

    /**
     * Start a new workflow build process from a natural language prompt.
     * 
     * Creates a new WorkflowBuilderChat session and initiates the requirements
     * gathering phase where the AI will engage in conversation to understand
     * what the user wants to build.
     * 
     * @param string $prompt Natural language description of desired workflow
     * @return int Command exit code
     */
    private function startNewWorkflowBuild(string $prompt): int
    {
        $this->info('🚀 Starting new workflow build...');
        $this->line("Prompt: {$prompt}");
        $this->line('');

        try {
            $this->chat = app(WorkflowBuilderService::class)->startRequirementsGathering(
                $prompt,
                null, // No existing workflow
                null, // No existing chat
                $this->team
            );
        } catch (\Exception $e) {
            $this->error("Service error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }

        $this->info("✅ Created workflow builder chat session (ID: {$this->chat->id})");
        $this->line('');

        return $this->enterChatLoop();
    }

    /**
     * Continue an existing workflow builder chat session.
     * 
     * Loads the specified chat session and resumes from the current phase,
     * handling any necessary error recovery if the session was interrupted.
     * 
     * @param int $chatId The ID of the existing chat session
     * @return int Command exit code
     */
    private function continueExistingChat(int $chatId): int
    {
        $this->info("🔄 Continuing workflow builder chat session {$chatId}...");
        $this->line('');

        $this->chat = WorkflowBuilderChat::where('id', $chatId)
            ->where('team_id', $this->team->id)
            ->first();

        if (!$this->chat) {
            $this->error("Workflow builder chat {$chatId} not found or not accessible for your team.");
            return 1;
        }

        $this->displayChatStatus();

        return $this->enterChatLoop();
    }

    /**
     * Modify an existing workflow with new requirements.
     * 
     * Loads the specified workflow and starts a new chat session focused on
     * modifying the existing workflow structure based on the provided prompt.
     * 
     * @param int $workflowId The ID of the workflow to modify
     * @param string $prompt Description of desired modifications
     * @return int Command exit code
     */
    private function modifyExistingWorkflow(int $workflowId, string $prompt): int
    {
        $this->info("🔧 Modifying existing workflow {$workflowId}...");
        $this->line("Modifications: {$prompt}");
        $this->line('');

        $workflow = WorkflowDefinition::where('id', $workflowId)
            ->where('team_id', $this->team->id)
            ->first();

        if (!$workflow) {
            $this->error("Workflow {$workflowId} not found or not accessible for your team.");
            return 1;
        }

        $this->info("Target workflow: {$workflow->name}");
        $this->line('');

        $this->chat = app(WorkflowBuilderService::class)->startRequirementsGathering(
            $prompt,
            $workflowId,
            null, // New chat session
            $this->team
        );

        $this->info("✅ Created modification chat session (ID: {$this->chat->id})");
        $this->line('');

        return $this->enterChatLoop();
    }

    /**
     * Enter the main interactive chat loop.
     * 
     * Manages the conversational interface, handling different phases of the
     * workflow building process and user interactions. Continues until the
     * process is completed or the user exits.
     * 
     * @return int Command exit code
     */
    private function enterChatLoop(): int
    {
        while (true) {
            $this->chat->refresh();

            switch ($this->chat->status) {
                case 'requirements_gathering':
                    if (!$this->handleRequirementsGathering()) {
                        return 1;
                    }
                    break;

                case 'analyzing_plan':
                    if (!$this->handlePlanAnalysis()) {
                        return 1;
                    }
                    break;

                case 'building_workflow':
                    if (!$this->handleWorkflowBuilding()) {
                        return 1;
                    }
                    break;

                case 'evaluating_results':
                    if (!$this->handleResultEvaluation()) {
                        return 1;
                    }
                    break;

                case 'completed':
                    $this->handleCompletedSession();
                    return 0;

                case 'failed':
                    $this->handleFailedSession();
                    return 1;

                default:
                    $this->error("Unknown chat status: {$this->chat->status}");
                    return 1;
            }
        }
    }

    /**
     * Handle the requirements gathering phase.
     * 
     * Manages the conversational planning phase where the AI gathers requirements
     * and generates a workflow plan for user approval.
     * 
     * @return bool True to continue, false to exit
     */
    private function handleRequirementsGathering(): bool
    {
        $this->info('💭 Requirements Gathering Phase');
        $this->line('I\'m analyzing your requirements and will generate a workflow plan...');
        $this->line('');

        // In a real implementation, this would listen for updates from the service
        // For now, we simulate the process
        $this->line('⏳ Analyzing requirements...');

        try {
            $plan = app(WorkflowBuilderService::class)->generateWorkflowPlan(
                $this->chat,
                $this->argument('prompt') ?? 'Continue with the plan'
            );

            return $this->displayPlanAndAwaitApproval($plan);

        } catch (\Exception $e) {
            $this->error("Failed to generate plan: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Display the generated workflow plan and get user approval.
     * 
     * Shows the AI-generated plan in a user-friendly format and prompts for
     * approval, corrections, or additional requirements.
     * 
     * @param array $plan The generated workflow plan
     * @return bool True to continue, false to exit
     */
    private function displayPlanAndAwaitApproval(array $plan): bool
    {
        $this->line('');
        $this->info('📋 Generated Workflow Plan:');
        $this->line('===============================');

        // Display plan details (structure would depend on actual plan format)
        if (isset($plan['workflow_name'])) {
            $this->line("Workflow Name: {$plan['workflow_name']}");
        }

        if (isset($plan['description'])) {
            $this->line("Description: {$plan['description']}");
        }

        if (isset($plan['tasks']) && is_array($plan['tasks'])) {
            $this->line('');
            $this->line('Tasks:');
            foreach ($plan['tasks'] as $i => $task) {
                $taskName = $task['name'] ?? "Task " . ($i + 1);
                $this->line("  " . ($i + 1) . ". {$taskName}");
                if (isset($task['description'])) {
                    $this->line("     {$task['description']}");
                }
            }
        }

        $this->line('');
        $this->line('===============================');
        $this->line('');

        // Get user approval
        $response = $this->choice(
            'How would you like to proceed?',
            [
                'approve' => '✅ Approve and build this workflow',
                'modify' => '✏️  Request modifications',
                'cancel' => '❌ Cancel and exit'
            ],
            'approve'
        );

        switch ($response) {
            case 'approve':
                return $this->startWorkflowBuild();

            case 'modify':
                $modifications = $this->ask('What modifications would you like to make?');
                if (!$modifications) {
                    $this->error('No modifications provided.');
                    return false;
                }

                try {
                    app(WorkflowBuilderService::class)->generateWorkflowPlan($this->chat, $modifications);
                    return true; // Continue the loop
                } catch (\Exception $e) {
                    $this->error("Failed to apply modifications: {$e->getMessage()}");
                    return false;
                }

            case 'cancel':
                $this->info('Workflow build cancelled.');
                return false;

            default:
                return false;
        }
    }

    /**
     * Start the workflow building process.
     * 
     * Initiates the actual workflow construction phase where the AI will create
     * all the technical components (tasks, connections, prompts, etc.).
     * 
     * @return bool True to continue, false to exit
     */
    private function startWorkflowBuild(): bool
    {
        $this->info('🏗️  Starting workflow build...');
        $this->line('');

        try {
            app(WorkflowBuilderService::class)->startWorkflowBuild($this->chat);
            $this->info('✅ Workflow build initiated successfully');
            return true;

        } catch (\Exception $e) {
            $this->error("Failed to start workflow build: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Handle the plan analysis phase.
     * 
     * Manages the phase where the AI analyzes the approved plan and prepares
     * for the actual workflow building process.
     * 
     * @return bool True to continue, false to exit
     */
    private function handlePlanAnalysis(): bool
    {
        $this->info('🔍 Plan Analysis Phase');
        $this->line('Analyzing the approved plan and preparing build specifications...');
        $this->line('');

        // This phase is typically short, so we can just wait and refresh
        sleep(2);
        return true;
    }

    /**
     * Handle the workflow building phase.
     * 
     * Monitors the progress of the automated workflow construction process,
     * displaying real-time updates to the user.
     * 
     * @return bool True to continue, false to exit
     */
    private function handleWorkflowBuilding(): bool
    {
        $this->info('🏗️  Workflow Building Phase');
        $this->line('Building your workflow components...');
        $this->line('');

        return $this->monitorWorkflowProgress();
    }

    /**
     * Monitor the progress of the workflow building process.
     * 
     * Displays real-time progress updates while the workflow is being constructed
     * by the specialized task runners. Shows build status and any intermediate results.
     * 
     * @return bool True when build completes successfully, false on error
     */
    private function monitorWorkflowProgress(): bool
    {
        $progressChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $progressIndex = 0;
        $dots = 0;
        $lastStatus = '';

        $this->line('⏳ Building workflow components...');

        // Listen for workflow builder chat updates
        $listener = function(WorkflowBuilderChatUpdatedEvent $event) use (&$lastStatus) {
            if ($event->chat->id === $this->chat->id) {
                $newStatus = $event->updateType;
                if ($newStatus !== $lastStatus) {
                    $this->line('');
                    $this->info("📡 {$newStatus}");
                    $lastStatus = $newStatus;
                }
            }
        };

        Event::listen(WorkflowBuilderChatUpdatedEvent::class, $listener);

        try {
            // Monitor progress with timeout
            $startTime = time();
            $timeout = 300; // 5 minutes timeout

            while ($this->chat->status === 'building_workflow') {
                $char = $progressChars[$progressIndex % count($progressChars)];
                $dotString = str_repeat('.', $dots % 4);
                
                $this->output->write("\r{$char} Building{$dotString}");
                
                $progressIndex++;
                $dots++;
                
                usleep(500000); // 500ms
                
                $this->chat->refresh();

                // Check for timeout
                if (time() - $startTime > $timeout) {
                    $this->line('');
                    $this->warn('⚠️  Build is taking longer than expected. You can continue monitoring or cancel.');
                    
                    if (!$this->confirm('Continue waiting?', true)) {
                        $this->line('Build continues in background. Use --chat=' . $this->chat->id . ' to resume monitoring.');
                        return false;
                    }
                    
                    $startTime = time(); // Reset timeout
                }
            }

            $this->output->write("\r✅ Workflow build completed!            \n");
            $this->line('');

            return true;

        } finally {
            // Clean up event listener
            Event::forget(WorkflowBuilderChatUpdatedEvent::class);
        }
    }

    /**
     * Handle the result evaluation phase.
     * 
     * Manages the phase where the AI analyzes the built workflow and generates
     * a user-friendly summary of what was created.
     * 
     * @return bool True to continue, false to exit
     */
    private function handleResultEvaluation(): bool
    {
        $this->info('📊 Result Evaluation Phase');
        $this->line('Analyzing the completed workflow and preparing summary...');
        $this->line('');

        try {
            app(WorkflowBuilderService::class)->evaluateAndCommunicateResults($this->chat);
            $this->info('✅ Results evaluated successfully');
            return true;

        } catch (\Exception $e) {
            $this->error("Failed to evaluate results: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Handle a completed workflow builder session.
     * 
     * Displays the final results, explains what was built, and provides
     * recommendations for next steps or additional workflows.
     * 
     * @return void
     */
    private function handleCompletedSession(): void
    {
        $this->line('');
        $this->info('🎉 Workflow Build Completed Successfully!');
        $this->line('========================================');
        $this->line('');

        // Display results from the chat's artifacts
        $artifacts = $this->chat->getLatestArtifacts();
        
        if (!empty($artifacts)) {
            $this->displayBuildResults($artifacts);
        } else {
            $this->line('Your workflow has been successfully built and is ready to use.');
        }

        $this->line('');
        $this->info('Next steps:');
        $this->line('• Test your workflow in the web interface');
        $this->line('• Create workflow runs to process your data');
        $this->line('• Build additional workflows with: php artisan workflow:build');
        $this->line('');

        // Option to start another build
        if ($this->confirm('Would you like to build another workflow?')) {
            $prompt = $this->ask('What would you like to build?');
            if ($prompt) {
                $this->call('workflow:build', ['prompt' => $prompt]);
            }
        }
    }

    /**
     * Handle a failed workflow builder session.
     * 
     * Displays error information and provides options for recovery or retrying
     * the workflow build process.
     * 
     * @return void
     */
    private function handleFailedSession(): void
    {
        $this->line('');
        $this->error('❌ Workflow Build Failed');
        $this->line('=========================');
        $this->line('');

        // Get failure details from chat meta
        $buildState = $this->chat->getCurrentBuildState();
        
        if (isset($buildState['error'])) {
            $this->error("Error: {$buildState['error']}");
        }

        if (isset($buildState['failure_phase'])) {
            $this->error("Failed during: {$buildState['failure_phase']}");
        }

        $this->line('');
        $this->info('Recovery options:');
        $this->line('• Retry with: php artisan workflow:build --chat=' . $this->chat->id);
        $this->line('• Start over with a new prompt');
        $this->line('• Contact support if the issue persists');
        $this->line('');
    }

    /**
     * Display the current chat session status.
     * 
     * Shows relevant information about the current state of the workflow builder
     * chat session, including phase, progress, and any relevant metadata.
     * 
     * @return void
     */
    private function displayChatStatus(): void
    {
        $this->line("Status: {$this->chat->status}");
        
        if ($this->chat->workflow_definition_id) {
            $this->line("Target Workflow: {$this->chat->workflowDefinition->name}");
        }

        if ($this->chat->current_workflow_run_id) {
            $this->line("Current Build Run: {$this->chat->current_workflow_run_id}");
        }

        $this->line("Created: {$this->chat->created_at->diffForHumans()}");
        $this->line("Last Updated: {$this->chat->updated_at->diffForHumans()}");
        $this->line('');
    }

    /**
     * Display the final build results in a user-friendly format.
     * 
     * Takes the build artifacts from the completed workflow and presents them
     * in a clear, readable format explaining what was created.
     * 
     * @param array $artifacts The build artifacts to display
     * @return void
     */
    private function displayBuildResults(array $artifacts): void
    {
        $this->info('📦 Build Results:');
        $this->line('');

        if (isset($artifacts['workflow'])) {
            $workflow = $artifacts['workflow'];
            $workflowName = $workflow['name'] ?? 'Untitled Workflow';
            $this->line("✅ Created Workflow: {$workflowName}");
            
            if (isset($workflow['description'])) {
                $this->line("   Description: {$workflow['description']}");
            }
        }

        if (isset($artifacts['tasks']) && is_array($artifacts['tasks'])) {
            $this->line('');
            $this->line('✅ Created Tasks:');
            foreach ($artifacts['tasks'] as $i => $task) {
                $taskName = $task['name'] ?? "Task " . ($i + 1);
                $this->line("   " . ($i + 1) . ". {$taskName}");
                
                if (isset($task['runner_class'])) {
                    $this->line("      Runner: {$task['runner_class']}");
                }
            }
        }

        if (isset($artifacts['connections']) && is_array($artifacts['connections'])) {
            $this->line('');
            $this->line("✅ Created {$artifacts['connections']} workflow connections");
        }

        if (isset($artifacts['summary'])) {
            $this->line('');
            $this->line('📝 Summary:');
            $this->line("   {$artifacts['summary']}");
        }
    }
}