<?php

namespace App\AiTools;

use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;

class UrlToMarkdownAiTool implements AiToolContract
{
    const string NAME        = 'url-to-markdown';
    const string DESCRIPTION = 'Convert a URL into markdown. Use the markdown formatted text to answer questions about a URL';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'url' => [
                'type'        => 'string',
                'description' => 'The URL to convert to markdown. Only works on XML / HTML websites.',
            ],
        ],
        'required'   => ['url'],
    ];

    public function execute($params): AiToolResponse
    {
        $url = $params['url'] ?? null;

        Log::debug("Executing URL to Markdown AI Tool: $url");

        if (!$url) {
            throw new BadFunctionCallException("URL to Markdown requires a URL");
        }

        $storedFile = $this->convertToMarkdownFile($url);

        $response = new AiToolResponse();

        $contents         = $storedFile->getContents();
        $contentLength    = strlen($contents);
        $maxContentLength = 50000;
        $response->addContent(
            "Markdown for URL $storedFile->filepath is provided below:\n\n" .
            substr($contents, 0, $maxContentLength) .
            (($contentLength > $maxContentLength ? "... \n\n\n$contentLength bytes in total. This page was too long to view via markdown." : ''))
        );

        return $response;
    }

    public function convertToMarkdownFile($url): StoredFile
    {
        $url = FileHelper::normalizeUrl($url);

        // Check for a previously cached web stored file, which might have a screenshot already
        $storedWebFile = StoredFile::where('disk', 'web')->where('url', $url)->first();

        // Create the HTML stored file for future reference
        if (!$storedWebFile) {
            Log::debug("Storing web file: $url");

            $storedWebFile = StoredFile::create([
                'disk'     => 'web',
                'filename' => basename($url),
                'filepath' => $url,
                'mime'     => StoredFile::MIME_HTML,
                'url'      => $url,
                'size'     => 0,
            ]);
        }

        // Check for the screenshot of this web page
        $storedMarkdownFile = $storedWebFile->transcodes()->where('transcode_name', UrlToMarkdownAiTool::NAME)->first();

        if ($storedMarkdownFile) {
            Log::debug("Found existing Url to Markdown transcode: $storedMarkdownFile->id");
        } else {
            Log::debug("Converting to markdown...");

            $markdown = FileHelper::htmlToMarkdown($url);

            // Save the markdown contents to a publicly accessible storage location
            $filepath = "url-to-markdown/" . md5($url) . ".jpg";
            app(FileRepository::class)->storeOnDisk($filepath, $markdown, 's3');

            // Store the screenshot and associate it with the web page file so it is cached in the DB for future uses
            $storedMarkdownFile = StoredFile::create([
                'disk'                    => 's3',
                'filename'                => basename($filepath),
                'filepath'                => $filepath,
                'mime'                    => StoredFile::MIME_TEXT,
                'url'                     => Storage::disk('s3')->url($filepath),
                'size'                    => strlen($markdown),
                'original_stored_file_id' => $storedWebFile->id,
                'transcode_name'          => UrlToMarkdownAiTool::NAME,
            ]);
        }

        // Return the Image file (not the web file) as this is asset of interest
        return $storedMarkdownFile;
    }
}
