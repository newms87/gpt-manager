<?php

namespace App\Api\ImageToText;

use Exception;
use Newms87\Danx\Api\BearerTokenApi;

class ImageToTextOcrApi extends BearerTokenApi
{
    public static string $serviceName = 'Image To Text OCR';

    public function __construct()
    {
        $this->baseApiUrl = config('imagetotext.api_url');
        $this->token      = config('imagetotext.api_key');
        $this->rateLimits = [
            ['limit' => 1, 'interval' => 5, 'waitPerAttempt' => 5],
        ];
    }

    public function convert(string $url)
    {
        $result = $this->post('imageToText', [
            'image_url' => $url,
        ])->json();

        if (!empty($result['error'])) {
            throw new Exception("Image To Text Failed to convert $url: " . ($result['message'] ?? 'Unknown error'));
        }

        return $result['result'] ?? null;
    }
}
