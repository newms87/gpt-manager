<?php

use App\AiTools\GoogleSerpAiTool;
use App\AiTools\SaveObjects\SaveObjectsAiTool;
use App\AiTools\SummarizerAiTool;
use App\AiTools\UrlToImageAiTool;
use App\AiTools\UrlToMarkdownAiTool;
use App\Api\OpenAi\OpenAiApi;
use App\Api\PerplexityAi\PerplexityAiApi;

$million  = 1000000;
$thousand = 1000;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'          => env('AI_SEED', 11181987),
    'default_model' => 'gpt-4o',
    'models'        => [
        PerplexityAiApi::$serviceName => [
            'llama-3.1-sonar-small-128k-online' => [
                'context'     => 127072,
                'input'       => .2 / $million,
                'output'      => .2 / $million,
                'per_request' => 5 / $thousand,
            ],
            'llama-3.1-sonar-large-128k-online' => [
                'context'     => 127072,
                'input'       => 1 / $million,
                'output'      => 1 / $million,
                'per_request' => 5 / $thousand,
            ],
            'llama-3.1-sonar-huge-128k-online'  => [
                'context'     => 127072,
                'input'       => 5 / $million,
                'output'      => 5 / $million,
                'per_request' => 5 / $thousand,
            ],
        ],
        OpenAiApi::$serviceName       => [
            'gpt-4o-mini'       => [
                'input'   => .15 / $million,
                'output'  => .60 / $million,
                'context' => 128000,
                'image'   => [
                    'tokens' => 5667,
                    'base'   => 2833,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4o'            => [
                'input'   => 5 / $million,
                'output'  => 15 / $million,
                'context' => 128000,
                'image'   => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4o-2024-08-06' => [
                'input'   => 2.5 / $million,
                'output'  => 10 / $million,
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
        OpenAiApi::$serviceName       => OpenAiApi::class,
        PerplexityAiApi::$serviceName => PerplexityAiApi::class,
    ],
    'tools'         => [
        [
            'class'       => UrlToImageAiTool::class,
            'name'        => UrlToImageAiTool::NAME,
            'description' => UrlToImageAiTool::DESCRIPTION,
            'parameters'  => UrlToImageAiTool::PARAMETERS,
        ],
        [
            'class'       => UrlToMarkdownAiTool::class,
            'name'        => UrlToMarkdownAiTool::NAME,
            'description' => UrlToMarkdownAiTool::DESCRIPTION,
            'parameters'  => UrlToMarkdownAiTool::PARAMETERS,
        ],
        [
            'class'       => SaveObjectsAiTool::class,
            'name'        => SaveObjectsAiTool::$name,
            'description' => SaveObjectsAiTool::description(),
            'parameters'  => SaveObjectsAiTool::parameters(),
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
