<?php

namespace App\AiTools\UrlToImage;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Api\ScreenshotOne\ScreenshotOneApi;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Newms87\Danx\Services\TranscodeFileService;
use Throwable;

class UrlToImageAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'url-to-image';

    public function execute($params): AiToolResponse
    {
        $url = $params['url'] ?? null;

        Log::debug("Executing URL to Image AI Tool: $url");

        if (!$url) {
            throw new BadFunctionCallException("URL to Image requires a URL");
        }

        $response = new AiToolResponse();

        try {
            if (FileHelper::isPdf($url)) {
                $storedFile = $this->convertPdfToImages($url);
            } else {
                $storedFile = $this->takeScreenshot($url);
            }

            $transcodes = $storedFile->transcodes()->get();
            $fileCount  = $transcodes->count();

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
        } catch(Throwable $throwable) {
            Log::error("Error converting URL to Image: $url: " . $throwable->getMessage(), ['exception' => $throwable]);

            $response->addContent("Error converting URL to Image: $url. Try another URL");
        }


        return $response;
    }

    public function convertPdfToImages($pdfUrl): StoredFile
    {
        Log::debug("Converting PDF to images: $pdfUrl");

        $storedFile = StoredFile::firstWhere('url', $pdfUrl);

        if (!$storedFile) {
            $storedFile = app(FileRepository::class)->createFileWithUrl(
                $pdfUrl,
                $pdfUrl,
                [
                    'disk' => 'web',
                    'mime' => StoredFile::MIME_PDF,
                    'size' => FileHelper::getRemoteFileSize($pdfUrl),
                ]
            );
        }

        app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        return $storedFile;
    }

    public function takeScreenshot($url): StoredFile
    {
        $url = FileHelper::normalizeUrl($url);

        Log::debug("Taking screenshot: $url");

        // Check for a previously cached web stored file, which might have a screenshot already
        $storedWebFile = StoredFile::where('disk', 'web')->where('url', $url)->first();

        // Create the HTML stored file for future reference
        if (!$storedWebFile) {
            $storedWebFile = app(FileRepository::class)->createFileWithUrl($url, $url, ['disk' => 'web', 'mime' => StoredFile::MIME_HTML]);
        }

        // Check for the screenshot of this web page
        $storedImageFile = $storedWebFile->transcodes()->where('transcode_name', UrlToImageAiTool::$name)->first();

        if ($storedImageFile) {
            Log::debug("Found existing Url to Image transcode: $storedImageFile->id");
        } else {
            Log::debug("Taking new screenshot");

            $filepath  = "url-to-image/" . md5($url) . ".jpg";
            $storedUrl = ScreenshotOneApi::make()->take($url, $filepath);

            // Store the screenshot and associate it with the web page file so it is cached in the DB for future uses
            $storedImageFile = app(FileRepository::class)->createFileWithUrl(
                $filepath,
                $storedUrl,
                [
                    'disk'                    => 's3',
                    'mime'                    => StoredFile::MIME_JPEG,
                    'size'                    => FileHelper::getRemoteFileSize($storedUrl),
                    'original_stored_file_id' => $storedWebFile->id,
                    'transcode_name'          => UrlToImageAiTool::$name,
                ]);
        }

        // Transcode the image into vertical chunks for easier processing (will only transcode once if not already done)
        app(TranscodeFileService::class)->transcode(TranscodeFileService::TRANSCODE_IMAGE_TO_VERTICAL_CHUNKS, $storedImageFile);

        // Return the Image file (not the web file) as this is asset of interest
        return $storedImageFile;
    }
}
