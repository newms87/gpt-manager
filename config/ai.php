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
    
    // Classification deduplication agent configuration
    'classification_deduplication' => [
        'agent_name' => 'Classification Deduplication Agent',
        'model' => env('AI_CLASSIFICATION_DEDUP_MODEL', 'o4-mini'),
    ],
    'models'        => [
        OpenAiApi::$serviceName => [
            'gpt-4o' => [
                'name' => 'GPT‑4o',
                'context' => 128_000,
                'input' => 2.50 / $million,
                'cached_input' => 1.25 / $million,
                'output' => 10.00 / $million,
                'features' => [
                    'streaming' => true,
                    'function_calling' => true,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => false,
                    'predicted_outputs' => true,
                    'image_input' => true,
                    'audio_input' => true,
                    'reasoning' => false,
                    'temperature' => true,
                ],
                'rate_limits' => [
                    'tokens_per_minute' => 150_000,
                    'requests_per_minute' => 900,
                ],
                'image' => [
                    'tokens' => 170,
                    'base' => 85,
                    'tile' => '512x512',
                ],
            ],
            'gpt-4o-mini' => [
                'name' => 'GPT‑4o Mini',
                'context' => 128_000,
                'input' => 0.15 / $million,
                'cached_input' => 0.075 / $million,
                'output' => 0.60 / $million,
                'features' => [
                    'streaming' => true,
                    'function_calling' => false,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => false,
                    'predicted_outputs' => false,
                    'image_input' => true,
                    'audio_input' => false,
                    'reasoning' => false,
                    'temperature' => true,
                ],
                'rate_limits' => [
                    'tokens_per_minute' => 450_000,
                    'requests_per_minute' => 2_700,
                ],
                'image' => [
                    'tokens' => 5667,
                    'base' => 2833,
                    'tile' => '512x512',
                ],
            ],
            'o4-mini' => [
                'name' => 'o4‑Mini',
                'context' => 200_000,
                'input' => 1.10 / $million,
                'cached_input' => 0.275 / $million,
                'output' => 4.40 / $million,
                'features' => [
                    'streaming' => true,
                    'function_calling' => true,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => false,
                    'predicted_outputs' => false,
                    'image_input' => true,
                    'audio_input' => false,
                    'reasoning' => true,
                    'temperature' => false,
                ],
                'rate_limits' => [
                    'tokens_per_minute' => 200_000,
                    'requests_per_minute' => 1_000,
                ],
            ],
            'o3' => [
                'name' => 'o3',
                'context' => 200_000,
                'input' => 2.00 / $million,
                'cached_input' => 0.50 / $million,
                'output' => 8.00 / $million,
                'features' => [
                    'streaming' => true,
                    'function_calling' => true,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => false,
                    'predicted_outputs' => false,
                    'image_input' => true,
                    'audio_input' => false,
                    'reasoning' => true,
                    'temperature' => false,
                ],
                'rate_limits' => [
                    'tokens_per_minute' => 100_000,
                    'requests_per_minute' => 600,
                ],
            ],
            'o3-pro' => [
                'name' => 'o3‑Pro',
                'context' => 200_000,
                'input' => 20.00 / $million,
                'cached_input' => 5.00 / $million,
                'output' => 80.00 / $million,
                'features' => [
                    'streaming' => true,
                    'function_calling' => true,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => false,
                    'predicted_outputs' => false,
                    'image_input' => true,
                    'audio_input' => false,
                    'reasoning' => true,
                    'temperature' => false,
                ],
                'rate_limits' => [
                    'tokens_per_minute' => 50_000,
                    'requests_per_minute' => 200,
                ],
            ],
        ],
        PerplexityAiApi::$serviceName => [
            'llama-3.1-sonar-small-128k-online' => [
                'context' => 127072,
                'input' => .2 / $million,
                'output' => .2 / $million,
                'per_request' => 5 / $thousand,
            ],
            'llama-3.1-sonar-large-128k-online' => [
                'context' => 127072,
                'input' => 1 / $million,
                'output' => 1 / $million,
                'per_request' => 5 / $thousand,
            ],
            'llama-3.1-sonar-huge-128k-online' => [
                'context' => 127072,
                'input' => 5 / $million,
                'output' => 5 / $million,
                'per_request' => 5 / $thousand,
            ],
        ],
    ],
    'apis' => [
        OpenAiApi::$serviceName => OpenAiApi::class,
        PerplexityAiApi::$serviceName => PerplexityAiApi::class,
    ],
];
