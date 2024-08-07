<?php

namespace App\AiTools;

use App\AiTools\Traits\HasOutputImagesTrait;
use App\Api\ScreenshotOne\ScreenshotOneApi;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

class UrlToImageAiTool implements AiToolContract
{
    const string NAME        = 'url-to-image';
    const string DESCRIPTION = 'Convert a URL into a list of images that shows the full web page or PDF. Use the images to answer questions about a URL';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'url' => [
                'type'        => 'string',
                'description' => 'The URL to convert to images. Can be a PDF or HTML website.',
            ],
        ],
        'required'   => ['url'],
    ];

    public function execute($params): AiToolResponse
    {
        $url = $params['url'] ?? null;

        Log::debug("Executing URL to Screenshot AI Tool: $url");

        if (!$url) {
            throw new BadFunctionCallException("URL to Screenshot requires a URL");
        }

        if (FileHelper::isPdf($url)) {
            $storedFile = $this->convertPdfToImages($url);
        } else {
            $storedFile = $this->takeScreenshot($url);
        }

        $transcodes = $storedFile->transcodes()->get();

        $fileCount = $transcodes->count();
        $response  = new AiToolResponse();

        $response->addContent(
            "Screenshot for file ID $storedFile->id is provided below." .
            ($fileCount > 1 ? " There are $fileCount separate images w/ the file ID in the path." : '')
        );

        if ($fileCount > 1) {
            foreach($transcodes as $transcode) {
                $response->addStoredFile($transcode);
            }
        } else {
            $response->addStoredFile($storedFile);
        }

        return $response;
    }

    public function convertPdfToImages($pdfUrl): StoredFile
    {
        Log::debug("Converting PDF to images: $pdfUrl");

        $storedFile = StoredFile::firstWhere('url', $pdfUrl);

        if (!$storedFile) {
            $storedFile = StoredFile::create([
                'disk'     => 'www',
                'filename' => basename($pdfUrl),
                'filepath' => $pdfUrl,
                'mime'     => StoredFile::MIME_PDF,
                'url'      => $pdfUrl,
                'size'     => FileHelper::getRemoteFileSize($pdfUrl),
            ]);
        }

        app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        return $storedFile;
    }

    public function takeScreenshot($url): StoredFile
    {
        $url = rtrim(trim($url), '/?\\#&');
        $url = preg_replace('/#.*/', '', $url);

        Log::debug("Taking screenshot: $url");

        // Check for a previously cached web stored file, which might have a screenshot already
        $storedWebFile = StoredFile::where('disk', 'web')->where('url', $url)->first();

        // Create the HTML stored file for future reference
        if (!$storedWebFile) {
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
        $storedImageFile = $storedWebFile->transcodes()->where('transcode_name', UrlToImageAiTool::NAME)->first();

        if ($storedImageFile) {
            Log::debug("Found existing Url to Image transcode: $storedImageFile->id");
        } else {
            Log::debug("Taking new screenshot");

            $filepath  = "url-to-screenshot/" . md5($url) . ".jpg";
            $storedUrl = ScreenshotOneApi::make()->take($url, $filepath);

            // Store the screenshot and associate it with the web page file so it is cached in the DB for future uses
            $storedImageFile = StoredFile::create([
                'disk'                    => 's3',
                'filename'                => basename($filepath),
                'filepath'                => $filepath,
                'mime'                    => StoredFile::MIME_JPEG,
                'url'                     => $storedUrl,
                'size'                    => FileHelper::getRemoteFileSize($storedUrl),
                'original_stored_file_id' => $storedWebFile->id,
                'transcode_name'          => UrlToImageAiTool::NAME,
            ]);
        }

        // Transcode the image into vertical chunks for easier processing (will only transcode once if not already done)
        app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_IMAGE_TO_VERTICAL_CHUNKS, $storedImageFile);

        // Return the Image file (not the web file) as this is asset of interest
        return $storedImageFile;
    }
}
