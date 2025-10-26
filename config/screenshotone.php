<?php

return [
    'api_key'       => env('SCREENSHOT_ONE_API_KEY'),
    'api_url'       => env('SCREENSHOT_ONE_API_URL', 'https://api.screenshotone.com/'),
    's3_access_key' => env('SCREENSHOT_ONE_S3_ACCESS_KEY'),
    's3_secret_key' => env('SCREENSHOT_ONE_S3_SECRET_KEY'),
    's3_bucket'     => env('SCREENSHOT_ONE_S3_BUCKET', 'gpt-manager-dev'),
];
