<?php

use App\Api\OpenAi\OpenAiApi;
use App\Api\PerplexityAi\PerplexityAiApi;

$million  = 1_000_000;
$thousand = 1_000;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'          => env('AI_SEED', 11181987),
    'default_api'   => OpenAiApi::$serviceName,
    'default_model' => 'o4-mini',
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
            'o4-mini'     => [
                'input'            => 1.1 / $million,
                'output'           => 4.4 / $million,
                'context'          => 200_000,
                'excluded_options' => [
                    'temperature',
                ],
            ],
            'gpt-4.1'     => [
                'input'            => 2 / $million,
                'output'           => 8 / $million,
                'context'          => 1_047_576,
                'excluded_options' => [
                    'temperature',
                ],
            ],
            'o3'          => [
                'input'            => 2 / $million,
                'output'           => 8 / $million,
                'context'          => 200_000,
                'excluded_options' => [
                    'temperature',
                ],
            ],
            'o3-mini'     => [
                'input'            => 1.1 / $million,
                'output'           => 4.4 / $million,
                'context'          => 128_000,
                'excluded_options' => [
                    'temperature',
                ],
            ],
            'gpt-4o-mini' => [
                'input'   => .15 / $million,
                'output'  => .60 / $million,
                'context' => 128000,
                'image'   => [
                    'tokens' => 5667,
                    'base'   => 2833,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4o'      => [
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
];
