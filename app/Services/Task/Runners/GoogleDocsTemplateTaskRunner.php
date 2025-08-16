<?php

namespace App\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use Exception;
use Newms87\Danx\Models\Utilities\StoredFile;

class GoogleDocsTemplateTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Google Docs Template';

    public function run(): void
    {
        // Step 1: Find the Google Doc template stored file
        $storedFile = $this->findGoogleDocStoredFile();
        if (!$storedFile) {
            throw new Exception("Template could not be resolved. No Google Docs template found in artifacts, text content, or directives.");
        }

        // Extract Google Doc ID from the stored file URL
        $googleDocFileId = $this->extractGoogleDocIdFromStoredFile($storedFile);
        if (!$googleDocFileId) {
            throw new Exception("Could not extract Google Doc ID from StoredFile URL: {$storedFile->url}");
        }

        // Step 2: Extract variables from the template
        $googleDocsApi     = app(GoogleDocsApi::class);
        $templateVariables = $googleDocsApi->extractTemplateVariables($googleDocFileId);

        static::log('Template variables extracted', [
            'variables' => $templateVariables,
        ]);

        $agentThread     = $this->setupAgentThread($this->taskProcess->inputArtifacts);
        $variableMapping = $this->executeVariableMappingWithAgent($agentThread, $templateVariables);

        // Step 6: Create document from template
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId,
            $variableMapping['variables'],
            $variableMapping['title']
        );

        static::log('Document created', [
            'document_id' => $newDocument['document_id'],
            'url'         => $newDocument['url'],
        ]);

        // Create output artifact
        $artifact = $this->createOutputArtifact($newDocument, $variableMapping);
        $this->complete([$artifact]);
    }

    /**
     * Extract Google Doc ID from StoredFile URL
     */
    protected function extractGoogleDocIdFromStoredFile(StoredFile $storedFile): ?string
    {
        // Extract Google Doc ID from URL
        if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $storedFile->url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Refine variable mapping and get document title using agent
     */
    protected function executeVariableMappingWithAgent(AgentThread $agentThread, array $templateVariables): array
    {
        $templateVariablesList = implode(', ', array_map(fn($v) => "{{$v}}", $templateVariables));

        $instructions = <<<INSTRUCTIONS
You are helping to populate a Google Docs template with data. Please review the template variables and suggest the best mapping and document title.

Template Variables Found: {$templateVariablesList}

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

        static::log('Agent refinement completed', [
            'title'           => $response['title'],
            'variables_count' => count($response['variables']),
        ]);

        return $response;
    }

    /**
     * Create output artifact with Google Docs information
     */
    protected function createOutputArtifact(array $newDocument, array $refinedMapping): Artifact
    {
        return Artifact::create([
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
    }
}
