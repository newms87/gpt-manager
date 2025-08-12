<?php

namespace App\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use Exception;
use Illuminate\Support\Arr;

class GoogleDocsTemplateTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Google Docs Template';

    public function run(): void
    {
        $inputArtifacts = $this->taskProcess->inputArtifacts;
        
        // Find the Google Doc template ID from artifacts
        $googleDocFileId = $this->findGoogleDocFileId($inputArtifacts);
        
        if (!$googleDocFileId) {
            throw new Exception("No google_doc_file_id found in any input artifact");
        }
        
        // Step 1: Extract variables from the Google Doc template using direct API
        $googleDocsApi = app(GoogleDocsApi::class);
        $templateVariables = $googleDocsApi->extractTemplateVariables($googleDocFileId);
        
        // Step 2: Collect all data from artifacts to use for template variable replacement
        $templateData = $this->collectTemplateData($inputArtifacts);
        
        // Step 3: Map the template data to the extracted variables
        $mappedData = $this->mapDataToVariables($templateVariables, $templateData);
        
        // Step 4: Ask agent for variable mapping refinement and document naming
        $agentThread = $this->setupAgentThread($inputArtifacts);
        $refinedMapping = $this->refineVariableMappingWithAgent($agentThread, $templateVariables, $mappedData, $templateData);
        
        // Step 5: Create document directly using GoogleDocsApi
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId, 
            $refinedMapping['variables'], 
            $refinedMapping['title']
        );
        
        // Create output artifact with document information
        $artifact = $this->createOutputArtifact($newDocument, $refinedMapping);
        
        $this->complete([$artifact]);
    }
    
    /**
     * Find google_doc_file_id in artifacts (check both json_content and meta)
     */
    protected function findGoogleDocFileId($artifacts): ?string
    {
        foreach ($artifacts as $artifact) {
            // Check in json_content
            if ($artifact->json_content) {
                $fileId = Arr::get($artifact->json_content, 'google_doc_file_id');
                if ($fileId) {
                    return $fileId;
                }
            }
            
            // Check in meta
            if ($artifact->meta) {
                $fileId = Arr::get($artifact->meta, 'google_doc_file_id');
                if ($fileId) {
                    return $fileId;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract template variables using GoogleDocsApi (for backward compatibility)
     */
    public static function extractTemplateVariablesStatic(string $googleDocFileId, TaskDefinition $taskDefinition): array
    {
        return app(GoogleDocsApi::class)->extractTemplateVariables($googleDocFileId);
    }
    
    /**
     * Instance method to extract template variables
     */
    public function extractTemplateVariables(string $googleDocFileId): array
    {
        return app(GoogleDocsApi::class)->extractTemplateVariables($googleDocFileId);
    }
    
    /**
     * Collect all data from artifacts to use as template data
     */
    protected function collectTemplateData($artifacts): array
    {
        $data = [];
        
        foreach ($artifacts as $artifact) {
            // Collect from json_content (excluding google_doc_file_id)
            if ($artifact->json_content) {
                foreach ($artifact->json_content as $key => $value) {
                    if ($key !== 'google_doc_file_id') {
                        $data[$key] = $value;
                    }
                }
            }
            
            // Collect from meta (excluding google_doc_file_id)
            if ($artifact->meta) {
                foreach ($artifact->meta as $key => $value) {
                    if ($key !== 'google_doc_file_id') {
                        $data[$key] = $value;
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Map the collected data to the extracted template variables
     */
    protected function mapDataToVariables(array $templateVariables, array $templateData): array
    {
        $mappedData = [];
        
        foreach ($templateVariables as $variable) {
            // Direct match
            if (isset($templateData[$variable])) {
                $mappedData[$variable] = $templateData[$variable];
            }
            // Try case-insensitive match
            else {
                $lowerVariable = strtolower($variable);
                foreach ($templateData as $key => $value) {
                    if (strtolower($key) === $lowerVariable) {
                        $mappedData[$variable] = $value;
                        break;
                    }
                }
            }
            
            // If still not found, leave empty
            if (!isset($mappedData[$variable])) {
                $mappedData[$variable] = '';
            }
        }
        
        return $mappedData;
    }
    
    /**
     * Refine variable mapping and get document title using agent
     */
    protected function refineVariableMappingWithAgent(AgentThread $agentThread, array $templateVariables, array $mappedData, array $availableData): array
    {
        $templateVariablesList = implode(', ', array_map(fn($v) => "{{$v}}", $templateVariables));
        $mappedDataJson = json_encode($mappedData, JSON_PRETTY_PRINT);
        $availableDataJson = json_encode($availableData, JSON_PRETTY_PRINT);
        
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
        
        return $response;
    }
    
    /**
     * Create output artifact with Google Docs information
     */
    protected function createOutputArtifact(array $newDocument, array $refinedMapping): Artifact
    {
        $artifact = Artifact::create([
            'team_id' => $this->taskDefinition->team_id,
            'name' => 'Generated Google Doc: ' . $newDocument['title'],
            'text_content' => "Successfully created Google Docs document from template.\n\nDocument Title: {$newDocument['title']}\nDocument URL: {$newDocument['url']}\n\nVariable Mapping:\n" . json_encode($refinedMapping['variables'], JSON_PRETTY_PRINT),
            'meta' => [
                'google_doc_url' => $newDocument['url'],
                'google_doc_id' => $newDocument['document_id'],
                'document_title' => $newDocument['title'],
                'created_at' => $newDocument['created_at'],
                'variable_mapping' => $refinedMapping['variables'],
                'reasoning' => $refinedMapping['reasoning'] ?? null,
            ],
            'json_content' => [
                'document' => $newDocument,
                'mapping' => $refinedMapping,
            ]
        ]);
        
        return $artifact;
    }
}