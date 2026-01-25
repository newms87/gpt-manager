<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ArtifactDeduplicationService
{
    use HasDebugLogging;

    protected Agent $agent;

    public function __construct()
    {
        // Use a configured model for deduplication
        $model       = config('ai.artifact_deduplication.model');
        $this->agent = Agent::where('model', $model)->first() ?? Agent::first();
    }

    /**
     * Deduplicate names across artifacts by normalizing similar names
     *
     * @param  Collection|Artifact[]  $artifacts
     */
    public function deduplicateArtifactNames(Collection $artifacts): void
    {
        static::logDebug('Starting deduplication for ' . $artifacts->count() . ' artifacts');

        // Extract names grouped by their path in the JSON structure
        $namesByPath = [];

        foreach ($artifacts as $artifact) {
            if (!$artifact->json_content) {
                continue;
            }

            $this->extractNamesGroupedByPath($artifact->json_content, '', $namesByPath);
        }

        if (empty($namesByPath)) {
            static::logDebug('No records with names found for deduplication');

            return;
        }

        static::logDebug('Found names at ' . count($namesByPath) . ' different paths');

        // Process each path separately
        $allMappings = [];

        foreach ($namesByPath as $path => $names) {
            $newNames      = [];
            $existingNames = [];

            foreach ($names as $name => $info) {
                if ($info['has_new']) {
                    $newNames[$name] = true;
                }
                if ($info['has_existing']) {
                    $existingNames[$name] = true;
                }
            }

            if (empty($newNames)) {
                static::logDebug("No new names at path: $path");

                continue;
            }

            static::logDebug("Processing path '$path': " . count($newNames) . ' new names, ' . count($existingNames) . ' existing names');

            // Get normalized name mappings for this specific path
            $pathMappings = $this->getNormalizedNameMappings(
                array_keys($newNames),
                array_keys($existingNames),
                $path
            );

            if ($pathMappings) {
                foreach ($pathMappings as $original => $normalized) {
                    $allMappings[$path][$original] = $normalized;
                    static::logDebug("Path '$path': Mapping '$original' => '$normalized'");
                }
            }
        }

        if (empty($allMappings)) {
            static::logDebug('No name mappings generated');

            return;
        }

        // Apply normalized names to all artifacts
        $this->applyNormalizedNamesToArtifacts($artifacts, $allMappings);

        static::logDebug('Deduplication completed');
    }

    /**
     * Extract names grouped by their path in the JSON structure
     */
    protected function extractNamesGroupedByPath(array $data, string $currentPath, array &$namesByPath): void
    {
        // If this object has a name field, track it
        if (isset($data['name']) && is_string($data['name'])) {
            $path  = $currentPath ?: 'root';
            $name  = $data['name'];
            $isNew = ($data['id'] ?? null) === null;

            if (!isset($namesByPath[$path])) {
                $namesByPath[$path] = [];
            }

            if (!isset($namesByPath[$path][$name])) {
                $namesByPath[$path][$name] = [
                    'has_new'      => false,
                    'has_existing' => false,
                ];
            }

            if ($isNew) {
                $namesByPath[$path][$name]['has_new'] = true;
            } else {
                $namesByPath[$path][$name]['has_existing'] = true;
            }
        }

        // Recursively search nested structures
        foreach ($data as $key => $value) {
            if ($key === 'name' || $key === 'id') {
                continue; // Skip these as we've already processed them
            }

            if (is_array($value)) {
                // Handle arrays of objects
                if (isset($value[0]) && is_array($value[0])) {
                    // For arrays, we want to group all items at the same path
                    $arrayPath = $currentPath ? "$currentPath.$key" : $key;
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $this->extractNamesGroupedByPath($item, $arrayPath, $namesByPath);
                        }
                    }
                } else {
                    // Handle single objects
                    $objectPath = $currentPath ? "$currentPath.$key" : $key;
                    $this->extractNamesGroupedByPath($value, $objectPath, $namesByPath);
                }
            }
        }
    }

    /**
     * Get normalized name mappings from LLM
     */
    protected function getNormalizedNameMappings(array $newNames, array $existingNames, string $path): ?array
    {
        $prompt = $this->buildDeduplicationPrompt($newNames, $existingNames, $path);

        // Create agent thread using the proper repository pattern
        $threadRepository = app(ThreadRepository::class);
        $agentThread      = $threadRepository->create($this->agent, 'Name Deduplication - ' . $path);

        // Add system message
        $systemMessage = 'You are a data normalization assistant that helps deduplicate similar record names. Always respond with valid JSON.';
        $threadRepository->addMessageToThread($agentThread, $systemMessage);

        // Add user message with the prompt
        $threadRepository->addMessageToThread($agentThread, $prompt);

        // Run the thread
        $threadRun = (new AgentThreadService())->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->content) {
            static::logDebug("Failed to get response from LLM for path: $path");

            return null;
        }

        // Parse the JSON response
        try {
            $content = $threadRun->lastMessage->content;
            static::logDebug('Raw LLM response: ' . substr($content, 0, 500) . (strlen($content) > 500 ? '...' : ''));

            // Try to extract JSON from the response (in case it's wrapped in markdown or has extra text)
            // First try to extract from markdown code blocks
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
                $content = $matches[1];
            } elseif (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $matches)) {
                // Otherwise try to find raw JSON
                $content = $matches[0];
            }

            $jsonContent = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                static::logDebug('Failed to parse JSON response: ' . json_last_error_msg());
                static::logDebug('Content that failed to parse: ' . $content);

                return null;
            }

            return $jsonContent;
        } catch (\Exception $e) {
            static::logDebug('Error parsing JSON response: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Build the deduplication prompt
     */
    protected function buildDeduplicationPrompt(array $newNames, array $existingNames, string $path): string
    {
        $newNamesJson      = json_encode(array_values($newNames), JSON_PRETTY_PRINT);
        $existingNamesJson = json_encode(array_values($existingNames), JSON_PRETTY_PRINT);

        // Create context based on the path
        $context = $this->getContextFromPath($path);

        return <<<PROMPT
I have extracted records from multiple parallel processing tasks at the JSON path: "$path"
Context: $context

Some NEW records may represent the same entity as existing records but with slightly different names. Please analyze the names and create a mapping of original names to normalized names.

NEW record names (these need to be normalized):
$newNamesJson

EXISTING record names (use these as reference for normalization):
$existingNamesJson

Please return a JSON object where:
- Keys are the ORIGINAL names from the new records that need to be changed
- Values are the NORMALIZED names (either matching an existing record name or a better normalized version)
- Only include entries where the name actually needs to be changed
- If a new name is already in its best form, don't include it

IMPORTANT RULES:
1. Prefer existing record names when a new record clearly matches an existing one
2. If multiple existing records could match, choose the most complete/professional name
3. For new records that don't match any existing records, normalize them to a consistent format
4. Remove redundant abbreviations or acronyms when the full name is clear
5. Consider the context - dates should be normalized to a consistent format, provider names to professional formats, etc.

Example response format:
{
  "CNCC Chiropractic Natural Care Center": "Chiropractic Natural Care Center",
  "Dr. Smith's Office": "Smith Medical Office",
  "Nov 18th, 2025": "November 18, 2025",
  "11/18/2025": "November 18, 2025"
}

Focus on:
1. Matching new records to existing ones when they represent the same entity
2. Standardizing variations to match existing records where possible
3. Creating consistent naming for truly new entities
4. Removing redundant information while preserving distinguishing details
5. Use appropriate formatting based on the type of data (dates, names, etc.)
PROMPT;
    }

    /**
     * Get context description from path
     */
    protected function getContextFromPath(string $path): string
    {
        if ($path === 'root') {
            return 'These are top-level record names';
        }

        // Try to infer context from path
        $parts    = explode('.', $path);
        $lastPart = end($parts);

        if (strpos($path, 'date') !== false) {
            return 'These appear to be date-related records';
        } elseif (strpos($path, 'provider') !== false) {
            return 'These appear to be healthcare provider names';
        } elseif ($lastPart === 'dates_of_service') {
            return 'These are dates of service';
        }

        return "These are records at the '$lastPart' level";
    }

    /**
     * Apply normalized names to all artifacts
     */
    protected function applyNormalizedNamesToArtifacts(Collection $artifacts, array $nameMappingsByPath): void
    {
        if (empty($nameMappingsByPath)) {
            static::logDebug('No name mappings to apply');

            return;
        }

        $totalUpdates = 0;

        foreach ($artifacts as $artifact) {
            if (!$artifact->json_content) {
                continue;
            }

            $updates     = [];
            $jsonContent = $artifact->json_content;

            // Recursively update names in the JSON content
            $this->updateNamesInJsonContent($jsonContent, '', $nameMappingsByPath, $updates);

            if (!empty($updates)) {
                $artifact->json_content = $jsonContent;
                $artifact->save();

                static::logDebug("=== Updated artifact {$artifact->id} ===");
                foreach ($updates as $update) {
                    static::logDebug("  Path: {$update['path']} | '{$update['original']}' => '{$update['normalized']}'");
                    $totalUpdates++;
                }
            }
        }

        static::logDebug("Total updates across all artifacts: $totalUpdates");
    }

    /**
     * Recursively update names in JSON content based on mappings
     */
    protected function updateNamesInJsonContent(array &$data, string $currentPath, array $nameMappingsByPath, array &$updates): void
    {
        $path = $currentPath ?: 'root';

        // Check if this object has a name field and null ID
        if (isset($data['name']) && is_string($data['name']) && ($data['id'] ?? null) === null) {
            $originalName = $data['name'];

            // Check if we have mappings for this path
            if (isset($nameMappingsByPath[$path][$originalName])) {
                $normalizedName = $nameMappingsByPath[$path][$originalName];
                $data['name']   = $normalizedName;

                $updates[] = [
                    'path'       => $path,
                    'original'   => $originalName,
                    'normalized' => $normalizedName,
                ];
                static::logDebug("Updated name at path '$path': '$originalName' => '$normalizedName'");
            }
        }

        // Recursively process nested structures
        foreach ($data as $key => &$value) {
            if ($key === 'name' || $key === 'id') {
                continue; // Skip these as we've already processed them
            }

            if (is_array($value)) {
                // Handle arrays of objects
                if (isset($value[0]) && is_array($value[0])) {
                    $arrayPath = $currentPath ? "$currentPath.$key" : $key;
                    foreach ($value as &$item) {
                        if (is_array($item)) {
                            $this->updateNamesInJsonContent($item, $arrayPath, $nameMappingsByPath, $updates);
                        }
                    }
                } else {
                    // Handle single objects
                    $objectPath = $currentPath ? "$currentPath.$key" : $key;
                    $this->updateNamesInJsonContent($value, $objectPath, $nameMappingsByPath, $updates);
                }
            }
        }
    }
}
