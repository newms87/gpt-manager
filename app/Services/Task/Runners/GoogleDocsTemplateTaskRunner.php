<?php

namespace App\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
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

        static::log('GoogleDocsTemplateTaskRunner: Template variables extracted', [
            'variables' => $templateVariables,
        ]);

        $agentThread    = $this->setupAgentThread($inputArtifacts);
        $refinedMapping = $this->refineVariableMappingWithAgent($agentThread, $templateVariables);

        // Step 6: Create document from template
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId,
            $refinedMapping['variables'],
            $refinedMapping['title']
        );

        static::log('GoogleDocsTemplateTaskRunner: Document created', [
            'document_id' => $newDocument['document_id'],
            'url'         => $newDocument['url'],
        ]);

        // Create output artifact
        $artifact = $this->createOutputArtifact($newDocument, $refinedMapping);
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
     * Find Google Doc template as StoredFile from artifacts, text content, or directives
     */
    protected function findGoogleDocStoredFile(): ?StoredFile
    {
        $artifacts = $this->taskProcess->inputArtifacts;
        static::log('GoogleDocsTemplateTaskRunner: Starting template search', [
            'total_artifacts' => $artifacts->count(),
        ]);

        $contentSearchRequest = ContentSearchRequest::create()
            ->withLlmModel(config('google-docs.file_id_detection_model'))
            ->withFieldPath('template_stored_file_id')
            ->withNaturalLanguageQuery('Given the context, identify the Google Doc ID if it exists in the context, otherwise do not respond with a google doc ID')
            ->withRegexPattern("/[a-zA-Z0-9_-]{25,60}/")
            ->withTaskDefinition($this->taskDefinition)
            ->searchArtifacts($artifacts);

        $templateStoredFileId = app(ContentSearchService::class)->search($contentSearchRequest);

        // Step 1: Check artifacts for template_stored_file_id
        foreach($artifacts as $artifact) {
            $storedFileId = $artifact->json_content['template_stored_file_id'] ?? $artifact->meta['template_stored_file_id'] ?? null;

            if ($storedFileId) {
                static::log('GoogleDocsTemplateTaskRunner: Found template_stored_file_id: ' . $storedFileId);

                $storedFile = StoredFile::find($storedFileId);
                if ($storedFile) {
                    static::log('GoogleDocsTemplateTaskRunner: Retrieved StoredFile', [
                        'filename' => $storedFile->filename,
                        'url'      => $storedFile->url,
                    ]);

                    return $storedFile;
                }
            }
        }

        // Step 2: Search for Google Doc ID in text_content using regex
        $googleDocId = $this->searchForGoogleDocIdInArtifacts($artifacts);
        if ($googleDocId) {
            // Validate with agent that this is the template we're looking for
            if ($this->checkForGoogleDocIdInContext($googleDocId, $artifacts, 'artifacts')) {
                return $this->findOrCreateStoredFileForGoogleDoc($googleDocId);
            }
        }

        // Step 3: Check directives for Google Doc ID
        $directives = $this->taskDefinition->taskDefinitionDirectives()->with('directive')->get();
        if ($directives->isNotEmpty()) {
            $googleDocId = $this->searchForGoogleDocIdInDirectives($directives);
            if ($googleDocId) {
                // Validate with agent that this is the template we're looking for
                if ($this->checkForGoogleDocIdInContext($googleDocId, $directives, 'directives')) {
                    return $this->findOrCreateStoredFileForGoogleDoc($googleDocId);
                }
            }
        }

        static::log('GoogleDocsTemplateTaskRunner: No template found');

        return null;
    }

    /**
     * Refine variable mapping and get document title using agent
     */
    protected function refineVariableMappingWithAgent(AgentThread $agentThread, array $templateVariables): array
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

        static::log('GoogleDocsTemplateTaskRunner: Agent refinement completed', [
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

    protected function hasGoogleDocIdMatch($text): bool
    {
        return preg_match('/[a-zA-Z0-9_-]{25,60}/', $text);
    }

    /**
     * Search for Google Doc ID in artifacts text_content using regex
     */
    protected function searchForGoogleDocIdInArtifacts($artifacts): ?string
    {
        /** @var Artifact $artifact */
        foreach($artifacts as $artifact) {
            if ($artifact->text_content && $this->hasGoogleDocIdMatch($artifact->text_content)) {
                // Validate with agent that this is the template we're looking for
                if ($this->checkForGoogleDocIdInContext($artifact->text_content 'artifacts')) {
                    return $this->findOrCreateStoredFileForGoogleDoc($googleDocId);
                }
            }
        }

        return null;
    }

    /**
     * Search for Google Doc ID in directives using regex
     */
    protected function searchForGoogleDocIdInDirectives($directives): ?string
    {
        foreach($directives as $taskDirective) {
            $directive = $taskDirective->directive;
            if ($directive && $directive->directive_text) {
                // Search for Google Doc ID pattern in directive content
                if (preg_match('/(?:docs\.google\.com\/document\/d\/|drive\.google\.com\/file\/d\/)?([a-zA-Z0-9_-]{25,60})/', $directive->directive_text, $matches)) {
                    $potentialId = $matches[1];
                    // Validate it's a proper Google Doc ID format
                    if (preg_match('/^[a-zA-Z0-9_-]{25,60}$/', $potentialId)) {
                        static::log('GoogleDocsTemplateTaskRunner: Found potential Google Doc ID in directive', [
                            'doc_id'       => $potentialId,
                            'directive_id' => $directive->id,
                        ]);

                        return $potentialId;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Validate with agent that the text contains a Google Doc ID
     */
    protected function checkForGoogleDocIdInContext(string $context): bool
    {
        // Setup a simple agent thread for validation
        $agent = $this->taskDefinition->agent;

        $agentThread = app(ThreadRepository::class)->create($agent, 'Validate Google Doc Template ID');

        $instructions = <<<INSTRUCTIONS
We are searching for a Google Doc ID that would be explicitly stated as a google doc ID or it would be in the format:
https://docs.google.com/document/d/{google_doc_id}/edit?tab=t.0

Here is the context:

{$context}

Does this context contain a google doc ID? Return the google doc ID if it does.
INSTRUCTIONS;

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        try {
            $artifact = $this->runAgentThread($agentThread);
            if ($artifact && $artifact->text_content) {
                $response = strtoupper(trim($artifact->text_content));
                $isValid  = str_contains($response, 'YES');

                static::log('GoogleDocsTemplateTaskRunner: Agent validation result', [
                    'doc_id'   => $googleDocId,
                    'valid'    => $isValid,
                    'response' => $response,
                ]);

                return $isValid;
            }
        } catch(Exception $e) {
            static::log('GoogleDocsTemplateTaskRunner: Agent validation failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Default to true if agent fails
        return true;
    }

    /**
     * Find or create a StoredFile for the Google Doc ID
     */
    protected function findOrCreateStoredFileForGoogleDoc(string $googleDocId): StoredFile
    {
        $filename = "Google Doc Template: {$googleDocId}";
        $url      = "https://docs.google.com/document/d/{$googleDocId}/edit";

        // Try to find existing stored file with this filename
        $storedFile = StoredFile::where('filename', $filename)->first();

        if (!$storedFile) {
            // Create new stored file
            $storedFile = StoredFile::create([
                'team_id'  => $this->taskDefinition->team_id,
                'disk'     => 'google',
                'filename' => $filename,
                'url'      => $url,
                'mime'     => 'application/vnd.google-apps.document',
                'size'     => 0,
            ]);

            static::log('GoogleDocsTemplateTaskRunner: Created StoredFile for Google Doc', [
                'stored_file_id' => $storedFile->id,
                'filename'       => $filename,
                'url'            => $url,
            ]);
        } else {
            static::log('GoogleDocsTemplateTaskRunner: Found existing StoredFile for Google Doc', [
                'stored_file_id' => $storedFile->id,
                'filename'       => $filename,
            ]);
        }

        return $storedFile;
    }
}
