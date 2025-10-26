<?php

namespace App\Api\ScreenshotOne;

use GuzzleHttp\Exception\GuzzleException;
use Newms87\Danx\Api\Api;
use Newms87\Danx\Exceptions\ApiException;
use Newms87\Danx\Exceptions\ApiRequestException;

class ScreenshotOneApi extends Api
{
    public static string $serviceName = 'ScreenshotOne';

    protected ?string $token = null;

    public function __construct()
    {
        $this->baseApiUrl = config('screenshotone.api_url');
        $this->token      = config('screenshotone.api_key');
    }

    public function getRequestHeaders(): array
    {
        return [
            'X-Access-Key' => $this->token,
        ] + parent::getRequestHeaders();
    }

    /**
     * @param  string  $url  The URL to take screenshot of
     * @param  string  $path  The path to store the screenshot
     * @return string The URL of the stored screenshot
     *
     * @throws ApiException
     * @throws ApiRequestException
     * @throws GuzzleException
     */
    public function take(string $url, string $path): string
    {
        $options = [
            'url'                       => $url,

            // Image Options
            // 768 is the max width for OpenAI Vision API. 760 due to additional padding added by Screenshot One. All images will be scaled to this size (on one side)
            'viewport_width'            => 760,
            'full_page'                 => true,
            'reduced_motion'            => true,
            'block_cookie_banners'      => true,
            'block_ads'                 => true,
            'block_trackers'            => true,

            // Storage options
            'store'                     => true,
            'storage_path'              => preg_replace('/\\.[a-z0-9]{2,3}$/', '', $path),
            'storage_bucket'            => config('screenshotone.s3_bucket'),
            'storage_return_location'   => true,
            'response_type'             => 'json',
            'storage_access_key_id'     => config('screenshotone.s3_access_key'),
            'storage_secret_access_key' => config('screenshotone.s3_secret_key'),
        ];

        $response = $this->post('take', $options)->json();

        $location = $response['store']['location'] ?? null;

        if (!$location) {
            throw new ApiException("ScreenshotOne failed to take screenshot of $url");
        }

        return $location;
    }
}
