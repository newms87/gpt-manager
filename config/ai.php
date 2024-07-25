<?php

use App\AiTools\GoogleSerpAiTool;
use App\AiTools\SummarizerAiTool;
use App\AiTools\UrlToImageAiTool;
use App\Api\OpenAi\OpenAiApi;
use Tests\Feature\TestApi\TestAiApi\TestAiApi;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'          => env('AI_SEED', 11181987),
    'default_model' => 'gpt-4o',
    'models'        => [
        TestAiApi::$serviceName => [
            'test-model' => [
                'input'   => 0,
                'output'  => 0,
                'context' => 128000,
            ],
        ],
        OpenAiApi::$serviceName => [
            'gpt-4o-mini' => [
                'input'   => .00015,
                'output'  => .0006,
                'context' => 128000,
                'image'   => [
                    'tokens' => 5667,
                    'base'   => 2833,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4o'      => [
                'input'   => .005,
                'output'  => .015,
                'context' => 128000,
                'image'   => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4-turbo' => [
                'input'   => .01,
                'output'  => .03,
                'context' => 128000,
                'image'   => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
        ],
    ],
    'apis'          => [
        OpenAiApi::$serviceName => OpenAiApi::class,
        TestAiApi::$serviceName => TestAiApi::class,
    ],
    'tools'         => [
        [
            'class'       => UrlToImageAiTool::class,
            'name'        => UrlToImageAiTool::NAME,
            'description' => UrlToImageAiTool::DESCRIPTION,
            'parameters'  => UrlToImageAiTool::PARAMETERS,
        ],
        [
            'class'       => SummarizerAiTool::class,
            'name'        => SummarizerAiTool::NAME,
            'description' => SummarizerAiTool::DESCRIPTION,
            'parameters'  => SummarizerAiTool::PARAMETERS,
        ],
        [
            'class'       => GoogleSerpAiTool::class,
            'name'        => GoogleSerpAiTool::NAME,
            'description' => GoogleSerpAiTool::DESCRIPTION,
            'parameters'  => GoogleSerpAiTool::PARAMETERS,
        ],
    ],
];
