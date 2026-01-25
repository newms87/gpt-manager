<?php

namespace App\Services\Template;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Template\TemplateDefinition;
use App\Services\GoogleDocs\GoogleDriveFolderService;
use Newms87\Danx\Traits\HasDebugLogging;
use Exception;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Service responsible for rendering Google Docs templates.
 * Handles document creation from templates via Google Docs API.
 */
class GoogleDocsRenderingService
{
    use HasDebugLogging;

    /**
     * Render a Google Docs template with the provided resolved values
     *
     * @param  TemplateDefinition  $template  The template definition
     * @param  array<string, string>  $resolvedValues  Map of variable names to resolved values
     * @param  string  $title  Title for the generated document
     * @param  int  $teamId  Team ID for ownership
     * @return array{url: string, document_id: string, title: string, created_at: string, stored_file: StoredFile}
     *
     * @throws Exception
     */
    public function render(
        TemplateDefinition $template,
        array $resolvedValues,
        string $title,
        int $teamId,
    ): array {
        // Extract Google Doc ID from template
        $googleDocFileId = $template->extractGoogleDocId();
        if (!$googleDocFileId) {
            throw new Exception(
                "Could not extract Google Doc ID from template URL: {$template->getTemplateUrl()}"
            );
        }

        static::logDebug('Starting Google Docs rendering', [
            'template_id'    => $template->id,
            'google_doc_id'  => $googleDocFileId,
            'title'          => $title,
            'values_count'   => count($resolvedValues),
        ]);

        // Find or create output folder
        $googleDocsApi    = app(GoogleDocsApi::class);
        $outputFolderName = config('google-docs.output_folder_name', 'Output Documents');
        $folderId         = app(GoogleDriveFolderService::class)->findOrCreateFolder(
            $googleDocsApi,
            $outputFolderName
        );

        static::logDebug('Output folder resolved', [
            'folder_name' => $outputFolderName,
            'folder_id'   => $folderId,
        ]);

        // Create document from template (GoogleDocsApi handles markdown formatting)
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId,
            $resolvedValues,
            $title,
            $folderId
        );

        static::logDebug('Document created', [
            'document_id' => $newDocument['document_id'],
            'url'         => $newDocument['url'],
        ]);

        // Create StoredFile for the generated doc
        $storedFile = $this->createStoredFile($newDocument, $teamId);

        return [
            'url'         => $newDocument['url'],
            'document_id' => $newDocument['document_id'],
            'title'       => $newDocument['title'],
            'created_at'  => $newDocument['created_at'],
            'stored_file' => $storedFile,
        ];
    }

    /**
     * Create StoredFile for Google Docs output
     */
    protected function createStoredFile(array $newDocument, int $teamId): StoredFile
    {
        $storedFile = new StoredFile();
        $storedFile->forceFill([
            'team_id'  => $teamId,
            'user_id'  => user()->id ?? null,
            'disk'     => 'external',
            'filepath' => $newDocument['url'],
            'filename' => $newDocument['title'] . '.gdoc',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
            'url'      => $newDocument['url'],
            'meta'     => [
                'type'        => 'google_docs',
                'document_id' => $newDocument['document_id'],
                'created_at'  => $newDocument['created_at'],
            ],
        ]);
        $storedFile->save();

        static::logDebug('Created StoredFile for Google Doc', [
            'stored_file_id' => $storedFile->id,
            'document_id'    => $newDocument['document_id'],
        ]);

        return $storedFile;
    }
}
