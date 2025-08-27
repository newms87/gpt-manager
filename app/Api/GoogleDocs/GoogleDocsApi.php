<?php

namespace App\Api\GoogleDocs;

use App\Services\Auth\OAuthService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Api\Api;
use Newms87\Danx\Exceptions\ApiException;

class GoogleDocsApi extends Api
{
    use HasDebugLogging;

    public static string $serviceName = 'Google Docs';

    protected array $rateLimits = [
        // Google Docs API: 100 requests per 100 seconds per user
        ['limit' => 100, 'interval' => 100, 'waitPerAttempt' => 1],
    ];

    protected ?string $accessToken   = null;
    protected bool    $isInitialized = false;

    public function __construct()
    {
        // Only static configuration here
    }

    public function getBaseApiUrl(): string
    {
        return config('google-docs.api_url', 'https://docs.googleapis.com/v1');
    }

    public function getRequestHeaders(): array
    {
        if (!$this->isInitialized) {
            $this->initializeAuthentication();
            $this->isInitialized = true;
        }

        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->accessToken,
        ];
    }

    /**
     * Read full document content from Google Docs
     */
    public function readDocument(string $documentId): array
    {
        try {
            Log::info("GoogleDocsApi: Reading document", ['document_id' => $documentId]);

            $response = $this->get("documents/{$documentId}");
            $document = $response->json();

            if (!$document) {
                throw new ApiException("Invalid response from Google Docs API");
            }

            $content = $this->extractTextContent($document);

            Log::info("GoogleDocsApi: Document read successfully", [
                'document_id'    => $documentId,
                'content_length' => strlen($content),
            ]);

            return [
                'document_id' => $documentId,
                'title'       => $document['title'] ?? 'Untitled',
                'content'     => $content,
                'revision_id' => $document['revisionId'] ?? null,
            ];

        } catch(\Exception $e) {
            Log::error("GoogleDocsApi: Failed to read document", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);

            throw new ApiException('Failed to read Google Docs document: ' . $e->getMessage());
        }
    }

    /**
     * Extract plain text content from Google Docs document structure
     */
    protected function extractTextContent(array $document): string
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
     * Parse template variables from content using regex
     */
    public function parseTemplateVariables(string $content): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);

        $variables = array_unique($matches[1] ?? []);

        Log::info("GoogleDocsApi: Parsed template variables", [
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

        Log::info("GoogleDocsApi: Variables replaced", [
            'mappings_count' => count($mappings),
            'content_length' => strlen($content),
        ]);

        return $content;
    }

    /**
     * Create a new Google Docs document with specified content
     */
    public function createDocument(string $title, string $content, ?string $parentFolderId = null): array
    {
        try {
            Log::info("GoogleDocsApi: Creating document", [
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

            $response = $this->postToDriveApi('files', $driveMetadata);

            $documentData = $response->json();

            // Log the response to debug
            Log::info("GoogleDocsApi: Drive API response", [
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
                $this->insertContentIntoDocument($documentId, $content);
            }

            // Step 3: Set permissions if configured
            $permissions = config('google-docs.default_permissions');
            if ($permissions) {
                $this->setDocumentPermissions($documentId, $permissions);
            }

            $documentUrl = "https://docs.google.com/document/d/{$documentId}/edit";

            Log::info("GoogleDocsApi: Document created successfully", [
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
            Log::error("GoogleDocsApi: Failed to create document", [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException('Failed to create Google Docs document: ' . $e->getMessage());
        }
    }

    /**
     * Insert content into an existing document
     */
    protected function insertContentIntoDocument(string $documentId, string $content): void
    {
        try {
            $requests = [];

            // Split content into paragraphs and build requests
            $paragraphs = explode("\n", $content);

            foreach($paragraphs as $paragraph) {
                if (trim($paragraph) !== '') {
                    $requests[] = [
                        'insertText' => [
                            'location' => ['index' => 1],
                            'text'     => $paragraph . "\n",
                        ],
                    ];
                }
            }

            if (!empty($requests)) {
                $this->post("documents/{$documentId}:batchUpdate", [
                    'requests' => $requests,
                ]);
            }

        } catch(\Exception $e) {
            Log::warning("GoogleDocsApi: Failed to insert content", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Move document to specified folder using Drive API
     */
    protected function moveDocumentToFolder(string $documentId, string $folderId): void
    {
        try {
            // This would need to use Drive API which has a different base URL
            // For now, just log that we would do this
            Log::info("GoogleDocsApi: Would move document to folder", [
                'document_id' => $documentId,
                'folder_id'   => $folderId,
            ]);

        } catch(\Exception $e) {
            Log::warning("GoogleDocsApi: Failed to move document to folder", [
                'document_id' => $documentId,
                'folder_id'   => $folderId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set default permissions for the document using Drive API
     */
    protected function setDocumentPermissions(string $documentId, array $permissions = null): void
    {
        try {
            $permissions = $permissions ?: config('google-docs.default_permissions');

            if ($permissions && isset($permissions['type']) && isset($permissions['role'])) {
                // This would need to use Drive API which has a different base URL
                // For now, just log that we would do this
                Log::info("GoogleDocsApi: Would set document permissions", [
                    'document_id' => $documentId,
                    'permissions' => $permissions,
                ]);
            }

        } catch(\Exception $e) {
            Log::warning("GoogleDocsApi: Failed to set document permissions", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create document from template by copying the template and replacing variables
     */
    public function createDocumentFromTemplate(string $templateId, array $variableMappings, ?string $newTitle = null, ?string $parentFolderId = null): array
    {
        try {
            // Generate title if not provided
            $title = $newTitle ?? ('Document - ' . now()->format('Y-m-d H:i:s'));

            Log::info("GoogleDocsApi: Creating document from template", [
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

            $response = $this->postToDriveApi("files/{$templateId}/copy", $copyMetadata);

            $documentData = $response->json();

            // Log the response to debug
            Log::info("GoogleDocsApi: Drive API copy response", [
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
                $this->replaceVariablesInDocument($documentId, $variableMappings);
            }

            // Step 3: Set permissions if configured
            $permissions = config('google-docs.default_permissions');
            if ($permissions) {
                $this->setDocumentPermissions($documentId, $permissions);
            }

            $documentUrl = "https://docs.google.com/document/d/{$documentId}/edit";

            Log::info("GoogleDocsApi: Document created from template successfully", [
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
            Log::error("GoogleDocsApi: Failed to create document from template", [
                'template_id' => $templateId,
                'error'       => $e->getMessage(),
            ]);

            throw new ApiException('Failed to create document from template: ' . $e->getMessage());
        }
    }

    /**
     * Replace variables in an existing document using batchUpdate
     */
    protected function replaceVariablesInDocument(string $documentId, array $variableMappings): void
    {
        try {
            $requests = [];

            // Create a replaceAllText request for each variable
            foreach($variableMappings as $variable => $value) {
                // Convert value to string
                $textValue = is_array($value) ? json_encode($value) : (string)$value;

                // Always create replace request, even for empty values
                // Empty strings should replace the template variable with nothing
                $requests[] = [
                    'replaceAllText' => [
                        'containsText' => [
                            'text'      => '{{' . $variable . '}}',
                            'matchCase' => true,
                        ],
                        'replaceText'  => $textValue,
                    ],
                ];
            }

            if (!empty($requests)) {
                $response = $this->post("documents/{$documentId}:batchUpdate", [
                    'requests' => $requests,
                ]);

                $responseData = $response->json();
                if (isset($responseData['error'])) {
                    throw new ApiException('Failed to replace variables: ' . ($responseData['error']['message'] ?? 'Unknown error'));
                }

                Log::info("GoogleDocsApi: Variables replaced in document", [
                    'document_id'        => $documentId,
                    'variables_replaced' => count($requests),
                ]);
            }

        } catch(\Exception $e) {
            Log::warning("GoogleDocsApi: Failed to replace variables in document", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract template variables directly from a Google Docs template
     */
    public function extractTemplateVariables(string $templateId): array
    {
        $templateData = $this->readDocument($templateId);

        return $this->parseTemplateVariables($templateData['content']);
    }

    /**
     * Initialize authentication (OAuth only)
     */
    protected function initializeAuthentication(): void
    {
        if (!$this->initializeOAuth()) {
            throw new ApiException('No valid OAuth token found. Please authorize Google Docs access first.');
        }
    }

    /**
     * Try to initialize OAuth authentication
     */
    protected function initializeOAuth(): bool
    {
        try {
            $oauthService = app(OAuthService::class);
            $token        = $oauthService->getToken('google');

            if (!$token) {
                return false;
            }

            // getToken() should have already refreshed the token if needed
            // but double-check validity
            if (!$token->isValid()) {
                return false;
            }

            $this->accessToken = $token->access_token;

            Log::info("GoogleDocsApi: OAuth authentication initialized", [
                'team_id'    => $token->team_id,
                'expires_at' => $token->expires_at?->toISOString(),
            ]);

            return true;

        } catch(\Exception $e) {
            Log::warning("GoogleDocsApi: Failed to initialize OAuth authentication", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }


    /**
     * Make a POST request to Google Drive API
     */
    protected function postToDriveApi(string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        $driveApiUrl = 'https://www.googleapis.com/drive/v3/';

        return Http::withHeaders($this->getRequestHeaders())
            ->post($driveApiUrl . $endpoint, $data);
    }


    /**
     * Check if OAuth authentication is available for current team
     */
    public function hasOAuthToken(): bool
    {
        try {
            $oauthService = app(OAuthService::class);
            $token        = $oauthService->getToken('google');

            return $token && $token->isValid();
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Force re-initialization of authentication (useful after OAuth changes)
     */
    public function reinitializeAuth(): void
    {
        $this->isInitialized = false;
        $this->accessToken   = null;
    }
}
