<?php

use App\AiTools\GoogleSerp\GoogleSerpAiTool;
use App\AiTools\SaveTeamObjects\SaveTeamObjectsAiTool;
use App\AiTools\Summarizer\SummarizerAiTool;
use App\AiTools\UrlToImage\UrlToImageAiTool;
use App\AiTools\UrlToMarkdown\UrlToMarkdownAiTool;
use App\Api\OpenAi\OpenAiApi;
use App\Api\PerplexityAi\PerplexityAiApi;

$million  = 1000000;
$thousand = 1000;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'          => env('AI_SEED', 11181987),
    'default_api'   => OpenAiApi::$serviceName,
    'default_model' => 'gpt-4o-2024-08-06',
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
            'name'        => UrlToImageAiTool::$name,
            'description' => UrlToImageAiTool::description(),
            'parameters'  => UrlToImageAiTool::parameters(),
        ],
        [
            'class'       => UrlToMarkdownAiTool::class,
            'name'        => UrlToMarkdownAiTool::$name,
            'description' => UrlToMarkdownAiTool::description(),
            'parameters'  => UrlToMarkdownAiTool::parameters(),
        ],
        [
            'class'       => SaveTeamObjectsAiTool::class,
            'name'        => SaveTeamObjectsAiTool::$name,
            'description' => SaveTeamObjectsAiTool::description(),
            'parameters'  => SaveTeamObjectsAiTool::parameters(),
        ],
        [
            'class'       => SummarizerAiTool::class,
            'name'        => SummarizerAiTool::$name,
            'description' => SummarizerAiTool::description(),
            'parameters'  => SummarizerAiTool::parameters(),
        ],
        [
            'class'       => GoogleSerpAiTool::class,
            'name'        => GoogleSerpAiTool::$name,
            'description' => GoogleSerpAiTool::description(),
            'parameters'  => GoogleSerpAiTool::parameters(),
        ],
    ],
];
