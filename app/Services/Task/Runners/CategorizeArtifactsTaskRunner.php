<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use Illuminate\Support\Collection;

/**
 * The runner processes all artifacts and applies category classification based on the user's prompt.
 * Behavior summary:
 *
 * - Each input artifact will be assigned a `__category` field (in JSON) with the category determined by the process.
 * - After all task processes finish:
 *   - Artifacts with the `__exclude` category will be removed from the final output.
 *   - Artifacts will be sorted based on their `position` field.
 *   - Any artifact categorized as `__previous` will inherit the category of the artifact immediately before it in the
 *   sorted list.
 * - The final list of artifacts (after filtering and sorting) will be set as the output.
 */
class CategorizeArtifactsTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Categorize Artifacts';

    // Special category constants
    const string
        CATEGORY_USE_PREVIOUS = '__previous',
        CATEGORY_EXCLUDE = '__exclude';

    // Flag to enable sequential mode
    protected bool $sequentialMode = false;

    // Store the last valid category for sequential mode
    protected ?string $lastCategory = null;

    // Store categorized artifacts by category
    protected array $categorizedArtifacts = [];

    public function prepareProcess(): void
    {
        parent::prepareProcess();

        // Check if sequential mode is enabled in the task definition config
        $this->sequentialMode = $this->taskProcess->config['sequential_mode'] ?? false;

        $this->activity('Preparing to categorize artifacts' . ($this->sequentialMode ? ' in sequential mode' : ''), 5);
    }

    public function run(): void
    {
        $taskDefinition = $this->taskRun->taskDefinition;
        $agent          = $taskDefinition->agent;

        // Make sure to include page numbers in the agent thread so the agent can reference them
        $this->includePageNumbersInThread = true;

        // Setup the agent thread
        $agentThread = $this->setupAgentThread();

        $this->activity('Using agent to categorize artifacts: ' . $agent->name, 10);

        // Get all input artifacts
        $inputArtifacts = $this->taskProcess->inputArtifacts;
        $totalArtifacts = $inputArtifacts->count();

        if ($totalArtifacts === 0) {
            $this->activity('No artifacts to categorize', 100);
            $this->complete([]);

            return;
        }

        $this->activity('Categorizing ' . $totalArtifacts . ' artifacts', 15);

        // Process each artifact one by one
        $percentPerArtifact = 80 / $totalArtifacts;
        $currentPercent     = 15;
        $outputArtifacts    = new Collection();

        foreach($inputArtifacts as $index => $artifact) {
            $currentPercent += $percentPerArtifact;
            $this->activity('Categorizing artifact ' . ($index + 1) . ' of ' . $totalArtifacts, $currentPercent);

            // Process the artifact and get its category
            $category = $this->categorizeArtifact($agentThread, $artifact);

            // Handle the artifact based on its category
            $processedArtifact = $this->processArtifactByCategory($artifact, $category);

            if ($processedArtifact) {
                $outputArtifacts->push($processedArtifact);
            }
        }

        $this->activity('Categorization complete', 95);

        // Complete the task with all the categorized artifacts
        $this->complete($outputArtifacts);
    }

    /**
     * Categorize a single artifact using the agent
     */
    protected function categorizeArtifact(AgentThread $agentThread, Artifact $artifact): string
    {
        $this->activity('Requesting category for artifact: ' . $artifact->name);

        // Create a schema definition for the category response
        $schemaDefinition = $this->createCategorySchema();

        // Clone the agent thread for this specific artifact
        $artifactThread = $this->cloneAgentThreadForArtifact($agentThread, $artifact);

        // Run the agent with the schema to get the category
        $outputArtifact = $this->runAgentThreadWithSchema($artifactThread, $schemaDefinition);

        // If we didn't receive an artifact from the agent, use a default category
        if (!$outputArtifact || empty($outputArtifact->json_content['category'])) {
            $this->activity('Failed to get category from agent, using default');

            return 'Uncategorized';
        }

        $category = $outputArtifact->json_content['category'];
        $this->activity('Artifact categorized as: ' . $category);

        return $category;
    }

    /**
     * Process an artifact based on its assigned category
     */
    protected function processArtifactByCategory(Artifact $artifact, string $category): ?Artifact
    {
        // Handle special categories
        if ($category === self::CATEGORY_EXCLUDE) {
            $this->activity('Excluding artifact: ' . $artifact->name, null);

            return null;
        }

        if ($category === self::CATEGORY_USE_PREVIOUS) {
            if (!$this->sequentialMode) {
                $this->activity('Use Previous category ignored - sequential mode not enabled', null);
                $category = 'Uncategorized';
            } elseif ($this->lastCategory === null) {
                $this->activity('No previous category available, using Uncategorized', null);
                $category = 'Uncategorized';
            } else {
                $this->activity('Using previous category: ' . $this->lastCategory, null);
                $category = $this->lastCategory;
            }
        } else {
            // Store this as the last valid category for Use Previous
            $this->lastCategory = $category;
        }

        // Clone the artifact and update its category
        $categorizedArtifact = $this->cloneArtifactWithCategory($artifact, $category);

        // Store the artifact in our category mapping
        if (!isset($this->categorizedArtifacts[$category])) {
            $this->categorizedArtifacts[$category] = [];
        }
        $this->categorizedArtifacts[$category][] = $categorizedArtifact;

        return $categorizedArtifact;
    }

    /**
     * Create a schema definition for the category response
     */
    protected function createCategorySchema(): SchemaDefinition
    {
        // Build the category description based on available categories
        $categoryDescription = "Classify the artifact by choosing the most appropriate category. " .
            "In addition to user-defined categories, you may also use the following options:\n\n";

        if ($this->sequentialMode) {
            $categoryDescription .= "* " . self::CATEGORY_USE_PREVIOUS . ": Use this if you're not 100% confident about the category, and the artifact seems to continue or relate to the previous one (e.g., part of a series or sequential pages).\n";
        }

        $categoryDescription .= "* " . self::CATEGORY_EXCLUDE . ": Use this to exclude the artifact entirely from the output.";

        return SchemaDefinition::make([
            'name'   => 'Category',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'category' => [
                        'type'        => 'string',
                        'description' => $categoryDescription,
                    ],
                ],
                'required'   => ['category'],
            ],
        ]);
    }

    /**
     * Clone the agent thread for a specific artifact
     */
    protected function cloneAgentThreadForArtifact(AgentThread $agentThread, Artifact $artifact): AgentThread
    {
        // Clone the agent thread
        $artifactThread     = clone $agentThread;
        $artifactThread->id = null; // Ensure we get a new ID when saved
        $artifactThread->save();

        // Add the artifact content to the thread
        $message = "Please categorize the following artifact:\n\n";
        $message .= "Artifact Name: {$artifact->name}\n";
        $message .= "Content:\n{$artifact->text_content}\n";

        // Add any stored files information if available
        if ($artifact->storedFiles->isNotEmpty()) {
            $message .= "\nFiles:\n";
            foreach($artifact->storedFiles as $file) {
                $message .= "- {$file->name} (Page: {$file->page_number})\n";
            }
        }

        // Add the message to the thread
        app(ThreadRepository::class)->addMessageToThread($artifactThread, $message);

        return $artifactThread;
    }

    /**
     * Clone an artifact and update its category
     */
    protected function cloneArtifactWithCategory(Artifact $artifact, string $category): Artifact
    {
        // Create a new artifact based on the original
        $categorizedArtifact = new Artifact([
            'name'               => $artifact->name,
            'text_content'       => $artifact->text_content,
            'json_content'       => array_merge($artifact->json_content ?? [], ['category' => $category]),
            'task_definition_id' => $this->taskRun->task_definition_id,
            'metadata'           => array_merge($artifact->metadata ?? [], ['original_artifact_id' => $artifact->id]),
        ]);

        // Save the new artifact
        $categorizedArtifact->save();

        // Copy any stored files from the original artifact
        if ($artifact->storedFiles->isNotEmpty()) {
            $fileIds = $artifact->storedFiles->pluck('id')->toArray();
            $categorizedArtifact->storedFiles()->attach($fileIds);
        }

        return $categorizedArtifact;
    }
}
