<?php

namespace App\Services\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ApiException;

class GoogleDocsTemplateService
{
    use HasDebugLogging;

    /**
     * Parse template variables from content using regex
     */
    public function parseTemplateVariables(string $content): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);

        $variables = array_unique($matches[1] ?? []);

        static::log("Parsed template variables", [
            'variables_found' => count($variables),
            'variables'       => $variables,
        ]);

        return $variables;
    }

    /**
     * Replace template variables in content with provided data
     */
    public function replaceVariables(string $content, array $mappings): string
    {
        foreach($mappings as $variable => $value) {
            // Handle arrays and objects by converting to JSON
            if (is_array($value) || is_object($value)) {
                $stringValue = json_encode($value);
            } else {
                $stringValue = (string)$value;
            }
            $content = str_replace("{{" . $variable . "}}", $stringValue, $content);
        }

        static::log("Variables replaced", [
            'mappings_count' => count($mappings),
            'content_length' => strlen($content),
        ]);

        return $content;
    }

    /**
     * Extract template variables directly from a Google Docs template
     */
    public function extractTemplateVariables(GoogleDocsApi $api, string $templateId): array
    {
        $templateData = $api->readDocument($templateId);

        return $this->parseTemplateVariables($templateData['content']);
    }

    /**
     * Create document from template by copying the template and replacing variables
     */
    public function createDocumentFromTemplate(GoogleDocsApi $api, string $templateId, array $variableMappings, ?string $newTitle = null, ?string $parentFolderId = null): array
    {
        try {
            // Generate title if not provided
            $title = $newTitle ?? ('Document - ' . now()->format('Y-m-d H:i:s'));

            static::log("Creating document from template", [
                'template_id'      => $templateId,
                'title'            => $title,
                'parent_folder_id' => $parentFolderId,
                'variable_count'   => count($variableMappings),
            ]);

            // Step 1: Copy the template document using Drive API
            $copyMetadata = [
                'name' => $title,
            ];

            if ($parentFolderId) {
                $copyMetadata['parents'] = [$parentFolderId];
            }

            $response = $api->postToDriveApi("files/{$templateId}/copy", $copyMetadata);

            $documentData = $response->json();

            // Log the response to debug
            static::log("Drive API copy response", [
                'status'   => $response->status(),
                'response' => $documentData,
            ]);

            if (!$response->successful()) {
                throw new ApiException('Drive API copy request failed: ' . ($documentData['error']['message'] ?? 'Unknown error'));
            }

            $documentId = $documentData['id'] ?? null;

            if (!$documentId) {
                throw new ApiException('Failed to get document ID from Drive API copy response');
            }

            // Step 2: Replace variables in the copied document
            if (!empty($variableMappings)) {
                $this->replaceVariablesInDocument($api, $documentId, $variableMappings);
            }

            // Step 3: Set permissions if configured
            $permissions = config('google-docs.default_permissions');
            if ($permissions) {
                $api->setDocumentPermissions($documentId, $permissions);
            }

            $documentUrl = "https://docs.google.com/document/d/{$documentId}/edit";

            static::log("Document created from template successfully", [
                'document_id'  => $documentId,
                'document_url' => $documentUrl,
            ]);

            return [
                'document_id' => $documentId,
                'title'       => $title,
                'url'         => $documentUrl,
                'created_at'  => now()->toISOString(),
            ];

        } catch(\Exception $e) {
            static::log("Failed to create document from template", [
                'template_id' => $templateId,
                'error'       => $e->getMessage(),
            ]);

            throw new ApiException('Failed to create document from template: ' . $e->getMessage());
        }
    }

    /**
     * Replace variables in an existing document using batchUpdate
     */
    public function replaceVariablesInDocument(GoogleDocsApi $api, string $documentId, array $variableMappings): void
    {
        try {
            $plainTextMappings = [];
            $markdownMappings = [];

            $formattingService = app(GoogleDocsFormattingService::class);

            // Separate markdown values from plain text values
            foreach($variableMappings as $variable => $value) {
                $textValue = is_array($value) ? json_encode($value) : (string)$value;

                // Check if value contains markdown syntax
                if ($formattingService->containsMarkdown($textValue)) {
                    $markdownMappings[$variable] = $textValue;
                } else {
                    $plainTextMappings[$variable] = $textValue;
                }
            }

            // Process plain text values with simple replaceAllText
            if (!empty($plainTextMappings)) {
                $formattingService->replaceVariablesWithPlainText($api, $documentId, $plainTextMappings);
            }

            // Process markdown values with formatting
            if (!empty($markdownMappings)) {
                foreach($markdownMappings as $variable => $markdownValue) {
                    $formattingService->replaceVariableWithFormattedMarkdown($api, $documentId, $variable, $markdownValue);
                }
            }

        } catch(\Exception $e) {
            static::log("Failed to replace variables in document", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
