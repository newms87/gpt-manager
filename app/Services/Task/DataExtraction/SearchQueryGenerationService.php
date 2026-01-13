<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use App\Traits\SchemaFieldHelper;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates item-specific search queries for array extractions.
 *
 * When extracting arrays of items (e.g., multiple diagnoses from a document),
 * each item needs its own set of search queries for duplicate detection.
 * This service makes a follow-up LLM request to generate those queries.
 *
 * Usage:
 * ```php
 * $searchQueryService = app(SearchQueryGenerationService::class);
 * $searchQueries = $searchQueryService->generateForArrayItems(
 *     $taskProcess,
 *     [
 *         ['name' => 'Chiropractic Adjustment', 'date' => '2024-10-22'],
 *         ['name' => 'Traction', 'date' => '2024-10-22'],
 *     ],
 *     $identityFields // field definitions from schema
 * );
 * ```
 */
class SearchQueryGenerationService
{
    use HasDebugLogging;
    use SchemaFieldHelper;

    private const int BATCH_SIZE = 10;

    /**
     * Generate search queries for extracted array items.
     *
     * @param  TaskProcess  $taskProcess  The task process context
     * @param  array  $extractedItems  Array of extracted data items
     * @param  array  $identityFields  Field definitions from the schema
     * @return array<int, array> Indexed by position with search queries for each item
     */
    public function generateForArrayItems(
        TaskProcess $taskProcess,
        array $extractedItems,
        array $identityFields
    ): array {
        if (empty($extractedItems)) {
            return [];
        }

        $taskRun = $taskProcess->taskRun;
        $schema  = $taskRun->taskDefinition->schemaDefinition?->schema;

        static::logDebug('Generating search queries for array items', [
            'item_count'      => count($extractedItems),
            'identity_fields' => $identityFields,
        ]);

        // If 10 or fewer items, process in a single request
        if (count($extractedItems) <= self::BATCH_SIZE) {
            return $this->processBatch($taskProcess, $extractedItems, $identityFields, $schema, 0);
        }

        // Batch into groups of 10
        $results = [];
        $batches = array_chunk($extractedItems, self::BATCH_SIZE, true);

        foreach ($batches as $batchStartIndex => $batch) {
            static::logDebug('Processing search query batch', [
                'batch_start'  => $batchStartIndex,
                'batch_size'   => count($batch),
                'total_items'  => count($extractedItems),
            ]);

            $batchResults = $this->processBatch($taskProcess, $batch, $identityFields, $schema, $batchStartIndex);

            // Merge results maintaining original indices
            foreach ($batchResults as $index => $searchQueries) {
                $results[$index] = $searchQueries;
            }
        }

        return $results;
    }

    /**
     * Process a batch of items and return their search queries.
     *
     * @param  array  $items  Batch of extracted items
     * @param  array  $identityFields  Field definitions
     * @param  array|null  $schema  Schema definition for type inference
     * @param  int  $startIndex  Starting index for this batch (for result mapping)
     * @return array<int, array> Search queries indexed by original position
     */
    protected function processBatch(
        TaskProcess $taskProcess,
        array $items,
        array $identityFields,
        ?array $schema,
        int $startIndex
    ): array {
        $yamlContext = $this->buildYamlContext($items);
        $schemaData  = $this->buildIndexedSchema($items, $identityFields, $schema);

        $response = $this->runThread($taskProcess, $yamlContext, $schemaData['schema']);

        return $this->parseResponse($response, $schemaData['hashMapping'], $startIndex);
    }

    /**
     * Format extracted items as YAML for the LLM prompt.
     *
     * @param  array  $extractedItems  Array of extracted data items
     */
    public function buildYamlContext(array $extractedItems): string
    {
        $yaml = Yaml::dump(['items' => array_values($extractedItems)], 4, 2);

        return $yaml;
    }

    /**
     * Build indexed schema with properties for each item using hash keys.
     *
     * Uses hash keys instead of numeric indices to ensure PHP's json_encode
     * outputs a JSON object (not an array). Returns both the schema and a
     * mapping from hash keys back to original indices.
     *
     * Structure:
     * {
     *   type: 'object',
     *   properties: {
     *     'ha1b2c3d4': { type: 'object', properties: { search_query: [...] } },
     *     'he5f6g7h8': { type: 'object', properties: { search_query: [...] } },
     *   },
     *   required: ['ha1b2c3d4', 'he5f6g7h8', ...],
     *   $defs: { stringSearch: ..., dateSearch: ..., etc. }
     * }
     *
     * @param  array  $extractedItems  Array of extracted data items
     * @param  array  $identityFields  Field definitions from the schema
     * @param  array|null  $schema  Full schema for type inference
     * @return array{schema: array, hashMapping: array<string, int>} Schema and hash-to-index mapping
     */
    public function buildIndexedSchema(array $extractedItems, array $identityFields, ?array $schema = null): array
    {
        $properties  = [];
        $required    = [];
        $hashMapping = [];

        // Use array_values to ensure sequential 0-based indexing regardless of input keys
        $items = array_values($extractedItems);

        foreach ($items as $index => $item) {
            // Generate a short hash key from the item content to ensure JSON object encoding
            // Prefix with 'h' to prevent all-numeric hashes being serialized as numbers by json_encode
            $hashKey     = 'h' . substr(md5(json_encode($item) . $index), 0, 8);
            $description = $this->buildItemDescription($item, $index);

            $properties[$hashKey] = [
                'type'        => 'object',
                'description' => $description,
                'properties'  => [
                    'search_query' => $this->buildSearchQueryItemSchema($identityFields, $schema),
                ],
                'required'             => ['search_query'],
                'additionalProperties' => false,
            ];

            $required[]            = $hashKey;
            $hashMapping[$hashKey] = $index;
        }

        return [
            'schema'      => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => $properties,
                'required'             => $required,
                '$defs'                => $this->getSearchTypeDefinitions($identityFields, $schema),
            ],
            'hashMapping' => $hashMapping,
        ];
    }

    /**
     * Build the search_query array schema for a single item.
     *
     * @param  array  $identityFields  Field names to include in search
     * @param  array|null  $schema  Full schema for type inference
     */
    public function buildSearchQueryItemSchema(array $identityFields, ?array $schema = null): array
    {
        $properties = [];

        foreach ($identityFields as $field) {
            $properties[$field] = $this->buildFieldSearchRef($field, $schema);
        }

        return [
            'type'        => 'array',
            'description' => 'MINIMUM 3 search queries ordered MOST SPECIFIC to LEAST SPECIFIC. ' .
                'Query 1: Most specific - use exact extracted values. ' .
                'Query 2: Less specific - key identifying terms only. ' .
                'Query 3: Broadest - general concept only.',
            'items'       => [
                'type'                 => 'object',
                'properties'           => $properties,
                'additionalProperties' => false,
                'required'             => $identityFields,
            ],
            'minItems'    => 3,
        ];
    }

    /**
     * Build a $ref for a field's search type based on its schema type.
     *
     * @param  string  $fieldName  The field name
     * @param  array|null  $schema  Full schema for type inference
     */
    protected function buildFieldSearchRef(string $fieldName, ?array $schema): array
    {
        $fieldType = $this->determineFieldType($fieldName, $schema);

        $defName = match ($fieldType) {
            'date', 'date-time' => 'dateSearch',
            'boolean'           => 'booleanSearch',
            'integer'           => 'integerSearch',
            'number'            => 'numericSearch',
            default             => 'stringSearch',
        };

        return ['$ref' => '#/$defs/' . $defName];
    }

    /**
     * Determine the type of a field from the schema definition.
     *
     * @param  string  $fieldName  The field name
     * @param  array|null  $schema  Full schema for type inference
     */
    protected function determineFieldType(string $fieldName, ?array $schema): string
    {
        // Handle native TeamObject columns
        if ($fieldName === 'name') {
            return 'string';
        }
        if ($fieldName === 'date') {
            return 'date';
        }

        // Delegate to trait for schema-based field type detection
        return $this->getSchemaFieldType($fieldName, $schema);
    }

    /**
     * Build description for an item including its identity field values.
     *
     * @param  array  $item  The extracted item data
     * @param  int  $index  The item's position in the array
     */
    public function buildItemDescription(array $item, int $index): string
    {
        $parts = [];

        foreach ($item as $field => $value) {
            if ($value !== null && $value !== '') {
                $valueStr = is_string($value) ? $value : json_encode($value);
                $parts[]  = "{$field}='{$valueStr}'";
            }
        }

        $summary = implode(', ', $parts);

        return "Search query for item {$index}: {$summary}";
    }

    /**
     * Run the LLM thread to generate search queries.
     *
     * @param  TaskProcess  $taskProcess  The task process for context
     * @param  string  $yamlContext  YAML-formatted extracted items
     * @param  array  $schema  The indexed response schema
     *
     * @throws \RuntimeException When the API returns an error or thread fails
     */
    protected function runThread(TaskProcess $taskProcess, string $yamlContext, array $schema): array
    {
        $taskRun        = $taskProcess->taskRun;
        $taskDefinition = $taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new \RuntimeException('No agent configured for search query generation');
        }

        $systemMessage = $this->buildSystemMessage();

        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Search Query Generation')
            ->withSystemMessage($systemMessage)
            ->withMessage($yamlContext)
            ->build();

        // Create a temporary SchemaDefinition for the response format
        $responseSchemaDefinition = $this->createResponseSchemaDefinition($schema);

        // Get timeout from config (default 2 minutes)
        $config  = $taskDefinition->task_runner_config ?? [];
        $timeout = $config['search_query_timeout']     ?? 120;
        $timeout = max(1, min((int)$timeout, 300)); // Between 1-300 seconds

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($responseSchemaDefinition, null, app(JsonSchemaService::class))
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            $error = $threadRun->error ?? 'Unknown error';
            throw new \RuntimeException("Search query generation thread failed: {$error}");
        }

        $data = $threadRun->lastMessage?->getJsonContent();

        if (!is_array($data)) {
            throw new \RuntimeException('Search query generation returned invalid response format');
        }

        return $data;
    }

    /**
     * Build the system message for search query generation.
     */
    protected function buildSystemMessage(): string
    {
        return <<<'PROMPT'
You are generating search queries for duplicate detection. For each item, provide MINIMUM 3 search queries ordered from MOST SPECIFIC to LEAST SPECIFIC.

Purpose: Find existing records efficiently - we check exact matches first, then broaden if needed.
- Query 1: Most specific - use exact extracted values
- Query 2: Less specific - key identifying terms only
- Query 3: Broadest - general concept only

Example for name="Dr. John Smith":
[
  {"name": ["Dr.", "John", "Smith"]},
  {"name": ["John", "Smith"]},
  {"name": ["Smith"]}
]

Example for name="Chiropractic Adjustment", date="2024-10-22":
[
  {"name": ["Chiropractic", "Adjustment"], "date": {"operator": "=", "value": "2024-10-22", "value2": null}},
  {"name": ["Chiropractic"]},
  {"name": ["Adjustment"]}
]

The items are provided in YAML format. Generate search queries for each numbered item in your response.
PROMPT;
    }

    /**
     * Parse the LLM response and extract search queries using hash mapping.
     *
     * @param  array  $response  Raw response from LLM (keyed by hash strings)
     * @param  array<string, int>  $hashMapping  Map of hash keys to batch indices
     * @param  int  $startIndex  Starting index for mapping results to original positions
     * @return array<int, array> Search queries indexed by original position
     */
    public function parseResponse(array $response, array $hashMapping, int $startIndex = 0): array
    {
        $results = [];

        foreach ($hashMapping as $hashKey => $batchIndex) {
            $originalIndex = $startIndex + $batchIndex;

            if (isset($response[$hashKey]['search_query']) && is_array($response[$hashKey]['search_query'])) {
                $results[$originalIndex] = $response[$hashKey]['search_query'];
            } else {
                static::logWarning("Missing search query for item {$originalIndex} (hash: {$hashKey})");
                $results[$originalIndex] = null;
            }
        }

        return $results;
    }

    /**
     * Load and return search type definitions from YAML.
     *
     * Only includes the types actually needed based on identity field types.
     *
     * @param  array  $identityFields  Field names to check
     * @param  array|null  $schema  Full schema for type inference
     */
    public function getSearchTypeDefinitions(array $identityFields, ?array $schema = null): array
    {
        $allDefs = $this->loadSearchQueryDefs();

        // Determine which types are needed
        $usedTypes = [];

        foreach ($identityFields as $field) {
            $fieldType = $this->determineFieldType($field, $schema);

            $searchType = match ($fieldType) {
                'date', 'date-time' => 'dateSearch',
                'boolean'           => 'booleanSearch',
                'integer'           => 'integerSearch',
                'number'            => 'numericSearch',
                default             => 'stringSearch',
            };

            if (!in_array($searchType, $usedTypes, true)) {
                $usedTypes[] = $searchType;
            }
        }

        // Filter to only include used types
        $defs = [];
        foreach ($usedTypes as $type) {
            if (isset($allDefs[$type])) {
                $defs[$type] = $allDefs[$type];
            }
        }

        return $defs;
    }

    /**
     * Load the search query type definitions from YAML file.
     */
    protected function loadSearchQueryDefs(): array
    {
        $path    = app_path('Services/JsonSchema/search_query.def.yaml');
        $content = Yaml::parseFile($path);

        return $content['$defs'] ?? [];
    }

    /**
     * Create a temporary SchemaDefinition for the response format.
     */
    protected function createResponseSchemaDefinition(array $schema): SchemaDefinition
    {
        $schemaDefinition         = new SchemaDefinition();
        $schemaDefinition->schema = $schema;
        $schemaDefinition->name   = 'SearchQueryGenerationResponse';
        $schemaDefinition->type   = SchemaDefinition::TYPE_AGENT_RESPONSE;

        return $schemaDefinition;
    }
}
