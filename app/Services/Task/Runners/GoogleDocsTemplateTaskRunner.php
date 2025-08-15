<?php

namespace App\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Repositories\ThreadRepository;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\ContentSearchService;
use Exception;
use Newms87\Danx\Models\Utilities\StoredFile;

class GoogleDocsTemplateTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Google Docs Template';

    public function run(): void
    {
        static::log('GoogleDocsTemplateTaskRunner: Starting', [
            'task_process_id' => $this->taskProcess->id,
        ]);

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        // Step 1: Find the Google Doc template ID
        $googleDocFileId = $this->findGoogleDocFileId($inputArtifacts);
        if (!$googleDocFileId) {
            throw new Exception("No Google Docs file ID found in any input artifact. Expected either 'template_stored_file_id' with StoredFile containing Google Docs URL, or 'google_doc_file_id' with direct file ID.");
        }

        // Step 2: Extract variables from the template
        $googleDocsApi     = app(GoogleDocsApi::class);
        $templateVariables = $googleDocsApi->extractTemplateVariables($googleDocFileId);
        
        static::log('GoogleDocsTemplateTaskRunner: Template variables extracted', [
            'variables' => $templateVariables,
        ]);

        // Step 3: Collect and map data to variables
        $templateData = $this->collectTemplateData($inputArtifacts);
        $mappedData = $this->mapDataToVariables($templateVariables, $templateData);

        // Step 4: Refine mapping with agent
        $agentThread = $this->setupAgentThread($inputArtifacts);
        $refinedMapping = $this->refineVariableMappingWithAgent($agentThread, $templateVariables, $mappedData, $templateData);

        // Step 5: Create document from template
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId,
            $refinedMapping['variables'],
            $refinedMapping['title']
        );
        
        static::log('GoogleDocsTemplateTaskRunner: Document created', [
            'document_id' => $newDocument['document_id'],
            'url' => $newDocument['url'],
        ]);

        // Create output artifact
        $artifact = $this->createOutputArtifact($newDocument, $refinedMapping);
        $this->complete([$artifact]);
    }

    /**
     * Find google_doc_file_id in artifacts using ContentSearchService
     */
    protected function findGoogleDocFileId($artifacts): ?string
    {
        static::log('GoogleDocsTemplateTaskRunner: Starting file ID search', [
            'total_artifacts' => $artifacts->count(),
        ]);

        // First check for template_stored_file_id in json_content
        foreach($artifacts as $artifact) {
            if ($artifact->json_content && isset($artifact->json_content['template_stored_file_id'])) {
                $storedFileId = $artifact->json_content['template_stored_file_id'];
                static::log('GoogleDocsTemplateTaskRunner: Found template_stored_file_id', [
                    'stored_file_id' => $storedFileId,
                ]);
                
                // Retrieve the StoredFile and extract Google Doc ID from URL
                $storedFile = StoredFile::find($storedFileId);
                if ($storedFile && $storedFile->url) {
                    // Extract Google Doc ID from URL
                    if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $storedFile->url, $matches)) {
                        static::log('GoogleDocsTemplateTaskRunner: Extracted Google Doc ID from StoredFile URL', [
                            'file_id' => $matches[1],
                            'url' => $storedFile->url,
                        ]);
                        return $matches[1];
                    }
                    
                    // If URL is already just the ID
                    if (preg_match('/^[a-zA-Z0-9_-]{25,60}$/', $storedFile->url)) {
                        static::log('GoogleDocsTemplateTaskRunner: StoredFile URL is already a Google Doc ID', [
                            'file_id' => $storedFile->url,
                        ]);
                        return $storedFile->url;
                    }
                }
            }
        }

        // Fall back to existing search logic
        $searchService = app(ContentSearchService::class);
        
        // Build base search request that will be reused
        $baseRequest = function() {
            return ContentSearchRequest::create()
                ->withFieldPath('google_doc_file_id')
                ->withNaturalLanguageQuery('Find the Google Docs file ID from the text content. Google Docs file IDs are typically 44 characters long, contain letters, numbers, hyphens, and underscores, and often found in URLs like: https://docs.google.com/document/d/FILE_ID/edit or as standalone strings.')
                ->withRegexPattern('/(?:docs\.google\.com\/document\/d\/|drive\.google\.com\/file\/d\/)?([a-zA-Z0-9_-]{25,60})/')
                ->withValidation(function($fileId) {
                    return preg_match('/^[a-zA-Z0-9_-]{25,60}$/', $fileId) === 1;
                })
                ->withTaskDefinition($this->taskDefinition)
                ->withMaxAttempts(1);
        };

        // Try searching artifacts first
        $result = $searchService->search(
            $baseRequest()->searchArtifacts($artifacts)
        );

        if ($result->isSuccessful()) {
            static::log('GoogleDocsTemplateTaskRunner: File ID found', [
                'file_id' => $result->getValue(),
                'source' => 'artifacts',
            ]);
            return $result->getValue();
        }

        // Try searching directives if artifacts search failed
        $directivesResult = $searchService->search(
            $baseRequest()->searchDirectives(
                $this->taskDefinition->taskDefinitionDirectives()->with('directive')->get()
            )
        );

        if ($directivesResult->isSuccessful()) {
            static::log('GoogleDocsTemplateTaskRunner: File ID found', [
                'file_id' => $directivesResult->getValue(),
                'source' => 'directives',
            ]);
            return $directivesResult->getValue();
        }

        static::log('GoogleDocsTemplateTaskRunner: No file ID found');
        return null;
    }

    /**
     * Collect all data from artifacts to use as template data
     */
    protected function collectTemplateData($artifacts): array
    {
        $data = [];

        foreach($artifacts as $artifact) {
            // Collect from json_content (excluding google_doc_file_id)
            if ($artifact->json_content) {
                foreach($artifact->json_content as $key => $value) {
                    if ($key !== 'google_doc_file_id') {
                        $data[$key] = $value;
                    }
                }
            }

            // Collect from meta (excluding google_doc_file_id)
            if ($artifact->meta) {
                foreach($artifact->meta as $key => $value) {
                    if ($key !== 'google_doc_file_id') {
                        $data[$key] = $value;
                    }
                }
            }
        }

        static::log('GoogleDocsTemplateTaskRunner: Template data collected', [
            'total_keys' => count($data),
            'keys' => array_keys($data),
        ]);

        return $data;
    }

    /**
     * Map the collected data to the extracted template variables
     */
    protected function mapDataToVariables(array $templateVariables, array $templateData): array
    {
        $mappedData = [];

        foreach($templateVariables as $variable) {
            // Direct match
            if (isset($templateData[$variable])) {
                $mappedData[$variable] = $templateData[$variable];
            } 
            // Try case-insensitive match
            else {
                $lowerVariable = strtolower($variable);
                $matchFound = false;
                
                foreach($templateData as $key => $value) {
                    if (strtolower($key) === $lowerVariable) {
                        $mappedData[$variable] = $value;
                        $matchFound = true;
                        break;
                    }
                }

                // If still not found, leave empty
                if (!$matchFound) {
                    $mappedData[$variable] = '';
                }
            }
        }

        static::log('GoogleDocsTemplateTaskRunner: Variables mapped', [
            'mapped_count' => count($mappedData),
            'variables' => array_keys($mappedData),
        ]);

        return $mappedData;
    }

    /**
     * Refine variable mapping and get document title using agent
     */
    protected function refineVariableMappingWithAgent(AgentThread $agentThread, array $templateVariables, array $mappedData, array $availableData): array
    {
        $templateVariablesList = implode(', ', array_map(fn($v) => "{{$v}}", $templateVariables));
        $mappedDataJson        = json_encode($mappedData, JSON_PRETTY_PRINT);
        $availableDataJson     = json_encode($availableData, JSON_PRETTY_PRINT);

        $instructions = <<<INSTRUCTIONS
You are helping to populate a Google Docs template with data. Please review the template variables and suggest the best mapping and document title.

Template Variables Found: {$templateVariablesList}

Current Automatic Mapping:
{$mappedDataJson}

All Available Data:
{$availableDataJson}

Please provide your response in the following JSON format:
{
    "title": "Suggested document title based on the data",
    "variables": {
        "variable1": "mapped_value1",
        "variable2": "mapped_value2"
    },
    "reasoning": "Brief explanation of your mapping choices"
}

Requirements:
1. Suggest an appropriate document title based on the available data
2. Map each template variable to the most appropriate value from the available data
3. If no good mapping exists for a variable, use an empty string ""
4. Provide brief reasoning for your choices

IMPORTANT: Return ONLY valid JSON in the exact format shown above.
INSTRUCTIONS;

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        // Run the agent thread to get refined mapping
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->text_content) {
            throw new Exception("Agent failed to provide variable mapping refinement");
        }

        // Parse JSON response
        $response = json_decode($artifact->text_content, true);

        if (!$response || !isset($response['title']) || !isset($response['variables'])) {
            throw new Exception("Agent response was not in the expected JSON format");
        }

        static::log('GoogleDocsTemplateTaskRunner: Agent refinement completed', [
            'title' => $response['title'],
            'variables_count' => count($response['variables']),
        ]);

        return $response;
    }

    /**
     * Create output artifact with Google Docs information
     */
    protected function createOutputArtifact(array $newDocument, array $refinedMapping): Artifact
    {
        $artifact = Artifact::create([
            'team_id'      => $this->taskDefinition->team_id,
            'name'         => 'Generated Google Doc: ' . $newDocument['title'],
            'text_content' => "Successfully created Google Docs document from template.\n\nDocument Title: {$newDocument['title']}\nDocument URL: {$newDocument['url']}\n\nVariable Mapping:\n" . json_encode($refinedMapping['variables'], JSON_PRETTY_PRINT),
            'meta'         => [
                'google_doc_url'   => $newDocument['url'],
                'google_doc_id'    => $newDocument['document_id'],
                'document_title'   => $newDocument['title'],
                'created_at'       => $newDocument['created_at'],
                'variable_mapping' => $refinedMapping['variables'],
                'reasoning'        => $refinedMapping['reasoning'] ?? null,
            ],
            'json_content' => [
                'document' => $newDocument,
                'mapping'  => $refinedMapping,
            ],
        ]);

        return $artifact;
    }
}
