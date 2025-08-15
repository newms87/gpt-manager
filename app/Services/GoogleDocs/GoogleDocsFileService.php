<?php

namespace App\Services\GoogleDocs;

use Newms87\Danx\Models\Utilities\StoredFile;

class GoogleDocsFileService
{
    /**
     * Create a StoredFile from a Google Docs URL
     */
    public function createFromUrl(string $url, string $name): string
    {
        $storedFile = StoredFile::make()->forceFill([
            'team_id'  => team()->id,
            'user_id'  => user()->id,
            'disk'     => 'external',
            'filepath' => $url,
            'filename' => "{$name}.gdoc",
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
            'url'      => $url,
            'meta'     => [
                'type' => 'google_docs_template',
            ],
        ]);
        $storedFile->save();

        return $storedFile->id;
    }

    /**
     * Extract Google Doc ID from URL
     */
    public function extractDocumentId(string $url): ?string
    {
        // Extract Google Doc ID from URL
        if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // If URL is already just the ID
        if (preg_match('/^[a-zA-Z0-9_-]{25,60}$/', $url)) {
            return $url;
        }

        return null;
    }
}