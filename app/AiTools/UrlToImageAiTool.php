<?php

namespace App\AiTools;

use App\Api\ScreenshotOne\ScreenshotOneApi;
use BadFunctionCallException;
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

    public function execute($params)
    {
        $url = $params['url'] ?? null;

        if (!$url) {
            throw new BadFunctionCallException("URL to Screenshot requires a URL");
        }

        if (FileHelper::isPdf($url)) {
            $storedFile = $this->convertPdfToImages($url);
        } else {
            $storedFile = $this->takeScreenshot($url);
        }

        $transcodes = $storedFile->transcodes()->get();

        $response = [
            [
                'type' => 'text',
                'text' => 'Analyze the screenshot to answer the question.',
            ],
        ];

        foreach($transcodes as $transcode) {
            $response[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => $transcode->url,
                    'detail' => 'high',
                ],
            ];
        }

        return $response;
    }

    public function convertPdfToImages($pdfUrl)
    {
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

        app(TranscodeFileService::class)->pdfToImages($storedFile);

        return $storedFile;
    }

    public function takeScreenshot($url)
    {
        $filepath   = "url-to-screenshot/" . md5($url) . ".jpg";
        $storedFile = StoredFile::firstWhere('filepath', $filepath);

        if (!$storedFile) {
            $storedUrl = ScreenshotOneApi::make()->take($url, $filepath);

            $storedFile = StoredFile::create([
                'disk'     => 's3',
                'filename' => basename($filepath),
                'filepath' => $filepath,
                'mime'     => FileHelper::getMimeFromExtension($filepath),
                'url'      => $storedUrl,
                'size'     => FileHelper::getRemoteFileSize($storedUrl),
            ]);
        }

        app(TranscodeFileService::class)->imageToVerticalChunks($storedFile);

        return $storedFile;
    }
}
