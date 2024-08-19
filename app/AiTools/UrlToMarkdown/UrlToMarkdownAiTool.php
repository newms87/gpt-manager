<?php

namespace App\AiTools\UrlToMarkdown;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Throwable;

class UrlToMarkdownAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'url-to-markdown';

    public function execute($params): AiToolResponse
    {
        $url = $params['url'] ?? null;

        Log::debug("Executing URL to Markdown AI Tool: $url");

        if (!$url) {
            throw new BadFunctionCallException("URL to Markdown requires a URL");
        }

        $response = new AiToolResponse();

        try {
            $storedFile = $this->convertToMarkdownFile($url);

            $contents         = $storedFile->getContents();
            $contentLength    = strlen($contents);
            $maxContentLength = 20000;
            $response->addContent(
                "Markdown for URL $storedFile->filepath is provided below:\n\n" .
                substr($contents, 0, $maxContentLength) .
                (($contentLength > $maxContentLength ? "... \n\n\n$contentLength bytes in total. This page was too long to view via markdown." : ''))
            );
        } catch(Throwable $e) {
            Log::error("Error converting URL to Markdown: $url: " . $e->getMessage(), ['exception' => $e]);

            $response->addContent("Error converting URL to Markdown: $url. Try another URL");
        }


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

            $storedWebFile = app(FileRepository::class)->createFileWithUrl(
                $url,
                $url,
                [
                    'disk' => 'web',
                    'mime' => StoredFile::MIME_HTML,
                ]);
        }

        // Check for the screenshot of this web page
        $storedMarkdownFile = $storedWebFile->transcodes()->where('transcode_name', UrlToMarkdownAiTool::$name)->first();

        if ($storedMarkdownFile) {
            Log::debug("Found existing Url to Markdown transcode: $storedMarkdownFile->id");
        } else {
            Log::debug("Converting to markdown...");

            $markdown = FileHelper::htmlToMarkdown($url);

            // Save the markdown contents to a publicly accessible storage location
            $filepath = "url-to-markdown/" . md5($url) . ".md";

            // Store the screenshot and associate it with the web page file so it is cached in the DB for future uses
            $storedMarkdownFile = app(FileRepository::class)->createFileWithContents(
                $filepath,
                $markdown,
                [
                    'disk'                    => 's3',
                    'mime'                    => StoredFile::MIME_TEXT,
                    'original_stored_file_id' => $storedWebFile->id,
                    'transcode_name'          => UrlToMarkdownAiTool::$name,
                ]
            );
        }

        // Return the Image file (not the web file) as this is asset of interest
        return $storedMarkdownFile;
    }
}
