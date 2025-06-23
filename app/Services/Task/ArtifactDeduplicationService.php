<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ArtifactDeduplicationService
{
    use HasDebugLogging;

    protected Agent $agent;

    public function __construct()
    {
        // Use a default agent for deduplication, or could be configured
        $this->agent = Agent::where('model', 'gpt-4o')->first() ?? Agent::first();
    }

    /**
     * Deduplicate names across artifacts by normalizing similar names
     *
     * @param Collection|Artifact[] $artifacts
     * @return void
     */
    public function deduplicateArtifactNames(Collection $artifacts): void
    {
        static::log("Starting deduplication for " . $artifacts->count() . " artifacts");

        // Extract all unique names from all artifacts
        $allNames = [];
        $newNames = [];
        $existingNames = [];

        foreach ($artifacts as $artifact) {
            if (!$artifact->json_content) {
                continue;
            }

            $records = $this->extractRecordsWithNames($artifact->json_content);
            foreach ($records as $record) {
                $name = $record['name'];
                if ($record['is_new']) {
                    $newNames[$name] = true;
                } else {
                    $existingNames[$name] = true;
                }
                $allNames[$name] = true;
            }
        }

        if (empty($newNames)) {
            static::log("No new records with names found for deduplication");
            return;
        }

        static::log("Found " . count($newNames) . " unique new names and " . count($existingNames) . " existing names");

        // Get normalized name mappings from LLM
        $nameMappings = $this->getNormalizedNameMappings(array_keys($newNames), array_keys($existingNames));

        if (!$nameMappings) {
            static::log("Failed to get normalized name mappings from LLM");
            return;
        }

        // Apply normalized names to all artifacts
        $this->applyNormalizedNamesToArtifacts($artifacts, $nameMappings);

        static::log("Deduplication completed");
    }

    /**
     * Extract all records that have a "name" field from JSON content
     *
     * @param array $data
     * @param string $path
     * @return array
     */
    protected function extractRecordsWithNames(array $data, string $path = ''): array
    {
        $records = [];

        // If this object has a name field, include it
        if (isset($data['name']) && is_string($data['name'])) {
            $records[] = [
                'path' => $path,
                'data' => $data,
                'name' => $data['name'],
                'id' => $data['id'] ?? null,
                'is_new' => ($data['id'] ?? null) === null
            ];
        }

        // Recursively search nested structures
        foreach ($data as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (is_array($value)) {
                // Handle arrays of objects
                if (isset($value[0]) && is_array($value[0])) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $itemPath = "$currentPath.$index";
                            $nestedRecords = $this->extractRecordsWithNames($item, $itemPath);
                            $records = array_merge($records, $nestedRecords);
                        }
                    }
                } else {
                    // Handle single objects
                    $nestedRecords = $this->extractRecordsWithNames($value, $currentPath);
                    $records = array_merge($records, $nestedRecords);
                }
            }
        }

        return $records;
    }

    /**
     * Get normalized name mappings from LLM
     *
     * @param array $newNames
     * @param array $existingNames
     * @return array|null
     */
    protected function getNormalizedNameMappings(array $newNames, array $existingNames): ?array
    {
        $prompt = $this->buildDeduplicationPrompt($newNames, $existingNames);

        // Create agent thread for deduplication
        $agentThread = new AgentThread();
        $agentThread->agent()->associate($this->agent);
        $agentThread->name = 'Name Deduplication';
        $agentThread->system_message = 'You are a data normalization assistant that helps deduplicate similar record names.';
        $agentThread->user_message = $prompt;
        $agentThread->save();

        // Run the thread
        $threadRun = (new AgentThreadService())->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->json_content) {
            return null;
        }

        return $threadRun->lastMessage->json_content;
    }

    /**
     * Build the deduplication prompt
     *
     * @param array $newNames
     * @param array $existingNames
     * @return string
     */
    protected function buildDeduplicationPrompt(array $newNames, array $existingNames): string
    {
        $newNamesJson = json_encode(array_values($newNames), JSON_PRETTY_PRINT);
        $existingNamesJson = json_encode(array_values($existingNames), JSON_PRETTY_PRINT);

        return <<<PROMPT
I have extracted records from multiple parallel processing tasks. Some NEW records may represent the same entity as existing records but with slightly different names. Please analyze the names and create a mapping of original names to normalized names.

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

Example response format:
{
  "CNCC Chiropractic Natural Care Center": "Chiropractic Natural Care Center",
  "Dr. Smith's Office": "Smith Medical Office",
  "ABC Corp.": "ABC Corporation"
}

Focus on:
1. Matching new records to existing ones when they represent the same entity
2. Standardizing variations to match existing records where possible
3. Creating consistent naming for truly new entities
4. Removing redundant information while preserving distinguishing details
PROMPT;
    }

    /**
     * Apply normalized names to all artifacts
     *
     * @param Collection $artifacts
     * @param array $nameMappings
     * @return void
     */
    protected function applyNormalizedNamesToArtifacts(Collection $artifacts, array $nameMappings): void
    {
        if (empty($nameMappings)) {
            static::log("No name mappings to apply");
            return;
        }

        $updatedCount = 0;

        foreach ($artifacts as $artifact) {
            if (!$artifact->json_content) {
                continue;
            }

            $updated = false;
            $jsonContent = $artifact->json_content;
            
            // Recursively update names in the JSON content
            $this->updateNamesInJsonContent($jsonContent, $nameMappings, $updated);
            
            if ($updated) {
                $artifact->json_content = $jsonContent;
                $artifact->save();
                $updatedCount++;
                static::log("Updated artifact {$artifact->id} with normalized names");
            }
        }

        static::log("Updated $updatedCount artifacts with normalized names");
    }

    /**
     * Recursively update names in JSON content based on mappings
     *
     * @param array &$data
     * @param array $nameMappings
     * @param bool &$updated
     * @return void
     */
    protected function updateNamesInJsonContent(array &$data, array $nameMappings, bool &$updated): void
    {
        // Check if this object has a name field and null ID
        if (isset($data['name']) && is_string($data['name']) && ($data['id'] ?? null) === null) {
            $originalName = $data['name'];
            if (isset($nameMappings[$originalName])) {
                $data['name'] = $nameMappings[$originalName];
                $updated = true;
                static::log("Updated name from '$originalName' to '{$data['name']}'");
            }
        }

        // Recursively process nested structures
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                // Handle arrays of objects
                if (isset($value[0]) && is_array($value[0])) {
                    foreach ($value as &$item) {
                        if (is_array($item)) {
                            $this->updateNamesInJsonContent($item, $nameMappings, $updated);
                        }
                    }
                } else {
                    // Handle single objects
                    $this->updateNamesInJsonContent($value, $nameMappings, $updated);
                }
            }
        }
    }

}