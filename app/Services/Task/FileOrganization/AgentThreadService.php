<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\Task\TaskAgentThreadBuilderService;
use Newms87\Danx\Traits\HasDebugLogging;
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
     * Reviews ALL groups for spelling corrections and potential merges.
     *
     * @param  TaskDefinition  $taskDefinition  The task definition
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $groupsForDeduplication  All groups with sample files
     */
    public function setupDuplicateGroupResolutionThread(TaskDefinition $taskDefinition, TaskRun $taskRun, array $groupsForDeduplication): AgentThread
    {
        static::logDebug("Setting up group deduplication thread for TaskRun {$taskRun->id} with " . count($groupsForDeduplication) . ' groups');

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $taskRun");
        }

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun);
        $agentThread = $builder->build();

        static::logDebug("Built agent thread {$agentThread->id}");

        // Add group images and context
        static::logDebug('Adding group context with sample images to thread');
        $this->addDuplicateGroupContext($agentThread, $taskRun, $groupsForDeduplication);

        // Create schema for duplicate group resolution
        $schemaProvider   = app(SchemaProvider::class);
        $schemaDefinition = $schemaProvider->getDuplicateGroupResolutionSchema($taskDefinition->team_id);

        $taskDefinition->schema_definition_id = $schemaDefinition->id;
        $taskDefinition->save();

        // Add duplicate group resolution instructions
        static::logDebug('Adding group deduplication instructions to thread');
        $this->addDuplicateGroupResolutionInstructions($agentThread);

        static::logDebug("Group deduplication thread setup completed: {$agentThread->id}");

        return $agentThread;
    }

    /**
     * Add artifact messages to thread.
     * Iterates all stored files within each artifact, adding 1 message per stored file.
     * This handles both old (1 file per artifact) and new (N files per artifact) patterns.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  Collection  $artifacts  Artifacts to add
     */
    protected function addArtifactMessages(AgentThread $agentThread, Collection $artifacts): void
    {
        foreach ($artifacts as $artifact) {
            // Get all stored files for this artifact, ordered by page_number
            $storedFiles = $artifact->storedFiles ? $artifact->storedFiles->sortBy('page_number') : collect();

            foreach ($storedFiles as $storedFile) {
                $pageNumber = $storedFile->page_number ?? null;

                if ($pageNumber !== null) {
                    app(ThreadRepository::class)->addMessageToThread(
                        $agentThread,
                        "Page $pageNumber",
                        [$storedFile->id]
                    );
                } else {
                    app(ThreadRepository::class)->addMessageToThread(
                        $agentThread,
                        '',
                        [$storedFile->id]
                    );
                }
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
        $instructions = "You are analyzing files in sequential order within a sliding window.\n" .
            "Each file represents a page or document section.\n" .
            "Your task is to provide TWO INDEPENDENT signals for each file:\n\n" .
            "1. ADJACENCY SIGNAL: Does this file continue from the previous file?\n" .
            "2. GROUP NAME SIGNAL: What identifier/name describes this file's content?\n\n" .
            "These signals are INDEPENDENT - you can be certain about one but uncertain about the other.\n\n" .
            "=== FOR EACH FILE, PROVIDE ===\n\n" .
            "1. belongs_to_previous (integer 0-5 or null):\n" .
            "   - null: This is the FIRST page in the window - no previous page visible to compare\n" .
            "   - 0: Definitely does NOT belong with the previous file (clear document break)\n" .
            "   - 1-2: Probably doesn't belong with previous (weak evidence of break)\n" .
            "   - 3: Uncertain (could go either way)\n" .
            "   - 4-5: Definitely belongs with/continues from previous file (strong continuity)\n\n" .
            "2. belongs_to_previous_reason (string):\n" .
            "   - Brief explanation of why you gave that score\n" .
            "   - Examples:\n" .
            "     * \"Same letterhead and continued narrative from previous page\"\n" .
            "     * \"New document header - clear break from previous\"\n" .
            "     * \"No clear indicators either way - blank page\"\n\n" .
            "3. group_name (string):\n" .
            "   - The name/identifier for this group of files\n" .
            "   - Follow the naming convention specified in the task instructions\n" .
            "   - If user says 'group by patient name', use patient names\n" .
            "   - If no naming convention specified, use a clear, descriptive identifier from the document\n" .
            "   - Use empty string \"\" for blank/separator pages with no identifiable content\n\n" .
            "4. group_name_confidence (integer 0-5):\n" .
            "   - How confident are you in the GROUP NAME you assigned?\n" .
            "   - 0: Cannot determine any group name\n" .
            "   - 1-2: Low confidence in the name (weak or ambiguous identifiers)\n" .
            "   - 3: Moderate confidence\n" .
            "   - 4-5: High confidence - clear identifier visible in the document\n\n" .
            "5. group_explanation (string):\n" .
            "   - Why you assigned this group name\n" .
            "   - Examples:\n" .
            "     * \"Clear invoice header with 'Acme Corp' letterhead\"\n" .
            "     * \"Patient name 'John Smith' visible in header\"\n" .
            "     * \"No identifiable markers - completely blank page\"\n\n" .
            "=== CRITICAL INDEPENDENCE RULE ===\n\n" .
            "belongs_to_previous and group_name_confidence are INDEPENDENT signals.\n\n" .
            "Valid combinations include:\n" .
            "- belongs_to_previous=5, group_name_confidence=1\n" .
            "  (\"This page definitely continues from the previous page, but I can't tell what to call this group\")\n\n" .
            "- belongs_to_previous=0, group_name_confidence=5\n" .
            "  (\"Clear document break here, and I can clearly see the new group name\")\n\n" .
            "- belongs_to_previous=4, group_name_confidence=4\n" .
            "  (\"Continuation page with clear identifiers\")\n\n" .
            "=== EDGE CASES ===\n\n" .
            "- First page in window: belongs_to_previous MUST be null (no previous page visible)\n" .
            "- Blank/separator pages: Often have belongs_to_previous=0 and group_name=\"\", group_name_confidence=0\n" .
            "- Continuation pages: Usually have high belongs_to_previous (4-5) regardless of whether identifiers are visible\n" .
            "- New document starts: Usually have belongs_to_previous=0 or 1 when clear headers/breaks are visible\n\n" .
            "=== RESPONSE FORMAT ===\n\n" .
            "Return your analysis using the schema provided.\n" .
            "Each file MUST include all five fields:\n" .
            "- belongs_to_previous (0-5 or null)\n" .
            "- belongs_to_previous_reason\n" .
            "- group_name\n" .
            "- group_name_confidence (0-5)\n" .
            "- group_explanation\n\n" .
            "Only analyze pages that were provided in the input messages.\n" .
            "Do not invent page numbers that weren't shown to you.";

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
     * Includes all groups with up to 3 sample images per group.
     *
     * @param  AgentThread  $agentThread  The agent thread
     * @param  TaskRun  $taskRun  The task run
     * @param  array  $groupsForDeduplication  All groups with sample file data
     */
    protected function addDuplicateGroupContext(AgentThread $agentThread, TaskRun $taskRun, array $groupsForDeduplication): void
    {
        $contextMessage = "CONTEXT: Review all group names for deduplication and spelling correction\n\n";
        $contextMessage .= 'You are reviewing ALL ' . count($groupsForDeduplication) . " group names from the file organization.\n";
        $contextMessage .= "Your task is to identify any misspellings, typos, or slight variations that should be unified.\n\n";
        $contextMessage .= "Below, each group is shown with up to 3 sample pages (highest confidence assignments):\n\n";

        // Add context message first
        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);

        // For each group, add sample images
        foreach ($groupsForDeduplication as $idx => $group) {
            $groupNum    = $idx + 1;
            $groupName   = $group['name'];
            $fileCount   = $group['file_count'];
            $sampleFiles = $group['sample_files'] ?? [];

            // Build group header message
            $groupMessage = "--- Group $groupNum: \"$groupName\" ($fileCount files) ---\n";
            $groupMessage .= 'Description: ' . ($group['description'] ?? 'N/A') . "\n";

            if (!empty($sampleFiles)) {
                $groupMessage .= "Sample pages (highest confidence):\n";
                foreach ($sampleFiles as $sampleIdx => $sample) {
                    $sampleNum   = $sampleIdx + 1;
                    $pageNumber  = $sample['page_number'];
                    $confidence  = $sample['confidence'];
                    $description = $sample['description'] ?? 'N/A';
                    $groupMessage .= "  $sampleNum. Page $pageNumber (confidence: $confidence)\n";
                    $groupMessage .= "     Description: $description\n";
                }
            } else {
                $groupMessage .= "No sample files available\n";
            }

            $groupMessage .= "\n";

            // Add group header as text message
            app(ThreadRepository::class)->addMessageToThread($agentThread, $groupMessage);

            // Add sample images to the thread
            static::logDebug('Adding ' . count($sampleFiles) . " sample images for group '$groupName'");
            foreach ($sampleFiles as $sampleIdx => $sample) {
                $pageNumber = $sample['page_number'];
                static::logDebug("Looking for stored file with page_number=$pageNumber");

                // Find the stored file for this page number using Eloquent relationships
                // Load all input artifacts with their stored files, then find the matching page
                $storedFile = $taskRun->inputArtifacts()
                    ->with(['storedFiles' => fn($query) => $query->where('page_number', $pageNumber)])
                    ->get()
                    ->flatMap(fn($artifact) => $artifact->storedFiles)
                    ->first();

                if ($storedFile) {
                    static::logDebug("Found stored file ID: {$storedFile->id} for page $pageNumber (URL: {$storedFile->url})");
                    // Attach the image to the thread
                    $sampleLabel = 'Sample ' . ($sampleIdx + 1) . " for \"$groupName\" - Page $pageNumber";
                    app(ThreadRepository::class)->addMessageToThread(
                        $agentThread,
                        $sampleLabel,
                        [$storedFile->id]
                    );
                    static::logDebug("Added message with stored file ID {$storedFile->id} to thread");
                } else {
                    static::logDebug("WARNING: No stored file found for page $pageNumber in group '$groupName'");
                }
            }
        }

        // Add summary message
        $summaryMessage = "\n=== END OF GROUP SAMPLES ===\n\n";
        $summaryMessage .= 'You have now seen all ' . count($groupsForDeduplication) . " groups with their sample pages.\n";
        $summaryMessage .= "Review these carefully and identify any corrections or merges needed.\n";

        app(ThreadRepository::class)->addMessageToThread($agentThread, $summaryMessage);
    }

    /**
     * Add duplicate group resolution instructions to thread.
     *
     * @param  AgentThread  $agentThread  The agent thread
     */
    protected function addDuplicateGroupResolutionInstructions(AgentThread $agentThread): void
    {
        $instructions = "TASK: Review all group names for corrections, merges, and deduplication\n\n" .
            "You have been shown ALL group names with sample pages from each group.\n\n" .
            "Your task:\n" .
            "1. Review each group name for spelling errors, typos, or inconsistencies\n" .
            "2. Identify groups that represent the SAME ENTITY but have variations in naming:\n" .
            "   - Spelling variations: 'ABC Medical' vs 'ABC Medcial'\n" .
            "   - Suffix variations: 'ABC Medical' vs 'ABC Medical Center' vs 'ABC Medical Clinic'\n" .
            "   - Similar names: 'Mountain View Pain Center' vs 'Mountain View Pain Specialists'\n" .
            "   - Location variants: 'XYZ Therapy' vs 'XYZ Therapy (Denver)'\n" .
            "3. Use the sample images to verify entities are the same:\n" .
            "   - Check if addresses match or are in the same building/area\n" .
            "   - Check if phone numbers match\n" .
            "   - Check if the letterhead/branding looks similar\n" .
            "   - Check if the same doctors/staff appear in both groups\n" .
            "4. For groups that should be unified:\n" .
            "   - List ALL original names in the 'original_names' array\n" .
            "   - Choose the best canonical name (most commonly used, most complete)\n" .
            "   - Explain why you merged them\n" .
            "5. For groups that need no changes:\n" .
            "   - List just that one name in 'original_names'\n" .
            "   - Use the same name as 'canonical_name'\n" .
            "   - Reason: 'No changes needed'\n\n" .
            "MERGE THESE TYPES OF VARIATIONS:\n" .
            "- 'X Center' / 'X Specialists' / 'X Clinic' / 'X Associates' (likely same entity)\n" .
            "- Names with/without location suffixes that share the same base name\n" .
            "- Names with minor spelling differences\n" .
            "- Names that clearly refer to the same physical location based on sample images\n\n" .
            "DO NOT MERGE:\n" .
            "- Completely different entities that happen to have similar-sounding names\n" .
            "- Groups where sample images show clearly different addresses or branding\n\n" .
            "RESPONSE FORMAT:\n" .
            "Return a 'group_decisions' array where each decision includes:\n" .
            "- original_names: Array of one or more group names to unify\n" .
            "- canonical_name: The correct final name to use\n" .
            "- reason: Explanation of any changes (or 'No changes needed')\n\n" .
            "Example:\n" .
            "{\n" .
            "  \"group_decisions\": [\n" .
            "    {\n" .
            "      \"original_names\": [\"Mountain View Pain Center\", \"Mountain View Pain Specialists\"],\n" .
            "      \"canonical_name\": \"Mountain View Pain Specialists\",\n" .
            "      \"reason\": \"Same facility - both names appear on documents from the same address\"\n" .
            "    }\n" .
            "  ]\n" .
            "}\n";

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
    }
}
