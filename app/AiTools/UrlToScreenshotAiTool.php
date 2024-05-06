<?php

namespace App\AiTools;

use App\Api\ScreenshotOne\ScreenshotOneApi;
use BadFunctionCallException;
use Flytedan\DanxLaravel\Helpers\FileHelper;
use Flytedan\DanxLaravel\Models\Utilities\StoredFile;

class UrlToScreenshotAiTool implements AiToolContract
{
    const string NAME        = 'url-to-screenshot';
    const string DESCRIPTION = 'Convert a URL into a screenshot that shows the full page. Use the screenshot to answer questions about a URL';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'url' => [
                'type'        => 'string',
                'description' => 'The URL to take screenshot of',
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

        $filepath   = "url-to-screenshot/" . md5($url) . ".jpg";
        $storedFile = StoredFile::firstWhere('filepath', $filepath);

        if (!$storedFile) {
            $storedUrl = ScreenshotOneApi::make()->take($url, $filepath);

            $storedFile = StoredFile::create([
                'disk'     => 's3',
                'name'     => 'UrlToScreenshot ' . now()->toDateTimeString(),
                'filename' => basename($filepath),
                'filepath' => $filepath,
                'mime'     => FileHelper::getMimeFromExtension($filepath),
                'url'      => $storedUrl,
                'size'     => 0,
            ]);
        }


        return [
            [
                'type' => 'text',
                'text' => 'Analyze the screenshot to answer the question.',
            ],
            [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => $storedFile->url,
                ],
            ],
        ];
    }
}
