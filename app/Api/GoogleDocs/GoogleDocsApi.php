<?php

namespace App\Api\GoogleDocs;

use App\Exceptions\Auth\NoTokenFoundException;
use App\Exceptions\Auth\TokenExpiredException;
use App\Exceptions\Auth\TokenRevokedException;
use App\Services\Auth\OAuthService;
use App\Services\GoogleDocs\GoogleDocsContentService;
use App\Services\GoogleDocs\GoogleDocsFormattingService;
use App\Services\GoogleDocs\GoogleDocsTemplateService;
use App\Traits\HasDebugLogging;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
            static::log("Reading document", ['document_id' => $documentId]);

            $response = $this->get("documents/{$documentId}");
            $document = $response->json();

            if (!$document) {
                throw new ApiException("Invalid response from Google Docs API");
            }

            $content = app(GoogleDocsContentService::class)->extractTextContent($document);

            static::log("Document read successfully", [
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
            static::log("Failed to read document", [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);

            throw new ApiException('Failed to read Google Docs document: ' . $e->getMessage());
        }
    }

    /**
     * Extract plain text content from Google Docs document structure
     * @deprecated Use GoogleDocsContentService::extractTextContent() directly
     */
    protected function extractTextContent(array $document): string
    {
        return app(GoogleDocsContentService::class)->extractTextContent($document);
    }

    /**
     * Parse template variables from content using regex
     */
    public function parseTemplateVariables(string $content): array
    {
        return app(GoogleDocsTemplateService::class)->parseTemplateVariables($content);
    }

    /**
     * Replace template variables in content with provided data
     */
    public function replaceVariables(string $content, array $mappings): string
    {
        return app(GoogleDocsTemplateService::class)->replaceVariables($content, $mappings);
    }

    /**
     * Create a new Google Docs document with specified content
     */
    public function createDocument(string $title, string $content, ?string $parentFolderId = null): array
    {
        return app(GoogleDocsContentService::class)->createDocument($this, $title, $content, $parentFolderId);
    }

    /**
     * Insert content into an existing document
     * @deprecated Use GoogleDocsContentService::insertContentIntoDocument() directly
     */
    protected function insertContentIntoDocument(string $documentId, string $content): void
    {
        app(GoogleDocsContentService::class)->insertContentIntoDocument($this, $documentId, $content);
    }


    /**
     * Set default permissions for the document using Drive API
     */
    public function setDocumentPermissions(string $documentId, array $permissions = null): void
    {
        try {
            $permissions = $permissions ?: config('google-docs.default_permissions');

            if ($permissions && isset($permissions['type']) && isset($permissions['role'])) {
                // This would need to use Drive API which has a different base URL
                // For now, just log that we would do this
                static::log("Would set document permissions", [
                    'document_id' => $documentId,
                    'permissions' => $permissions,
                ]);
            }

        } catch(\Exception $e) {
            static::log("Failed to set document permissions", [
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
        return app(GoogleDocsTemplateService::class)->createDocumentFromTemplate($this, $templateId, $variableMappings, $newTitle, $parentFolderId);
    }

    /**
     * Replace variables in an existing document using batchUpdate
     * @deprecated Use GoogleDocsTemplateService::replaceVariablesInDocument() directly
     */
    protected function replaceVariablesInDocument(string $documentId, array $variableMappings): void
    {
        app(GoogleDocsTemplateService::class)->replaceVariablesInDocument($this, $documentId, $variableMappings);
    }

    /**
     * Check if a string contains markdown syntax
     * @deprecated Use GoogleDocsFormattingService::containsMarkdown() directly
     */
    protected function containsMarkdown(string $text): bool
    {
        return app(GoogleDocsFormattingService::class)->containsMarkdown($text);
    }

    /**
     * Replace variables with plain text (no formatting)
     * @deprecated Use GoogleDocsFormattingService::replaceVariablesWithPlainText() directly
     */
    protected function replaceVariablesWithPlainText(string $documentId, array $variableMappings): void
    {
        app(GoogleDocsFormattingService::class)->replaceVariablesWithPlainText($this, $documentId, $variableMappings);
    }

    /**
     * Replace a variable with formatted markdown content
     * @deprecated Use GoogleDocsFormattingService::replaceVariableWithFormattedMarkdown() directly
     */
    protected function replaceVariableWithFormattedMarkdown(string $documentId, string $variable, string $markdownValue): void
    {
        app(GoogleDocsFormattingService::class)->replaceVariableWithFormattedMarkdown($this, $documentId, $variable, $markdownValue);
    }

    /**
     * Parse markdown text and return plain text with formatting instructions
     * @deprecated Use GoogleDocsFormattingService::parseMarkdown() directly
     */
    protected function parseMarkdown(string $markdown): array
    {
        return app(GoogleDocsFormattingService::class)->parseMarkdown($markdown);
    }

    /**
     * Parse inline formatting (bold, italic) from a line
     * @deprecated Use GoogleDocsFormattingService::parseInlineFormatting() directly
     */
    protected function parseInlineFormatting(string $line, int $lineStart, array &$formats): string
    {
        return app(GoogleDocsFormattingService::class)->parseInlineFormatting($line, $lineStart, $formats);
    }

    /**
     * Find the position (index) of text in the document
     * @deprecated Use GoogleDocsContentService::findTextPosition() directly
     */
    protected function findTextPosition(array $document, string $searchText): ?int
    {
        return app(GoogleDocsContentService::class)->findTextPosition($document, $searchText);
    }

    /**
     * Apply formatting instructions to text in document
     * @deprecated Use GoogleDocsFormattingService::applyFormattingToText() directly
     */
    protected function applyFormattingToText(string $documentId, int $baseIndex, array $formats): void
    {
        app(GoogleDocsFormattingService::class)->applyFormattingToText($this, $documentId, $baseIndex, $formats);
    }

    /**
     * Extract template variables directly from a Google Docs template
     */
    public function extractTemplateVariables(string $templateId): array
    {
        return app(GoogleDocsTemplateService::class)->extractTemplateVariables($this, $templateId);
    }

    /**
     * Initialize authentication (OAuth only)
     */
    protected function initializeAuthentication(): void
    {
        try {
            $oauthService      = app(OAuthService::class);
            $token             = $oauthService->getValidToken('google');
            $this->accessToken = $token->access_token;

            static::log("OAuth authentication initialized", [
                'team_id'    => $token->team_id,
                'expires_at' => $token->expires_at?->toISOString(),
            ]);
        } catch(TokenRevokedException|TokenExpiredException $e) {
            static::log("OAuth token issue", [
                'service'    => $e->getService(),
                'team_id'    => $e->getTeamId(),
                'error_type' => get_class($e),
                'reason'     => method_exists($e, 'getRevokeReason') ? $e->getRevokeReason() : ($e->getExpiresAt() ?? 'unknown'),
            ]);
            throw new ApiException($e->getMessage(), $e->getCode());
        } catch(NoTokenFoundException $e) {
            static::log("No OAuth token found", [
                'service' => $e->getService(),
                'team_id' => $e->getTeamId(),
            ]);
            throw new ApiException($e->getMessage(), $e->getCode());
        } catch(\Exception $e) {
            static::log("Failed to initialize OAuth authentication", [
                'error' => $e->getMessage(),
            ]);
            throw new ApiException('Failed to initialize Google Docs authentication: ' . $e->getMessage());
        }
    }

    /**
     * Make a POST request to Google Drive API
     */
    public function postToDriveApi(string $endpoint, array $data = []): Response
    {
        $driveApiUrl = 'https://www.googleapis.com/drive/v3/';

        return Http::withHeaders($this->getRequestHeaders())
            ->post($driveApiUrl . $endpoint, $data);
    }


    /**
     * Validate that the current OAuth token is valid by making a test API call
     */
    public function validateToken(): bool
    {
        try {
            // Make a lightweight API call to Google Drive API to verify token
            // Using the /about endpoint with minimal fields
            $response = Http::withHeaders($this->getRequestHeaders())
                ->get('https://www.googleapis.com/drive/v3/about?fields=user/emailAddress');

            if ($response->successful()) {
                static::log("OAuth token validated successfully", [
                    'status' => $response->status(),
                ]);
                return true;
            }

            static::log("OAuth token validation failed", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;

        } catch(\Exception $e) {
            static::log("OAuth token validation error", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
