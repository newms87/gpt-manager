<?php

namespace App\AiTools;

use App\Api\ScreenshotOne\ScreenshotOneApi;
use BadFunctionCallException;
use Flytedan\DanxLaravel\Helpers\FileHelper;
use Flytedan\DanxLaravel\Models\Utilities\StoredFile;
use Illuminate\Support\Facades\Storage;

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

        if ($storedFile) {
            return $storedFile;
        }

        $storedUrl = ScreenshotOneApi::make()->take($url, $filepath);

        $size = Storage::disk('s3')->size($filepath);

        return StoredFile::create([
            'disk'     => 's3',
            'name'     => 'UrlToScreenshot ' . now()->toDateTimeString(),
            'filepath' => $filepath,
            'mime'     => FileHelper::getMimeFromExtension($filepath),
            'url'      => $storedUrl,
            'size'     => $size,
        ]);
    }
}
