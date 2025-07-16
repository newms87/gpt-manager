<?php

return [
    \App\Api\ImageToText\ImageToTextOcrApi::$serviceName => [
        'pricing' => [
            'per_request' => 0.001, // $0.001 per OCR request
            'description' => 'Image to Text OCR API - charged per request',
        ],
    ],

    \App\Api\ScreenshotOne\ScreenshotOneApi::$serviceName => [
        'pricing' => [
            'per_request' => 0.002, // $0.002 per screenshot
            'description' => 'ScreenshotOne API - charged per screenshot taken',
        ],
    ],

    \App\Api\GoogleSerpApi\GoogleSerpApi::$serviceName => [
        'pricing' => [
            'per_request' => 0.01, // $0.01 per search query
            'description' => 'Google SERP API - charged per search query',
        ],
    ],

    \Newms87\Danx\Api\ConvertApi\ConvertApi::$serviceName => [
        'pricing' => [
            'per_unit'    => 0.005, // $0.005 per conversion unit
            'description' => 'ConvertAPI - charged per conversion unit',
        ],
    ],
];
