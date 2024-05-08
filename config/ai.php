<?php

use App\AiTools\UrlToScreenshotAiTool;
use App\Api\OpenAi\OpenAiApi;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'   => env('AI_SEED', 11181987),
    'models' => [
        OpenAiApi::$serviceName => [
            'gpt-4-turbo-2024-04-09' => [
                'input'  => .01,
                'output' => .03,
                'image'  => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4'                  => [
                'input'  => .03,
                'output' => .06,
            ],
            'gpt-4-32k'              => [
                'input'  => .06,
                'output' => .12,
            ],
            'gpt-3.5-turbo-0125'     => [
                'input'  => .0005,
                'output' => .0015,
            ],
            'gpt-3.5-turbo-instruct' => [
                'input'  => .0015,
                'output' => .0020,
            ],
            'davinci-002'            => [
                'input'  => .002,
                'output' => .002,
            ],
            'babbage-002'            => [
                'input'  => .0004,
                'output' => .0004,
            ],
        ],
    ],
    'apis'   => [
        OpenAiApi::$serviceName => OpenAiApi::class,
    ],
    'tools'  => [
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
