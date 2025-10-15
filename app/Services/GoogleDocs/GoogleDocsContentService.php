<?php

namespace App\Services\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ApiException;

class GoogleDocsContentService
{
    use HasDebugLogging;

    /**
     * Extract plain text content from Google Docs document structure
     */
    public function extractTextContent(array $document): string
    {
        $content = '';
        $body    = $document['body'] ?? null;

        if ($body && isset($body['content']) && is_array($body['content'])) {
            foreach($body['content'] as $element) {
                if (isset($element['paragraph'])) {
                    $paragraph = $element['paragraph'];
                    if (isset($paragraph['elements']) && is_array($paragraph['elements'])) {
                        foreach($paragraph['elements'] as $paragraphElement) {
                            if (isset($paragraphElement['textRun']['content'])) {
                                $content .= $paragraphElement['textRun']['content'];
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Find the position (index) of text in the document
     */
    public function findTextPosition(array $document, string $searchText): ?int
    {
        $body = $document['body'] ?? null;
        if (!$body || !isset($body['content'])) {
            static::log("findTextPosition: No body content found");
            return null;
        }

        // Search through document content
        $currentIndex = 1; // Google Docs uses 1-based indexing
        $searchStart = substr($searchText, 0, 50);

        static::log("findTextPosition: Searching for text", [
            'search_start' => $searchStart,
            'search_length' => strlen($searchText),
        ]);

        foreach($body['content'] as $elementIndex => $element) {
            if (isset($element['paragraph']['elements'])) {
                foreach($element['paragraph']['elements'] as $paragraphElementIndex => $paragraphElement) {
                    if (isset($paragraphElement['textRun']['content'])) {
                        $content = $paragraphElement['textRun']['content'];
                        $startIndex = $paragraphElement['startIndex'] ?? $currentIndex;
                        $endIndex = $paragraphElement['endIndex'] ?? ($startIndex + strlen($content));

                        static::log("findTextPosition: Checking text run", [
                            'element_index' => $elementIndex,
                            'paragraph_element_index' => $paragraphElementIndex,
                            'start_index' => $startIndex,
                            'end_index' => $endIndex,
                            'content_preview' => substr($content, 0, 50),
                            'content_length' => strlen($content),
                        ]);

                        // Check if this text run contains our search text
                        if (strpos($content, $searchStart) !== false) {
                            static::log("findTextPosition: Found matching text", [
                                'start_index' => $startIndex,
                                'found_at_position' => strpos($content, $searchStart),
                            ]);
                            return $startIndex + strpos($content, $searchStart);
                        }

                        $currentIndex = $endIndex;
                    }
                }
            }
        }

        static::log("findTextPosition: Text not found");
        return null;
    }

    /**
     * Insert content into an existing document
     */
    public function insertContentIntoDocument(GoogleDocsApi $api, string $documentId, string $content): void
    {
        try {
            static::log("insertContentIntoDocument: start", [
                'content_length' => strlen($content),
                'has_newlines' => strpos($content, "\n") !== false,
                'has_literal_backslash_n' => strpos($content, '\\' . 'n') !== false,
                'content_preview' => substr($content, 0, 100),
            ]);

            $requests = [];

            // Convert literal \n strings (2 chars: backslash + n) to actual newlines
            $content = str_replace(chr(92) . 'n', "\n", $content);

            // Split content into paragraphs and build requests
            $paragraphs = explode("\n", $content);

            static::log("insertContentIntoDocument: after split", [
                'paragraph_count' => count($paragraphs),
                'paragraphs' => $paragraphs,
            ]);

            foreach($paragraphs as $paragraph) {
                // Insert all paragraphs including empty ones to preserve blank lines
                // Empty paragraphs create blank lines when followed by \n
                $requests[] = [
                    'insertText' => [
                        'location' => ['index' => 1],
                        'text'     => $paragraph . "\n",
                    ],
                ];
            }

            if (!empty($requests)) {
                $api->post("documents/{$documentId}:batchUpdate", [
                    'requests' => $requests,
                ]);
            }

        } catch(\Exception $e) {
            static::log("Failed to insert content", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a new Google Docs document with specified content
     */
    public function createDocument(GoogleDocsApi $api, string $title, string $content, ?string $parentFolderId = null): array
    {
        try {
            static::log("Creating document", [
                'title'            => $title,
                'parent_folder_id' => $parentFolderId,
                'content_length'   => strlen($content),
            ]);

            // Step 1: Create empty Google Docs document using Drive API
            // Note: Google Docs don't count against storage quota, only uploaded files do
            $driveMetadata = [
                'name'     => $title,
                'mimeType' => 'application/vnd.google-apps.document',
            ];

            // Only set parent folder if explicitly provided (not default)
            if ($parentFolderId && $parentFolderId !== config('google-docs.default_folder_id')) {
                $driveMetadata['parents'] = [$parentFolderId];
            }

            $response = $api->postToDriveApi('files', $driveMetadata);

            $documentData = $response->json();

            // Log the response to debug
            static::log("Drive API response", [
                'status'   => $response->status(),
                'response' => $documentData,
            ]);

            if (!$response->successful()) {
                throw new ApiException('Drive API request failed: ' . ($documentData['error']['message'] ?? 'Unknown error'));
            }

            $documentId = $documentData['id'] ?? null;

            if (!$documentId) {
                throw new ApiException('Failed to get document ID from Drive API response');
            }

            // Step 2: Add content to the document if provided
            if (!empty($content)) {
                $this->insertContentIntoDocument($api, $documentId, $content);
            }

            // Step 3: Set permissions if configured
            $permissions = config('google-docs.default_permissions');
            if ($permissions) {
                $api->setDocumentPermissions($documentId, $permissions);
            }

            $documentUrl = "https://docs.google.com/document/d/{$documentId}/edit";

            static::log("Document created successfully", [
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
            static::log("Failed to create document", [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException('Failed to create Google Docs document: ' . $e->getMessage());
        }
    }
}
