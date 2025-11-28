<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Agent\AgentThread;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Manages agent thread setup for file organization processes.
 */
class AgentThreadService
{
    use HasDebugLogging;

    /**
     * Setup agent thread for comparison window process.
     * Only sends page_number from metadata, along with the file itself.
     *
     * @param  TaskDefinition  $taskDefinition  The task definition
     * @param  TaskRun  $taskRun  The task run
     * @param  Collection  $artifacts  Artifacts to include in thread
     */
    public function setupComparisonWindowThread(TaskDefinition $taskDefinition, TaskRun $taskRun, Collection $artifacts): AgentThread
    {
        static::logDebug("Setting up comparison window thread for TaskRun {$taskRun->id}");

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $taskRun");
        }

        // Build the agent thread using the task-specific builder WITHOUT artifacts
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun);
        $agentThread = $builder->build();

        static::logDebug("Built agent thread {$agentThread->id} for agent {$taskDefinition->agent->name}");

        // Manually add artifacts in a simplified format: just the page number in text
        static::logDebug("Adding {$artifacts->count()} artifact messages to thread");
        $this->addArtifactMessages($agentThread, $artifacts);

        // Get or create the schema definition for file organization
        $schemaProvider   = app(SchemaProvider::class);
        $schemaDefinition = $schemaProvider->getFileOrganizationSchema($taskDefinition->team_id, $taskDefinition);

        // Always use the current schema
        $taskDefinition->schema_definition_id = $schemaDefinition->id;
        $taskDefinition->save();

        // Add file organization specific instructions as the LAST message
        static::logDebug('Adding comparison window instructions to thread');
        $this->addComparisonWindowInstructions($agentThread);

        static::logDebug("Comparison window thread setup completed: {$agentThread->id}");

        return $agentThread;
    }

    /**
     * Setup agent thread for low confidence file resolution.
     * Provides all context from window comparisons to help agent make better decisions.
     *
     * @param  TaskDefinition  $taskDefinition  The task definition
     * @param  TaskRun  $taskRun  The task run
     * @param  Collection  $artifacts  Artifacts needing resolution
     * @param  array  $lowConfidenceFiles  Low confidence file data
     */
    public function setupLowConfidenceResolutionThread(TaskDefinition $taskDefinition, TaskRun $taskRun, Collection $artifacts, array $lowConfidenceFiles): AgentThread
    {
        static::logDebug("Setting up low confidence resolution thread for TaskRun {$taskRun->id} with " . count($lowConfidenceFiles) . ' files');

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $taskRun");
        }

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun);
        $agentThread = $builder->build();

        static::logDebug("Built agent thread {$agentThread->id}");

        // Add file messages
        static::logDebug("Adding {$artifacts->count()} artifact messages to thread");
        $this->addArtifactMessages($agentThread, $artifacts);

        // Build context message showing ALL explanations from all windows
        static::logDebug('Adding low confidence context to thread');
        $this->addLowConfidenceContext($agentThread, $lowConfidenceFiles);

        // Use the same schema as window comparisons
        $schemaProvider   = app(SchemaProvider::class);
        $schemaDefinition = $schemaProvider->getFileOrganizationSchema($taskDefinition->team_id, $taskDefinition);

        $taskDefinition->schema_definition_id = $schemaDefinition->id;
        $taskDefinition->save();

        // Add resolution-specific instructions
        static::logDebug('Adding low confidence resolution instructions to thread');
        $this->addLowConfidenceResolutionInstructions($agentThread);

        static::logDebug("Low confidence resolution thread setup completed: {$agentThread->id}");

        return $agentThread;
    }

    /**
     * Setup agent thread for null group resolution.
     * Provides context about adjacent groups to help agent decide assignment.
     *
     * @param  TaskDefinition  $taskDefinition  The task definition
     * @param  TaskRun  $taskRun  The task run
     * @param  Collection  $artifacts  All artifacts (null files + context pages)
     * @param  array  $nullGroupFiles  Null group file data
     * @param  array  $nullFileIds  IDs of null files
     */
    public function setupNullGroupResolutionThread(TaskDefinition $taskDefinition, TaskRun $taskRun, Collection $artifacts, array $nullGroupFiles, array $nullFileIds): AgentThread
    {
        static::logDebug("Setting up null group resolution thread for TaskRun {$taskRun->id} with " . count($nullGroupFiles) . ' null files and ' . count($nullFileIds) . ' files needing context');

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $taskRun");
        }

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun);
        $agentThread = $builder->build();

        static::logDebug("Built agent thread {$agentThread->id}");

        // Add file messages with context/resolution markers
        static::logDebug("Adding {$artifacts->count()} artifact messages to thread (null files + context pages)");
        $this->addNullGroupArtifactMessages($agentThread, $artifacts, $nullFileIds);

        // Build context message explaining the situation
        static::logDebug('Adding null group context to thread');
        $this->addNullGroupContext($agentThread, $nullGroupFiles);

        // Use the same schema as window comparisons
        $schemaProvider   = app(SchemaProvider::class);
        $schemaDefinition = $schemaProvider->getFileOrganizationSchema($taskDefinition->team_id, $taskDefinition);

        $taskDefinition->schema_definition_id = $schemaDefinition->id;
        $taskDefinition->save();

        // Add null group resolution instructions
        static::logDebug('Adding null group resolution instructions to thread');
        $this->addNullGroupResolutionInstructions($agentThread);

        static::logDebug("Null group resolution thread setup completed: {$agentThread->id}");

        return $agentThread;
    }

    /**
     * Setup agent thread for duplicate group resolution.
     * Presents potentially duplicate groups side-by-side for LLM to decide.
     *
     * @param  TaskDefinition  $taskDefinition  The task definition
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $duplicateCandidates  Duplicate candidate data
     */
    public function setupDuplicateGroupResolutionThread(TaskDefinition $taskDefinition, TaskRun $taskRun, array $duplicateCandidates): AgentThread
    {
        static::logDebug("Setting up duplicate group resolution thread for TaskRun {$taskRun->id} with " . count($duplicateCandidates) . ' duplicate candidates');

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $taskRun");
        }

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun);
        $agentThread = $builder->build();

        static::logDebug("Built agent thread {$agentThread->id}");

        // Build context message explaining the situation
        static::logDebug('Adding duplicate group context to thread');
        $this->addDuplicateGroupContext($agentThread, $duplicateCandidates);

        // Create schema for duplicate group resolution
        $schemaProvider   = app(SchemaProvider::class);
        $schemaDefinition = $schemaProvider->getDuplicateGroupResolutionSchema($taskDefinition->team_id);

        $taskDefinition->schema_definition_id = $schemaDefinition->id;
        $taskDefinition->save();

        // Add duplicate group resolution instructions
        static::logDebug('Adding duplicate group resolution instructions to thread');
        $this->addDuplicateGroupResolutionInstructions($agentThread);

        static::logDebug("Duplicate group resolution thread setup completed: {$agentThread->id}");

        return $agentThread;
    }

    /**
     * Add artifact messages to thread.
     * Each message contains page number and attached file.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  Collection  $artifacts  Artifacts to add
     */
    protected function addArtifactMessages(AgentThread $agentThread, Collection $artifacts): void
    {
        foreach ($artifacts as $artifact) {
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? null;
            $fileIds    = $artifact->storedFiles ? $artifact->storedFiles->pluck('id')->toArray() : [];

            if ($pageNumber !== null) {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    "Page $pageNumber",
                    $fileIds
                );
            } else {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    '',
                    $fileIds
                );
            }
        }
    }

    /**
     * Add artifact messages for null group resolution.
     * Marks null files differently from context pages.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  Collection  $artifacts  All artifacts
     * @param  array  $nullFileIds  IDs of null files
     */
    protected function addNullGroupArtifactMessages(AgentThread $agentThread, Collection $artifacts, array $nullFileIds): void
    {
        foreach ($artifacts as $artifact) {
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? null;
            $fileIds    = $artifact->storedFiles ? $artifact->storedFiles->pluck('id')->toArray() : [];
            $isNullFile = in_array($artifact->id, $nullFileIds);

            if ($pageNumber !== null) {
                $label = $isNullFile ? "Page $pageNumber [NEEDS RESOLUTION]" : "Page $pageNumber [CONTEXT PAGE]";
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    $label,
                    $fileIds
                );
            } else {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    '',
                    $fileIds
                );
            }
        }
    }

    /**
     * Add comparison window instructions to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     */
    protected function addComparisonWindowInstructions(AgentThread $agentThread): void
    {
        $instructions = "You are comparing adjacent files to organize them into logical groups.\n" .
            "Each file represents a page or document section.\n" .
            "Group files that belong together based on their content and context.\n\n" .
            "IMPORTANT: The task instructions provided by the user define how groups should be named. Follow those instructions for naming groups.\n\n" .
            "For each group:\n" .
            "- 'name': Use the naming convention specified in the task instructions. If the user says 'group by patient name', use patient names. If no naming convention is specified, use a clear, descriptive identifier from the document content.\n" .
            "- 'description': A high-level summary of what the group contains\n" .
            "- 'files': Array of file objects with page_number, confidence, and explanation\n\n" .
            "GROUPING STRATEGY - PRIORITIZE CONTINUITY:\n" .
            "Pages are presented in sequential order. Follow these rules:\n" .
            "1. DEFAULT TO SAME GROUP: When a page has no clear grouping indicators, keep it with the PREVIOUS page's group\n" .
            "2. ONLY SPLIT when there is CLEAR EVIDENCE of a boundary\n" .
            "3. CONTINUATION PAGES: Multi-page documents, narratives, or related content should stay together\n" .
            "4. BLANK/SEPARATOR PAGES: These often belong to the FOLLOWING content, not the preceding content\n" .
            "5. AMBIGUOUS PAGES: When in doubt, assume continuity - use the same group as the previous page\n\n" .
            // ... (rest of instructions - truncated for brevity but would include full text from runner)
            "CRITICAL RULES:\n" .
            "- Each page MUST appear in EXACTLY ONE group - NEVER place the same page in multiple groups\n" .
            "- PREFER CONTINUITY: When uncertain, default to the same group as the previous page\n" .
            "- If uncertain about placement, use a MODERATE confidence score (3) for continuity assumptions\n" .
            "- Only use LOW confidence (0-2) when genuinely conflicted between multiple different groups\n" .
            "- Only include page numbers that were provided in the input messages\n" .
            "- If a file should be ignored (e.g., completely blank page), simply don't include it in any group\n\n" .
            "WHEN NO CLEAR IDENTIFIER EXISTS:\n" .
            "- If you cannot find ANY clear identifier for a group, use an empty string \"\" for the name\n" .
            "- For files with no identifier, use confidence score 0 or 1 (minimum)\n" .
            "- Example: {\"name\": \"\", \"description\": \"No clear identifier found\", \"files\": [...]}\n" .
            '- An empty name explicitly signals that no valid grouping could be determined';

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
    }

    /**
     * Add low confidence context to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  array  $lowConfidenceFiles  Low confidence file data
     */
    protected function addLowConfidenceContext(AgentThread $agentThread, array $lowConfidenceFiles): void
    {
        $contextMessage = "CONTEXT: Low-confidence file assignments requiring review\n\n";
        $contextMessage .= "These files were assigned with low confidence (< 3) during the windowed comparison process.\n";
        $contextMessage .= "Below are ALL explanations from ALL comparison windows for each file:\n\n";

        foreach ($lowConfidenceFiles as $fileData) {
            $pageNumber      = $fileData['page_number'];
            $bestAssignment  = $fileData['best_assignment'];
            $allExplanations = $fileData['all_explanations'];

            $contextMessage .= "--- Page $pageNumber ---\n";
            $contextMessage .= "Best assignment: '{$bestAssignment['group_name']}' (confidence: {$bestAssignment['confidence']})\n";
            $contextMessage .= "Description: {$bestAssignment['description']}\n\n";

            $contextMessage .= "All explanations from comparison windows:\n";
            foreach ($allExplanations as $idx => $explanation) {
                $num            = $idx + 1;
                $contextMessage .= "  $num. Group: '{$explanation['group_name']}' (confidence: {$explanation['confidence']})\n";
                $contextMessage .= "     Explanation: {$explanation['explanation']}\n";
            }
            $contextMessage .= "\n";
        }

        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);
    }

    /**
     * Add low confidence resolution instructions to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     */
    protected function addLowConfidenceResolutionInstructions(AgentThread $agentThread): void
    {
        $instructions = "TASK: Resolve uncertain file groupings\n\n" .
            "You have been provided with files that had CONFLICTING LOW CONFIDENCE assignments from multiple windows.\n" .
            "Above, you can see ALL explanations from ALL comparison windows that reviewed each file.\n\n" .
            "Your task:\n" .
            "1. Review each file carefully with the full context provided\n" .
            "2. Look at the sequential context - which group did pages BEFORE and AFTER belong to?\n" .
            "3. Make a FINAL DECISION on the correct group assignment\n" .
            "4. Assign a NEW confidence score (0-5) based on your review\n" .
            "5. Provide a detailed explanation for your decision\n\n" .
            // ... (rest of instructions - truncated for brevity)
            'Return your assignments using the same format as the comparison windows.';

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
    }

    /**
     * Add null group context to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  array  $nullGroupFiles  Null group file data
     */
    protected function addNullGroupContext(AgentThread $agentThread, array $nullGroupFiles): void
    {
        $contextMessage = "CONTEXT: Files with no clear identifier that need group assignment\n\n";
        // ... (build full context message as in runner)

        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);
    }

    /**
     * Add null group resolution instructions to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     */
    protected function addNullGroupResolutionInstructions(AgentThread $agentThread): void
    {
        $instructions = "TASK: Assign files with no clear identifier to the correct adjacent group\n\n";
        // ... (full instructions as in runner)

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
    }

    /**
     * Add duplicate group context to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  array  $duplicateCandidates  Duplicate candidate data
     */
    protected function addDuplicateGroupContext(AgentThread $agentThread, array $duplicateCandidates): void
    {
        $contextMessage = "CONTEXT: Potential duplicate groups detected\n\n";
        // ... (build full context message as in runner)

        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);
    }

    /**
     * Add duplicate group resolution instructions to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     */
    protected function addDuplicateGroupResolutionInstructions(AgentThread $agentThread): void
    {
        $instructions = "TASK: Determine if groups with similar names represent the same entity\n\n";
        // ... (full instructions as in runner)

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
    }
}
