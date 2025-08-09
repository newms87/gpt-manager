<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
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
        
        // Extract all variables from artifacts
        $templateVariables = $this->extractTemplateVariables($inputArtifacts);
        
        // Setup agent thread with special instructions
        $agentThread = $this->setupAgentThread($inputArtifacts);
        
        // Add instructions to use the Google Docs MCP tool
        $instructions = $this->buildInstructions($googleDocFileId, $templateVariables);
        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);
        
        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);
        
        if (!$artifact) {
            throw new Exception("Failed to create document from template");
        }
        
        // Store the Google Doc URL in the artifact meta
        $this->storeGoogleDocUrl($artifact, $agentThread);
        
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
     * Extract all template variables from artifacts
     */
    protected function extractTemplateVariables($artifacts): array
    {
        $variables = [];
        
        foreach ($artifacts as $artifact) {
            // Extract from json_content
            if ($artifact->json_content) {
                $variables = array_merge($variables, $this->flattenArray($artifact->json_content));
            }
            
            // Extract from meta (excluding google_doc_file_id)
            if ($artifact->meta) {
                $metaVars = $this->flattenArray($artifact->meta);
                unset($metaVars['google_doc_file_id']);
                $variables = array_merge($variables, $metaVars);
            }
            
            // Extract from text_content if it contains key-value pairs
            if ($artifact->text_content) {
                $textVars = $this->parseTextContentVariables($artifact->text_content);
                $variables = array_merge($variables, $textVars);
            }
        }
        
        return $variables;
    }
    
    /**
     * Flatten nested array to dot notation
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Parse text content for key-value pairs
     */
    protected function parseTextContentVariables(string $text): array
    {
        $variables = [];
        
        // Look for patterns like "key: value" or "key = value"
        preg_match_all('/^([a-zA-Z0-9_][a-zA-Z0-9_]*)\s*[:=]\s*(.+)$/m', $text, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = trim($match[1]);
            $value = trim($match[2]);
            $variables[$key] = $value;
        }
        
        return $variables;
    }
    
    /**
     * Build instructions for the agent to use Google Docs MCP tool
     */
    protected function buildInstructions(string $googleDocFileId, array $templateVariables): string
    {
        $variablesJson = json_encode($templateVariables, JSON_PRETTY_PRINT);
        
        return <<<INSTRUCTIONS
Use the Google Docs MCP tool to create a new document from the template.

Template Document ID: {$googleDocFileId}

Template Variables:
{$variablesJson}

Instructions:
1. Use the google_docs_create_document_from_template MCP tool
2. Pass the template document ID
3. Fill in all {{variable}} placeholders with the corresponding values from the template variables
4. Create a new document with all variables replaced
5. Return the URL of the newly created document

IMPORTANT: Make sure to replace ALL template variables found in the document.
INSTRUCTIONS;
    }
    
    /**
     * Extract Google Doc URL from agent thread messages and store in artifact
     */
    protected function storeGoogleDocUrl(Artifact $artifact, AgentThread $agentThread): void
    {
        // Get the last message from the thread
        $lastMessage = $agentThread->messages()->latest()->first();
        
        if ($lastMessage && $lastMessage->content) {
            // Look for Google Docs URL in the message
            preg_match('/(https:\/\/docs\.google\.com\/document\/d\/[a-zA-Z0-9-_]+[\/\w\?=#&]*)/i', $lastMessage->content, $matches);
            
            if (!empty($matches[1])) {
                $meta = $artifact->meta ?? [];
                $meta['google_doc_url'] = $matches[1];
                $artifact->meta = $meta;
                $artifact->save();
            }
        }
    }
}