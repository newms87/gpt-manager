<?php

use App\AiTools\UrlToScreenshotAiTool;
use App\Api\OpenAi\OpenAiApi;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'  => env('AI_SEED', 11181987),
    'apis'  => [
        OpenAiApi::$serviceName => OpenAiApi::class,
    ],
    'tools' => [
        [
            'name'        => 'code-interpreter',
            'description' => 'Run code in a variety of languages and return the output',
        ],
        [
            'class'       => UrlToScreenshotAiTool::class,
            'name'        => UrlToScreenshotAiTool::NAME,
            'description' => UrlToScreenshotAiTool::DESCRIPTION,
            'parameters'  => UrlToScreenshotAiTool::PARAMETERS,
        ],
    ],
];
